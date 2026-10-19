<?php

namespace App\Services\Tournament;

use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Mesa de control payload: the event-day board of scheduled matches with their
 * control status (pendiente/llamado/jugando/terminado), per-player payment state
 * and a tournament-wide running delay.
 *
 * Reads only scheduled matches (those with a time). Everything the view needs is
 * eager-loaded here (pairs, players, registration.payments) so the board and
 * Pagos checkboxes don't N+1.
 */
class ControlTableService
{
    /**
     * Median late-start delay in minutes across matches that are in progress
     * (called or playing) but not finished. Null when nothing is running late.
     * Used by the mesa header and the public delay notice.
     */
    public function delay(Tournament $tournament): ?int
    {
        $delays = $this->scheduledMatches($tournament)
            ->filter(fn (GameMatch $m) => in_array($m->controlStatus(), ['called', 'playing'], true))
            ->map(fn (GameMatch $m) => $m->delayMinutes())
            ->filter(fn ($d) => $d !== null && $d > 0)
            ->values();

        if ($delays->isEmpty()) {
            return null;
        }

        return (int) round($this->median($delays->all()));
    }

    /**
     * Full mesa payload: scheduled matches grouped by day, status counts, and the
     * running delay. The view renders each match via its model helpers.
     *
     * @return array{byDay: \Illuminate\Support\Collection, counts: array<string,int>, delay: ?int, days: \Illuminate\Support\Collection}
     */
    public function build(Tournament $tournament): array
    {
        $tz = 'America/Mexico_City';

        $matches = $this->scheduledMatches($tournament)
            ->sortBy(fn (GameMatch $m) => $m->starts_at->timestamp)
            ->values();

        $byDay = $matches->groupBy(
            fn (GameMatch $m) => $m->starts_at->timezone($tz)->format('Y-m-d')
        );

        $counts = ['pending' => 0, 'called' => 0, 'playing' => 0, 'finished' => 0];
        foreach ($matches as $m) {
            $counts[$m->controlStatus()] = ($counts[$m->controlStatus()] ?? 0) + 1;
        }

        return [
            'byDay' => $byDay,
            'counts' => $counts,
            'delay' => $this->delay($tournament),
            'days' => $tournament->playDays(),
        ];
    }

    /** Scheduled matches for the tournament with everything the mesa needs. */
    private function scheduledMatches(Tournament $tournament): Collection
    {
        return GameMatch::whereHas('category', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->with([
                'category',
                'court',
                'pairA.player1', 'pairA.player2', 'pairA.registration.payments',
                'pairB.player1', 'pairB.player2', 'pairB.registration.payments',
            ])
            ->get();
    }

    private function median(array $nums): float
    {
        sort($nums);
        $n = count($nums);
        if ($n === 0) {
            return 0.0;
        }
        $mid = intdiv($n, 2);

        return $n % 2
            ? (float) $nums[$mid]
            : ($nums[$mid - 1] + $nums[$mid]) / 2;
    }
}
