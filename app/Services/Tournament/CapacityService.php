<?php

namespace App\Services\Tournament;

use App\Models\Tournament;
use App\Support\SchedulePhase;
use Carbon\Carbon;

/**
 * Capacity preview: tallies how many matches each phase/category needs and how
 * much court time that implies, so a manager can size phase windows BEFORE
 * running the auto-scheduler.
 *
 * Bracket counts are derived from configuration (groups × advance_per_group +
 * extra_qualifiers → qualifier count → bracket size), so the preview works even
 * before groups are played.
 */
class CapacityService
{
    /**
     * @return array{
     *   rows: array<int,array>,          // per category, per-phase counts
     *   phaseTotals: array<string,int>,  // phaseKey => total matches
     *   courts: int, duration: int,
     *   perPhase: array<string,array{matches:int,slotRows:float,courtHours:float,fits:?bool,shortfall:int}>
     * }
     */
    public function preview(Tournament $tournament): array
    {
        $tournament->loadMissing('categories.groups.pairs', 'phaseWindows');
        $courts = max(1, $tournament->courts()->count());
        $duration = (int) ($tournament->match_duration_minutes ?: 75);

        $rows = [];
        $phaseTotals = array_fill_keys(SchedulePhase::keys(), 0);

        foreach ($tournament->categories as $category) {
            $counts = $this->categoryCounts($category);
            foreach ($counts as $phase => $n) {
                if (str_starts_with($phase, '_')) continue; // display-only keys
                $phaseTotals[$phase] += $n;
            }
            $realTotal = 0;
            foreach ($counts as $phase => $n) {
                if (! str_starts_with($phase, '_')) $realTotal += $n;
            }
            $rows[] = [
                'category' => $category->name,
                'counts' => $counts,
                'total' => $realTotal,
            ];
        }

        // Window capacity per phase (in match-slots), from saved windows.
        $windowsByPhase = $tournament->phaseWindows->groupBy('phase');

        $perPhase = [];
        foreach (SchedulePhase::keys() as $phase) {
            $matches = $phaseTotals[$phase];
            if ($matches === 0) continue;

            $slotRows = $courts > 0 ? ceil($matches / $courts) : 0;       // grid rows needed
            $courtHours = round(($matches * $duration) / ($courts * 60), 1); // total court-time hrs

            // Does the configured window hold it?
            $fits = null;
            $shortfall = 0;
            $wins = $windowsByPhase->get($phase);
            if ($wins && $wins->isNotEmpty()) {
                $capacity = 0;
                foreach ($wins as $w) {
                    $mins = $w->starts_at->diffInMinutes($w->ends_at);
                    $capacity += intdiv((int) $mins, $duration) * $courts;
                }
                $fits = $matches <= $capacity;
                $shortfall = max(0, $matches - $capacity);
            }

            $perPhase[$phase] = [
                'matches' => $matches,
                'slotRows' => $slotRows,
                'courtHours' => $courtHours,
                'fits' => $fits,
                'shortfall' => $shortfall,
            ];
        }

        return [
            'rows' => $rows,
            'phaseTotals' => $phaseTotals,
            'courts' => $courts,
            'duration' => $duration,
            'perPhase' => $perPhase,
        ];
    }

    /**
     * Per-phase match counts for one category, derived from config.
     * Returns [phaseKey => count] including a synthetic 'groups' total.
     */
    private function categoryCounts($category): array
    {
        $counts = array_fill_keys(SchedulePhase::keys(), 0);

        // --- Group matches (actual, from generated groups) ---
        // Tracked split into R1/R2 for the inventory display; both roll into the
        // single 'groups' phase window for capacity.
        $gr1 = 0;
        $gr2 = 0;
        if ($category->format->hasGroups()) {
            foreach ($category->groups as $group) {
                $size = $group->pairs->count();
                $isMexicano = $category->group_format === \App\Enums\GroupFormat::Mexicano && $size === 4;
                if ($isMexicano) {
                    $gr1 += 2; // round 1: 2 matches
                    $gr2 += 2; // round 2: 2 matches
                } else {
                    $gr1 += $size < 2 ? 0 : intdiv($size * ($size - 1), 2); // round-robin = all R1
                }
            }
            $counts['groups'] = $gr1 + $gr2;
        }
        $counts['_groups_r1'] = $gr1; // display-only (underscore = not a phase window)
        $counts['_groups_r2'] = $gr2;

        // --- Elimination matches (derived from qualifier count) ---
        if ($category->format->hasBracket()) {
            $qualifiers = $this->qualifierCount($category);
            $bracketSize = $this->nextPow2($qualifiers); // bracket draws to a power of 2

            if ($bracketSize >= 2) {
                // First round: byes (top seeds) don't play, so the real match
                // count is qualifiers − bracketSize/2 (not bracketSize/2).
                $byes = $bracketSize - $qualifiers; // pairs that skip round 1
                $firstRoundMatches = max(0, intdiv($qualifiers - $byes, 2));
                $firstPhase = $this->roundPhase($bracketSize);
                $counts[$firstPhase] += $firstRoundMatches;

                // Subsequent rounds are always full (byes already resolved).
                $n = intdiv($bracketSize, 2); // field size of round 2
                while ($n >= 2) {
                    $counts[$this->roundPhase($n)] += intdiv($n, 2);
                    $n = intdiv($n, 2);
                }

                // Third-place match.
                if ($category->has_third_place && $bracketSize >= 4) {
                    $counts['final'] += 1;
                }
            }
        }

        return $counts;
    }

    /** qualifiers = groups × advance_per_group + extra_qualifiers (config-based). */
    private function qualifierCount($category): int
    {
        $groupCount = $category->groups->count();
        if ($groupCount === 0) {
            // No groups yet: estimate from preferred size if available, else 0.
            return 0;
        }
        return $groupCount * (int) $category->advance_per_group + (int) ($category->extra_qualifiers ?? 0);
    }

    /** Smallest power of 2 ≥ n (min 2). */
    private function nextPow2(int $n): int
    {
        if ($n < 2) return 0;
        $p = 2;
        while ($p < $n) $p *= 2;
        return $p;
    }

    /** Field size (pairs in the round) → phase key. */
    private function roundPhase(int $fieldSize): string
    {
        return match ($fieldSize) {
            2 => 'final',
            4 => 'semifinal',
            8 => 'quarterfinal',
            16 => 'r16',
            default => 'r32',
        };
    }
}
