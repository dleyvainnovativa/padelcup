<?php

namespace App\Services\Tournament;

use App\Models\Court;
use App\Models\GameMatch;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scheduling: assigns matches to courts and times within court availability
 * windows, detecting two kinds of conflict:
 *
 *   1. Court overlap   — two matches on the same court at the same time.
 *   2. Player overlap  — a player (possibly in two pairs across categories)
 *                        scheduled in two places at once. This is the whole
 *                        reason player-level tracking exists.
 *
 * Times are CDMX. Default match duration is configurable per call.
 */
class SchedulingService
{
    /**
     * Check whether placing $match on $court at $startsAt for $duration would
     * conflict. Returns a list of human-readable conflict reasons (empty = ok).
     *
     * @return array<int, string>
     */
    public function conflictsFor(GameMatch $match, Court $court, Carbon $startsAt, int $duration): array
    {
        $endsAt = $startsAt->copy()->addMinutes($duration);
        $conflicts = [];

        // 1. Must fall within an availability window of the court.
        if (! $this->withinAvailability($court, $startsAt, $endsAt)) {
            $conflicts[] = 'Fuera del horario disponible de la cancha.';
        }

        // 2. Court overlap.
        if ($this->courtOverlap($court, $startsAt, $endsAt, $match->id)) {
            $conflicts[] = 'La cancha ya tiene un partido en ese horario.';
        }

        // 3. Player overlap (across ALL of this tournament's matches).
        $playerIds = $match->playerIds();
        $clash = $this->playerOverlap($match, $playerIds, $startsAt, $endsAt);
        if ($clash) {
            $conflicts[] = "Conflicto de jugador: {$clash} ya juega en ese horario.";
        }

        return $conflicts;
    }

    private function withinAvailability(Court $court, Carbon $startsAt, Carbon $endsAt): bool
    {
        return $court->availabilities()
            ->where('starts_at', '<=', $startsAt)
            ->where('ends_at', '>=', $endsAt)
            ->exists();
    }

    private function courtOverlap(Court $court, Carbon $startsAt, Carbon $endsAt, int $exceptMatchId): bool
    {
        return GameMatch::where('court_id', $court->id)
            ->where('id', '!=', $exceptMatchId)
            ->whereNotNull('starts_at')
            ->get()
            ->contains(fn($m) => $this->overlaps(
                $startsAt,
                $endsAt,
                $m->starts_at,
                $m->starts_at->copy()->addMinutes((int) ($m->duration_minutes ?: 60))
            ));
    }

    /**
     * Returns the name of a clashing player if any player in $playerIds is
     * already scheduled in an overlapping match elsewhere in the tournament.
     */
    private function playerOverlap(GameMatch $match, array $playerIds, Carbon $startsAt, Carbon $endsAt): ?string
    {
        if (empty($playerIds)) return null;

        $tournamentId = $match->category->tournament_id;

        // All scheduled matches in the tournament (other than this one).
        $scheduled = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournamentId))
            ->where('id', '!=', $match->id)
            ->whereNotNull('starts_at')
            ->with(['pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->get();

        foreach ($scheduled as $other) {
            $otherEnds = $other->starts_at->copy()->addMinutes((int) ($other->duration_minutes ?: 60));
            if (! $this->overlaps($startsAt, $endsAt, $other->starts_at, $otherEnds)) {
                continue;
            }
            $shared = array_intersect($playerIds, $other->playerIds());
            if (! empty($shared)) {
                // Name the first clashing player.
                $pid = reset($shared);
                $name = $this->playerName($other, $pid) ?? 'Un jugador';
                return $name;
            }
        }

        return null;
    }



    private function overlaps(Carbon $aStart, Carbon $aEnd, Carbon $bStart, Carbon $bEnd): bool
    {
        return $aStart->lt($bEnd) && $bStart->lt($aEnd);
    }

    /**
     * After the tournament's scheduling settings change, unschedule ONLY the
     * matches that no longer fit the new grid — i.e. their start time now falls
     * outside [play_start, play_end), outside the tournament date range, or no
     * longer lands on a valid slot boundary. Still-valid placements are left
     * exactly where they are.
     *
     * @return int  number of matches moved back to "sin programar"
     */
    public function pruneInvalidSchedule(Tournament $tournament): int
    {
        $validDates = $tournament->playDays()->map->format('Y-m-d')->all();
        $slots = $tournament->timeSlots(); // ['08:00', '09:30', ...]

        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->get();

        $movedIds = [];

        foreach ($matches as $m) {
            $local = $m->starts_at->timezone('America/Mexico_City');
            $date = $local->format('Y-m-d');
            $time = $local->format('H:i');

            $outOfRange = ! in_array($date, $validDates, true);
            $offGrid = ! in_array($time, $slots, true);

            if ($outOfRange || $offGrid) {
                $movedIds[] = $m->id;
            }
        }

        if (! empty($movedIds)) {
            GameMatch::whereIn('id', $movedIds)->update([
                'starts_at' => null,
                'court_id' => null,
            ]);
        }

        return count($movedIds);
    }

    /**
     * Phase-window auto-scheduler (in-memory). Places each unscheduled, ready
     * match within its PHASE WINDOW (groups / quarterfinal / semifinal / final…)
     * intersected with court availability, enforcing a per-pair rest gap
     * (min_rest_minutes) to prevent player collapse.
     *
     * If no phase windows are defined for the tournament, falls back to plain
     * court-availability scheduling (Step 1 behavior) so nothing breaks.
     *
     * @return array{scheduled: int, unplaced: int, by_phase: array<string,array{scheduled:int,unplaced:int}>}
     */
    public function autoSchedule(Tournament $tournament, Collection $courts, int $duration = 60, int $stepMinutes = 30): array
    {
        $stepSec = $stepMinutes * 60;
        $durSec = $duration * 60;
        $restSec = ((int) ($tournament->min_rest_minutes ?? 30)) * 60;

        // Court availability windows: [courtId => [[startTs, endTs], ...]].
        $courtWindows = [];
        foreach ($courts as $court) {
            $courtWindows[$court->id] = $court->availabilities
                ->map(fn($w) => [$w->starts_at->timestamp, $w->ends_at->timestamp])
                ->sortBy(fn($w) => $w[0])->values()->all();
        }

        // Phase windows: [phaseKey => [[startTs, endTs], ...]].
        $phaseWindows = [];
        foreach ($tournament->phaseWindows as $pw) {
            $phaseWindows[$pw->phase][] = [$pw->starts_at->timestamp, $pw->ends_at->timestamp];
        }
        $hasPhases = ! empty($phaseWindows);

        // Grid anchor: matches must align to the tournament's slot grid
        // (play_start stepping by match_duration_minutes) so they always land on
        // a visible row. We snap candidate start times to this grid.
        $anchorParse = Carbon::parse($tournament->play_start ?? '08:00', 'America/Mexico_City');
        $anchorMinOfDay = $anchorParse->hour * 60 + $anchorParse->minute;
        $gridStepSec = ((int) ($tournament->match_duration_minutes ?: $duration)) * 60;

        // Seed occupancy from already-scheduled matches.
        $existing = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->with(['pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->get();

        $courtBusy = [];
        $playerBusy = [];
        $endOf = []; // [matchId => endTs] — seeded with already-scheduled matches
        foreach ($existing as $m) {
            $start = $m->starts_at->timestamp;
            $end = $start + (((int) ($m->duration_minutes ?: $duration)) * 60);
            $endOf[$m->id] = $end;
            if ($m->court_id) $courtBusy[$m->court_id][] = [$start, $end];
            foreach ($m->playerIds() as $pid) {
                $playerBusy[$pid][] = [$start, $end];
            }
        }

        // Unscheduled matches to place. With phase windows we INCLUDE placeholder
        // matches (pairs not yet known — Mexicano R2, bracket rounds fed by earlier
        // results) so the phase window can RESERVE their court/time ahead of time.
        // Without phase windows we keep the conservative behavior (ready matches
        // only), since there's no window to anchor a placeholder to.
        $query = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNull('starts_at')
            ->with(['group', 'category', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2']);

        if ($hasPhases) {
            // Ready matches OR placeholders that will be fed later.
            $query->where(function ($q) {
                $q->where(fn($q) => $q->whereNotNull('pair_a_id')->whereNotNull('pair_b_id'))
                    ->orWhereNotNull('feeder_a_id')
                    ->orWhereNotNull('feeder_b_id');
            });
        } else {
            $query->whereNotNull('pair_a_id')->whereNotNull('pair_b_id');
        }

        $matches = $query->get();

        // Order matches: phase → round (so dependencies hold), and WITHIN each
        // phase+round, INTERLEAVE categories so the early slots get a mix instead
        // of one category clustering at the front. This spreads each category
        // across the day. Round/feeder ordering is preserved because we never
        // mix matches of different rounds — only different categories of the same
        // round are interleaved.
        $order = \App\Support\SchedulePhase::keys();

        // 1) Bucket by (phase, round), keyed so buckets sort chronologically.
        $buckets = [];
        foreach ($matches as $m) {
            $bucketKey = sprintf('%02d-%03d', array_search($m->phaseKey(), $order, true), (int) ($m->round ?? 0));
            $buckets[$bucketKey][$m->category_id][] = $m;
        }
        ksort($buckets);

        // 2) Within each bucket, round-robin through categories (interleave).
        $ordered = [];
        foreach ($buckets as $byCategory) {
            // Each category's matches keep their own order; we rotate across
            // categories taking one at a time until all are drained.
            $queues = array_values($byCategory);
            $i = 0;
            while (! empty($queues)) {
                $idx = $i % count($queues);
                $ordered[] = array_shift($queues[$idx]);
                if (empty($queues[$idx])) {
                    array_splice($queues, $idx, 1);
                    // Don't advance $i: the next category shifted into this slot.
                } else {
                    $i++;
                }
            }
        }

        $matches = collect($ordered);

        $placements = []; // [matchId => [courtId, startTs]]
        $byPhase = [];     // [phaseKey => [scheduled, unplaced]]

        foreach ($matches as $match) {
            $phase = $match->phaseKey();
            $byPhase[$phase] ??= ['scheduled' => 0, 'unplaced' => 0];

            // Effective windows = court availability ∩ phase window (if defined).
            $allowed = $hasPhases ? ($phaseWindows[$phase] ?? []) : null;

            // A match cannot start before all its feeder matches have ENDED
            // (Mexicano R2 after its R1; bracket round after the round feeding it).
            // If a feeder isn't placed/known, we don't constrain on it.
            $earliest = 0;
            foreach ([$match->feeder_a_id, $match->feeder_b_id] as $fid) {
                if ($fid && isset($endOf[$fid])) {
                    $earliest = max($earliest, $endOf[$fid]);
                }
            }

            $playerIds = $match->playerIds();
            $slot = $this->findSlotInMemory(
                $courts,
                $courtWindows,
                $courtBusy,
                $playerBusy,
                $playerIds,
                $durSec,
                $stepSec,
                $restSec,
                $allowed,
                $anchorMinOfDay,
                $gridStepSec,
                $earliest,
            );

            if ($slot) {
                [$courtId, $startTs] = $slot;
                $endTs = $startTs + $durSec;
                $courtBusy[$courtId][] = [$startTs, $endTs];
                foreach ($playerIds as $pid) {
                    $playerBusy[$pid][] = [$startTs, $endTs];
                }
                $placements[$match->id] = [$courtId, $startTs];
                $endOf[$match->id] = $endTs;
                $byPhase[$phase]['scheduled']++;
            } else {
                $byPhase[$phase]['unplaced']++;
            }
        }

        DB::transaction(function () use ($placements, $duration) {
            foreach ($placements as $matchId => [$courtId, $startTs]) {
                GameMatch::where('id', $matchId)->update([
                    'court_id' => $courtId,
                    'starts_at' => Carbon::createFromTimestamp($startTs, 'America/Mexico_City'),
                    'duration_minutes' => $duration,
                ]);
            }
        });

        return [
            'scheduled' => count($placements),
            'unplaced' => $matches->count() - count($placements),
            'by_phase' => $byPhase,
        ];
    }

    /**
     * Earliest conflict-free slot using only in-memory arrays.
     *
     * @param  array|null  $allowed  Phase windows [[startTs,endTs],...] to
     *         intersect with court availability; null = no phase restriction.
     * @return array{0:int,1:int}|null  [courtId, startTs]
     */
    private function findSlotInMemory(
        Collection $courts,
        array $courtWindows,
        array $courtBusy,
        array $playerBusy,
        array $playerIds,
        int $durSec,
        int $stepSec,
        int $restSec = 0,
        ?array $allowed = null,
        int $anchorMinOfDay = 480,
        int $gridStepSec = 0,
        int $earliestStart = 0,
    ): ?array {
        $best = null;

        foreach ($courts as $court) {
            $cid = $court->id;
            foreach ($courtWindows[$cid] ?? [] as [$wStart, $wEnd]) {
                // Clip the court window to the allowed phase windows.
                $segments = $allowed === null
                    ? [[$wStart, $wEnd]]
                    : $this->clipToAllowed($wStart, $wEnd, $allowed);

                foreach ($segments as [$segStart, $segEnd]) {
                    // Floor the segment by the earliest allowed start (feeder
                    // matches must finish first).
                    $segStart = max($segStart, $earliestStart);
                    if ($segStart >= $segEnd) continue;

                    // Snap the segment start UP to the next grid-aligned slot so
                    // placements always land on a visible row (08:00, 09:15, ...).
                    $start = $gridStepSec > 0
                        ? $this->snapToGrid($segStart, $anchorMinOfDay, $gridStepSec)
                        : $segStart;

                    $lastStart = $segEnd - $durSec;
                    for ($ts = $start; $ts <= $lastStart; $ts += $stepSec) {
                        $end = $ts + $durSec;

                        if ($this->intervalHits($courtBusy[$cid] ?? [], $ts, $end)) continue;

                        $clash = false;
                        foreach ($playerIds as $pid) {
                            if ($this->intervalHits($playerBusy[$pid] ?? [], $ts, $end, $restSec)) {
                                $clash = true;
                                break;
                            }
                        }
                        if ($clash) continue;

                        if ($best === null || $ts < $best[1]) $best = [$cid, $ts];
                        break 2;
                    }
                }
            }
        }

        return $best;
    }

    /**
     * Snap a timestamp UP to the next time that aligns with the slot grid
     * (a day's $anchorMinOfDay, stepping by $gridStepSec). Keeps placements on
     * the visible grid rows.
     */
    private function snapToGrid(int $ts, int $anchorMinOfDay, int $gridStepSec): int
    {
        $day = Carbon::createFromTimestamp($ts, 'America/Mexico_City')->startOfDay();
        $anchorTs = $day->timestamp + $anchorMinOfDay * 60;
        if ($ts <= $anchorTs) return $anchorTs;
        $elapsed = $ts - $anchorTs;
        $steps = (int) ceil($elapsed / $gridStepSec);
        return $anchorTs + $steps * $gridStepSec;
    }

    /**
     * Intersect a [start,end] window with a set of allowed windows, returning
     * the overlapping sub-segments (sorted by start).
     */
    private function clipToAllowed(int $start, int $end, array $allowed): array
    {
        $out = [];
        foreach ($allowed as [$aStart, $aEnd]) {
            $s = max($start, $aStart);
            $e = min($end, $aEnd);
            if ($s < $e) $out[] = [$s, $e];
        }
        usort($out, fn($a, $b) => $a[0] <=> $b[0]);
        return $out;
    }

    /**
     * True if [start,end) overlaps any interval in $list. Each interval may be
     * padded by $pad seconds on both sides (used for the per-pair rest gap).
     */
    private function intervalHits(array $list, int $start, int $end, int $pad = 0): bool
    {
        foreach ($list as [$s, $e]) {
            if ($start < ($e + $pad) && ($s - $pad) < $end) return true;
        }
        return false;
    }

    /**
     * Post-resolution conflict check: find players booked in two (or more)
     * SCHEDULED matches whose times overlap (hard conflict) or fall within the
     * rest gap (soft warning). Only considers matches with KNOWN players — the
     * cross-category collapse that placeholder scheduling can't prevent up front.
     *
     * @return array<int,array{player:string, severity:string, matches:array}>
     */
    public function detectConflicts(Tournament $tournament): array
    {
        $restSec = ((int) ($tournament->min_rest_minutes ?? 30)) * 60;

        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->whereNotNull('pair_a_id')->whereNotNull('pair_b_id')
            ->with(['category', 'group', 'court', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->get();

        // [playerId => [ ['match'=>m,'start'=>ts,'end'=>ts], ... ]]
        $byPlayer = [];
        foreach ($matches as $m) {
            $start = $m->starts_at->timestamp;
            $end = $start + (((int) ($m->duration_minutes ?: 60)) * 60);
            foreach ($m->playerIds() as $pid) {
                $byPlayer[$pid][] = ['match' => $m, 'start' => $start, 'end' => $end];
            }
        }

        $conflicts = [];
        foreach ($byPlayer as $pid => $entries) {
            if (count($entries) < 2) continue;
            usort($entries, fn($a, $b) => $a['start'] <=> $b['start']);

            for ($i = 0; $i < count($entries) - 1; $i++) {
                for ($j = $i + 1; $j < count($entries); $j++) {
                    $a = $entries[$i];
                    $b = $entries[$j];

                    $hardOverlap = $a['start'] < $b['end'] && $b['start'] < $a['end'];
                    $restViolation = ! $hardOverlap
                        && $b['start'] >= $a['end']
                        && ($b['start'] - $a['end'] < $restSec);

                    if (! $hardOverlap && ! $restViolation) continue;

                    $conflicts[] = [
                        'player' => $this->playerName($a['match'], $pid) ?? 'Jugador',
                        'severity' => $hardOverlap ? 'overlap' : 'rest',
                        'matches' => [
                            $this->matchInfo($a['match']),
                            $this->matchInfo($b['match']),
                        ],
                    ];
                }
            }
        }

        // De-dupe (same player + same two matches).
        $seen = [];
        $unique = [];
        foreach ($conflicts as $c) {
            $key = $c['player'] . '|' . implode('|', array_map(fn($x) => $x['label'] . $x['time'], $c['matches']));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $unique[] = $c;
        }

        // Hard overlaps first.
        usort($unique, fn($a, $b) => ($a['severity'] === 'overlap' ? 0 : 1) <=> ($b['severity'] === 'overlap' ? 0 : 1));

        return $unique;
    }

    /** Resolve a player's display name from a match's pairs. */
    private function playerName(GameMatch $match, int $playerId): ?string
    {
        foreach ([$match->pairA, $match->pairB] as $pair) {
            if (! $pair) continue;
            foreach ([$pair->player1, $pair->player2] as $p) {
                if ($p && $p->id === $playerId) {
                    return $p->name;
                }
            }
        }
        return null;
    }

    /** Compact match descriptor for the conflict report. */
    private function matchInfo(GameMatch $match): array
    {
        return [
            'label' => $match->contextLabel(),
            'court' => $match->court?->name,
            'time' => $match->starts_at?->timezone('America/Mexico_City')->translatedFormat('D d M · H:i'),
        ];
    }
}
