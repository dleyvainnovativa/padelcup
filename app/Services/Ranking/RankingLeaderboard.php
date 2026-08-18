<?php

namespace App\Services\Ranking;

use App\Models\Player;
use App\Models\RankingPoint;
use App\Models\RankingSystem;
use Illuminate\Support\Collection;

/**
 * Phase 4 — reads the ranking_points ledger and produces a leaderboard for a
 * ranking system.
 *
 * THE KEY WRINKLE: the same human has a SEPARATE Player row per category (dedup
 * only happens on email/phone), so summing raw player_id would list one person
 * many times. We aggregate by the human identity the rest of the app uses:
 * (normalized_name, created_by) — mirroring PublicTournamentController::player().
 *
 * A leaderboard row:
 *   [
 *     'rank'        => int,     // 1-based, ties share a rank
 *     'name'        => string,  // display name (a representative Player->name)
 *     'points'      => int,     // total across all counted tournaments
 *     'player_ids'  => int[],   // every Player row that is this human
 *     'tournaments' => int,     // distinct tournaments they scored in
 *     'breakdown'   => [ achievement => points ]  // optional detail
 *   ]
 */
class RankingLeaderboard
{
    /**
     * Full leaderboard for a system, ranked desc by points.
     *
     * @param  bool  $withBreakdown  include per-achievement point breakdown
     */
    public function forSystem(RankingSystem $system, bool $withBreakdown = false): Collection
    {
        // Pull the ledger with the minimum columns we need, plus the player's
        // normalized identity for grouping. Join players for normalized_name +
        // created_by + a display name.
        $rows = RankingPoint::query()
            ->where('ranking_points.ranking_system_id', $system->id)
            ->join('players', 'players.id', '=', 'ranking_points.player_id')
            ->selectRaw('
                players.normalized_name as norm,
                players.created_by as owner,
                ranking_points.player_id,
                ranking_points.tournament_id,
                ranking_points.achievement,
                ranking_points.points
            ')
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        // Group by the human: (normalized_name, created_by).
        $humans = $rows->groupBy(fn ($r) => $r->norm . '|' . ($r->owner ?? ''));

        // Resolve a display name per human (first Player row we can find).
        $displayNames = $this->displayNamesFor($humans);

        $board = $humans->map(function ($group, $key) use ($withBreakdown, $displayNames) {
            $points     = (int) $group->sum('points');
            $playerIds  = $group->pluck('player_id')->unique()->values()->all();
            $tourneys   = $group->pluck('tournament_id')->unique()->count();

            $row = [
                'name'        => $displayNames[$key] ?? '—',
                'points'      => $points,
                'player_ids'  => $playerIds,
                'tournaments' => $tourneys,
            ];

            if ($withBreakdown) {
                $row['breakdown'] = $group
                    ->groupBy('achievement')
                    ->map(fn ($g) => (int) $g->sum('points'))
                    ->sortDesc()
                    ->all();
            }

            return $row;
        })->values();

        // Rank desc by points; ties share a rank (standard competition ranking).
        $board = $board->sortByDesc('points')->values();

        $rank = 0; $seen = 0; $prev = null;
        $board = $board->map(function ($row) use (&$rank, &$seen, &$prev) {
            $seen++;
            if ($prev === null || $row['points'] < $prev) {
                $rank = $seen;         // new (lower) score → rank jumps to position
            }
            $prev = $row['points'];
            $row['rank'] = $rank;
            return $row;
        });

        return $board;
    }

    /**
     * Resolve a display name per human key. Uses the first Player row of each
     * group; falls back to a lookup if needed.
     *
     * @param  Collection  $humans  keyed by "norm|owner"
     * @return array<string,string> [ key => display name ]
     */
    private function displayNamesFor(Collection $humans): array
    {
        // Collect one player_id per human to fetch a representative name.
        $repIds = $humans->map(fn ($g) => $g->first()->player_id)->all();

        $names = Player::whereIn('id', array_values($repIds))
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($humans as $key => $group) {
            $pid = $group->first()->player_id;
            $player = $names->get($pid);
            $out[$key] = $player ? $player->name : '—';
        }
        return $out;
    }
}
