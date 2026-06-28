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
     * play days, working in GRID SLOTS (not raw minutes) so every proposed
     * window aligns to the scheduler's slot grid. This guarantees:
     *   - a phase starts exactly on a grid slot (so the first slot of a day is
     *     usable — no "08:28" cutting off the 08:00 slot),
     *   - a phase window fully contains the last slot its matches use (so the
     *     window end never cuts a slot short — no matches stranded near play_end),
     *   - phases are separated by whole-slot buffers so nothing is orphaned.
     *
     * The grid matches Tournament::timeSlots(): start at play_start, step by
     * match_duration, while slotStart + duration <= play_end.
     *
     * @return array{windows: array<string,array{starts_at:string,ends_at:string}>, overflow: bool}
     */
    public function proposeWindows(Tournament $tournament): array
    {
        $preview = $this->preview($tournament);
        $courts = max(1, $preview['courts']);
        $duration = $preview['duration'];

        $days = $tournament->playDays()->values();
        if ($days->isEmpty()) return ['windows' => [], 'overflow' => false];

        $playStart = Carbon::parse($tournament->play_start ?? '08:00', 'America/Mexico_City');
        $playEnd = Carbon::parse($tournament->play_end ?? '23:00', 'America/Mexico_City');
        $startMin = $playStart->hour * 60 + $playStart->minute;
        $endMin = $playEnd->hour * 60 + $playEnd->minute;

        // How many whole match-slots fit in one day's play window.
        $slotsPerDay = intdiv(max(0, $endMin - $startMin), $duration);
        if ($slotsPerDay < 1) return ['windows' => [], 'overflow' => false];

        // Slot buffer between phases (at least one empty slot row), derived from
        // the old 30-min gap but rounded UP to whole slots so boundaries stay on
        // the grid.
        $bufferSlots = (int) max(1, ceil(30 / $duration));

        // Cursor expressed as a GLOBAL SLOT INDEX across all play days:
        //   slotIndex = dayIdx * slotsPerDay + slotOfDay   (0-based)
        // Helpers convert an index to its start/end timestamps.
        $maxSlotIndex = $days->count() * $slotsPerDay; // exclusive upper bound

        $slotStartStamp = function (int $idx) use ($days, $slotsPerDay, $startMin, $duration) {
            $dayIdx = intdiv($idx, $slotsPerDay);
            $slotOfDay = $idx % $slotsPerDay;
            $minOfDay = $startMin + $slotOfDay * $duration;
            $d = $days->get(min($dayIdx, $days->count() - 1));
            return Carbon::parse($d->format('Y-m-d'), 'America/Mexico_City')
                ->setTime(intdiv($minOfDay, 60), $minOfDay % 60);
        };
        // End-of-slot = start of slot + one match duration (the slot's closing edge).
        $slotEndStamp = function (int $idx) use ($slotStartStamp, $duration) {
            return $slotStartStamp($idx)->copy()->addMinutes($duration);
        };

        $cursor = 0; // next free global slot index
        $proposal = [];
        $overflow = false;

        foreach (SchedulePhase::keys() as $phase) {
            $matches = $preview['perPhase'][$phase]['matches'] ?? 0;
            if ($matches === 0) continue;

            // Grid rows this phase needs (each row = up to $courts matches).
            $rows = (int) ceil($matches / $courts);

            // A phase must START at the next free slot. If that slot is the last
            // of a day and there isn't room, it naturally flows onto following
            // days because slot indices are continuous across days.
            $firstSlot = $cursor;
            if ($firstSlot >= $maxSlotIndex) {
                $overflow = true;
                break;
            }

            // The phase occupies $rows consecutive slot rows. Its window must
            // contain all of them: from the first slot's START to the last
            // slot's END (start + duration) — so the closing slot is never cut.
            $lastSlot = $firstSlot + $rows - 1;
            if ($lastSlot >= $maxSlotIndex) {
                $lastSlot = $maxSlotIndex - 1;
                $overflow = true; // ran out of grid before all rows fit
            }

            $proposal[$phase] = [
                'starts_at' => $slotStartStamp($firstSlot)->format('Y-m-d H:i'),
                'ends_at' => $slotEndStamp($lastSlot)->format('Y-m-d H:i'),
            ];

            // Advance cursor past this phase + a whole-slot buffer.
            $cursor = $lastSlot + 1 + $bufferSlots;
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
