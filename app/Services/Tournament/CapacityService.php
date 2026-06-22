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
                    // Count only PLAY-HOUR minutes within the window, not the raw
                    // calendar span (a Thu→Sat window doesn't include the
                    // overnight hours when no matches are played).
                    $mins = $this->playableMinutes($w->starts_at, $w->ends_at, $tournament);
                    $capacity += intdiv($mins, $duration) * $courts;
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

    /**
     * Playable minutes between two datetimes, counting only the daily play-hours
     * window (play_start..play_end) on each day the range spans. A Thu→Sat
     * window does NOT include the overnight 23:00→08:00 gaps.
     */
    private function playableMinutes(Carbon $start, Carbon $end, Tournament $tournament): int
    {
        $ps = Carbon::parse($tournament->play_start ?? '08:00', 'America/Mexico_City');
        $pe = Carbon::parse($tournament->play_end ?? '23:00', 'America/Mexico_City');
        $startMin = $ps->hour * 60 + $ps->minute;
        $endMin = $pe->hour * 60 + $pe->minute;

        $total = 0;
        $day = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($day->lte($last)) {
            // This day's playable window.
            $dayPlayStart = $day->copy()->setTime(intdiv($startMin, 60), $startMin % 60);
            $dayPlayEnd = $day->copy()->setTime(intdiv($endMin, 60), $endMin % 60);

            // Clip to the actual [start, end] range.
            $s = $start->greaterThan($dayPlayStart) ? $start : $dayPlayStart;
            $e = $end->lessThan($dayPlayEnd) ? $end : $dayPlayEnd;

            if ($e->greaterThan($s)) {
                $total += $s->diffInMinutes($e);
            }
            $day->addDay();
        }

        return $total;
    }

    /**
     * Propose phase windows by laying phases end-to-end across the tournament's
     * play days. Each phase consumes its court-time (+15% headroom), followed by
     * a 30-min buffer before the next. Flows across days within play hours.
     *
     * @return array<string,array{starts_at:string, ends_at:string}>  (Y-m-d H:i)
     */
    public function proposeWindows(Tournament $tournament): array
    {
        $preview = $this->preview($tournament);
        $courts = $preview['courts'];
        $duration = $preview['duration'];

        $bufferMin = 30;       // gap between phases
        $headroom = 1.15;      // +15% slack on each phase's court-time

        $days = $tournament->playDays();
        if ($days->isEmpty()) return [];

        $playStart = Carbon::parse($tournament->play_start ?? '08:00', 'America/Mexico_City');
        $playEnd = Carbon::parse($tournament->play_end ?? '23:00', 'America/Mexico_City');
        $startMin = $playStart->hour * 60 + $playStart->minute;
        $endMin = $playEnd->hour * 60 + $playEnd->minute;

        // Cursor: day index + minute-of-day.
        $dayIdx = 0;
        $cur = $startMin;

        $advance = function (int $minutes) use (&$dayIdx, &$cur, $startMin, $endMin, $days) {
            // Move the cursor forward $minutes, wrapping to next day at play_end.
            $remaining = $minutes;
            while ($remaining > 0) {
                $left = $endMin - $cur;
                if ($remaining <= $left) {
                    $cur += $remaining;
                    $remaining = 0;
                } else {
                    $remaining -= $left;
                    $dayIdx++;
                    $cur = $startMin;
                    if ($dayIdx >= $days->count()) {
                        // Out of days: clamp to last day's end.
                        $dayIdx = $days->count() - 1;
                        $cur = $endMin;
                        return;
                    }
                }
            }
        };

        $stamp = function () use ($days, &$dayIdx, &$cur) {
            $d = $days->get(min($dayIdx, $days->count() - 1));
            $h = intdiv($cur, 60);
            $m = $cur % 60;
            return Carbon::parse($d->format('Y-m-d'), 'America/Mexico_City')
                ->setTime($h, $m)->format('Y-m-d H:i');
        };

        $proposal = [];
        $overflow = false;
        foreach (SchedulePhase::keys() as $phase) {
            $matches = $preview['perPhase'][$phase]['matches'] ?? 0;
            if ($matches === 0) continue;

            // Court-time minutes needed for this phase (rows × duration), padded.
            $rows = (int) ceil($matches / max(1, $courts));
            $need = (int) ceil($rows * $duration * $headroom);

            $startStamp = $stamp();
            $advance($need);
            $endStamp = $stamp();

            // Overflow: a phase ran out of days (clamped at the last day's end),
            // or produced a zero-length window (no room left).
            if (($dayIdx >= $days->count() - 1 && $cur >= $endMin) || $startStamp === $endStamp) {
                $overflow = true;
            }
            if ($startStamp === $endStamp) $overflow = true;

            $proposal[$phase] = ['starts_at' => $startStamp, 'ends_at' => $endStamp];

            $advance($bufferMin);
        }

        return ['windows' => $proposal, 'overflow' => $overflow];
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
