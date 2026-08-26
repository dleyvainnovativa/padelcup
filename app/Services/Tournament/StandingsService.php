<?php

namespace App\Services\Tournament;

use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Pair;
use Illuminate\Support\Collection;

/**
 * Computes standings for a group from CONFIRMED matches only.
 *
 * Tiebreaker chain (standard amateur padel):
 *   1. points (win = 2, played-loss = 1, no-show loss = 0)  [configurable below]
 *   2. head-to-head (only when exactly the relevant pairs are compared)
 *   3. set difference
 *   4. game difference
 *   5. games won
 *
 * For 3+ pairs tied on points, a mini-table is recomputed using ONLY the
 * matches among the tied pairs, then the chain is applied within that subset.
 *
 * A "standing row" is an array:
 *   pair_id, played, won, lost, points, sets_for, sets_against,
 *   games_for, games_against, set_diff, game_diff
 */
class StandingsService
{
    // Points model. Padel commonly uses 2 for a win, 1 for a loss played out,
    // 0 for a no-show. Adjustable here if a tournament uses win=3.
    private const WIN_POINTS = 2;
    private const LOSS_POINTS = 1;
    private const NOSHOW_POINTS = 0;

    /** Ordered standing rows for a group. */
    public function forGroup(Group $group): Collection
    {
        $group->loadMissing('pairs', 'category.tournament');
        $matches = GameMatch::where('group_id', $group->id)
            ->where('state', 'confirmed')
            ->get();

        // Mexicano 4-pair groups rank cumulatively: games won → sets → points.
        $isMexicano = $group->category->group_format === \App\Enums\GroupFormat::Mexicano
            && $group->pairs->count() === 4;

        // if ($isMexicano) {
        //     return $this->computeMexicano($group->pairs, $matches);
        // }

        // Per-tournament tiebreak order (falls back to the default).
        $order = \App\Support\TiebreakCriteria::sanitize(
            $group->category->tournament->tiebreak_order ?? null
        );

        return $this->compute($group->pairs, $matches, $order);
    }

    /**
     * Mexicano standings: same accumulation as round-robin, but ranked by
     * total games won → sets won → match-win points (no head-to-head mini-table,
     * since not everyone plays everyone).
     */
    public function computeMexicano(Collection $pairs, Collection $matches): Collection
    {
        $rows = [];
        foreach ($pairs as $pair) {
            $rows[$pair->id] = $this->blankRow($pair->id);
        }
        foreach ($matches as $m) {
            if (! isset($rows[$m->pair_a_id]) || ! isset($rows[$m->pair_b_id])) {
                continue;
            }
            $this->applyMatch($rows, $m);
        }

        return collect($rows)->values()->sort(function ($x, $y) {
            // 1. games won (games_for)
            if ($x['games_for'] !== $y['games_for']) return $y['games_for'] <=> $x['games_for'];
            // 2. sets won (sets_for)
            if ($x['sets_for'] !== $y['sets_for']) return $y['sets_for'] <=> $x['sets_for'];
            // 3. match-win points
            if ($x['points'] !== $y['points']) return $y['points'] <=> $x['points'];
            // stable fallback: game diff then set diff
            if ($x['game_diff'] !== $y['game_diff']) return $y['game_diff'] <=> $x['game_diff'];
            return $y['set_diff'] <=> $x['set_diff'];
        })->values();
    }

    /**
     * Compute standings for a set of pairs given their confirmed matches.
     *
     * @param  Collection<int,Pair>  $pairs
     * @param  Collection<int,GameMatch>  $matches
     */
    public function compute(Collection $pairs, Collection $matches, ?array $order = null): Collection
    {
        $rows = [];
        foreach ($pairs as $pair) {
            $rows[$pair->id] = $this->blankRow($pair->id);
        }

        foreach ($matches as $m) {
            if (! isset($rows[$m->pair_a_id]) || ! isset($rows[$m->pair_b_id])) {
                continue; // a participant not in this pair set
            }
            $this->applyMatch($rows, $m);
        }

        return $this->rank(collect($rows)->values(), $matches, \App\Support\TiebreakCriteria::sanitize($order));
    }

    private function blankRow(int $pairId): array
    {
        return [
            'pair_id' => $pairId,
            'played' => 0,
            'won' => 0,
            'lost' => 0,
            'points' => 0,
            'sets_for' => 0,
            'sets_against' => 0,
            'games_for' => 0,
            'games_against' => 0,
            'set_diff' => 0,
            'game_diff' => 0,
        ];
    }

    private function applyMatch(array &$rows, GameMatch $m): void
    {
        [$aSets, $bSets] = $m->setsWon();
        [$aGames, $bGames] = $m->gamesWon();

        $this->accumulate($rows[$m->pair_a_id], $aSets, $bSets, $aGames, $bGames);
        $this->accumulate($rows[$m->pair_b_id], $bSets, $aSets, $bGames, $aGames);

        // Win/loss + points
        $aWon = $m->winner_pair_id === $m->pair_a_id;
        $bWon = $m->winner_pair_id === $m->pair_b_id;

        $rows[$m->pair_a_id]['played']++;
        $rows[$m->pair_b_id]['played']++;

        if ($aWon) {
            $rows[$m->pair_a_id]['won']++;
            $rows[$m->pair_b_id]['lost']++;
            $rows[$m->pair_a_id]['points'] += self::WIN_POINTS;
            $rows[$m->pair_b_id]['points'] += $this->loserPoints($m, $m->pair_b_id);
        } elseif ($bWon) {
            $rows[$m->pair_b_id]['won']++;
            $rows[$m->pair_a_id]['lost']++;
            $rows[$m->pair_b_id]['points'] += self::WIN_POINTS;
            $rows[$m->pair_a_id]['points'] += $this->loserPoints($m, $m->pair_a_id);
        }
    }

    private function accumulate(array &$row, int $setsFor, int $setsAgainst, int $gamesFor, int $gamesAgainst): void
    {
        $row['sets_for'] += $setsFor;
        $row['sets_against'] += $setsAgainst;
        $row['games_for'] += $gamesFor;
        $row['games_against'] += $gamesAgainst;
        $row['set_diff'] = $row['sets_for'] - $row['sets_against'];
        $row['game_diff'] = $row['games_for'] - $row['games_against'];
    }

    /** No-show losers get 0; a played loss gets 1. */
    private function loserPoints(GameMatch $m, int $loserId): int
    {
        return $m->result_type->value === 'walkover'
            ? self::NOSHOW_POINTS
            : self::LOSS_POINTS;
    }

    /**
     * Rank rows by points, resolving ties via head-to-head (or mini-table for
     * 3+), then set diff, game diff, games won.
     */
    private function rank(Collection $rows, Collection $matches, ?array $order = null): Collection
    {
        $order = \App\Support\TiebreakCriteria::sanitize($order);

        // Walk the configured criteria in order. Each numeric criterion compares
        // a row field (descending); head_to_head uses the pairwise result (2 tied)
        // or a mini-table (3+ tied) among exactly the pairs still tied through all
        // PRIOR criteria. Comparator returns negative when $x should rank first.
        return $rows->sort(function ($x, $y) use ($matches, $rows, $order) {
            foreach ($order as $key) {
                if ($key === 'head_to_head') {
                    // Determine the cluster tied with x & y through the criteria
                    // applied BEFORE this one, so the mini-table is scoped right.
                    $tied = $this->clusterTiedBefore($rows, $x, $order, $key);

                    if ($tied->count() <= 2) {
                        $h2h = $this->headToHead($matches, $x['pair_id'], $y['pair_id']);
                        if ($h2h !== 0) return -$h2h; // +1 (x beat y) => x first
                    } else {
                        $mini = $this->miniTableRank($matches, $tied->pluck('pair_id')->all());
                        $rx = $mini[$x['pair_id']] ?? 0;
                        $ry = $mini[$y['pair_id']] ?? 0;
                        if ($rx !== $ry) return $ry <=> $rx;
                    }
                    continue;
                }

                $field = \App\Support\TiebreakCriteria::field($key);
                if ($field === null) continue; // unknown → skip defensively
                if (($x[$field] ?? 0) !== ($y[$field] ?? 0)) {
                    return ($y[$field] ?? 0) <=> ($x[$field] ?? 0); // descending
                }
            }

            // Fully tied on every configured criterion: stable by pair_id.
            return $x['pair_id'] <=> $y['pair_id'];
        })->values();
    }

    /**
     * The set of rows tied with $x on every NUMERIC criterion that appears before
     * $stopKey in the order. Used to scope the head-to-head mini-table to exactly
     * the pairs still level when H2H is reached. (Only numeric criteria before
     * H2H narrow the cluster; an earlier H2H can't be "before itself".)
     */
    private function clusterTiedBefore(Collection $rows, array $x, array $order, string $stopKey): Collection
    {
        return $rows->filter(function ($r) use ($x, $order, $stopKey) {
            foreach ($order as $key) {
                if ($key === $stopKey) break;
                if ($key === 'head_to_head') continue; // pairwise, not a cluster narrower
                $field = \App\Support\TiebreakCriteria::field($key);
                if ($field === null) continue;
                if (($r[$field] ?? 0) !== ($x[$field] ?? 0)) return false;
            }
            return true;
        })->values();
    }

    /** +1 if A beat B head-to-head, -1 if B beat A, 0 if no/split result. */
    private function headToHead(Collection $matches, int $aId, int $bId): int
    {
        $aWins = $bWins = 0;
        foreach ($matches as $m) {
            $isPair = ($m->pair_a_id === $aId && $m->pair_b_id === $bId)
                || ($m->pair_a_id === $bId && $m->pair_b_id === $aId);
            if (! $isPair) continue;
            if ($m->winner_pair_id === $aId) $aWins++;
            elseif ($m->winner_pair_id === $bId) $bWins++;
        }
        return $aWins <=> $bWins; // returns -1/0/1 ; caller wants A-first => invert
    }

    /**
     * Mini-table points among tied pairs, using only matches between them.
     * Returns [pair_id => points].
     */
    private function miniTableRank(Collection $matches, array $tiedIds): array
    {
        $pts = array_fill_keys($tiedIds, 0);
        foreach ($matches as $m) {
            if (! in_array($m->pair_a_id, $tiedIds, true) || ! in_array($m->pair_b_id, $tiedIds, true)) {
                continue;
            }
            if ($m->winner_pair_id === $m->pair_a_id) $pts[$m->pair_a_id] += self::WIN_POINTS;
            elseif ($m->winner_pair_id === $m->pair_b_id) $pts[$m->pair_b_id] += self::WIN_POINTS;
        }
        return $pts;
    }
}
