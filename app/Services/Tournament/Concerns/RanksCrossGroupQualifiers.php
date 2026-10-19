<?php

namespace App\Services\Tournament\Concerns;

use Illuminate\Support\Collection;

/**
 * Single source of truth for how boundary/extra qualifiers are ranked ACROSS
 * groups (they never played each other, so head-to-head doesn't apply).
 *
 * Both BracketService (which BINDS the real qualifiers) and
 * GhostQualifierResolver (which PROJECTS them for display before binding) use
 * this, so the projected qualifier can never disagree with the eventual bound
 * qualifier. Change the tiebreaker order here and both stay in lockstep.
 *
 * Order: points → set diff → game diff → games won.
 *
 * Row shape (from StandingsService): each row has at least
 *   pair_id, points, set_diff, game_diff, games_for
 */
trait RanksCrossGroupQualifiers
{
    /**
     * Rank rows across groups, best first. Returns a plain (0-indexed) array.
     *
     * When $order is null the legacy fixed chain is used (points → set diff →
     * game diff → games won), so existing callers behave exactly as before.
     * When a tournament's tiebreak order is passed, the numeric criteria are
     * applied in that order (head-to-head is skipped — it can't apply to pairs
     * from different groups), then the legacy chain + pair_id break any remaining
     * ties for a stable result. This keeps the general board, the ghost
     * projection and the real binder in lockstep with the configured criteria.
     *
     * @param  Collection<int,array>  $rows
     * @param  array<int,string>|null  $order  tiebreak criteria keys
     * @return array<int,array>
     */
    protected function rankCrossGroup(Collection $rows, ?array $order = null): array
    {
        if ($order === null) {
            return $rows->sort(function ($x, $y) {
                if ($x['points'] !== $y['points'])       return $y['points'] <=> $x['points'];
                if ($x['set_diff'] !== $y['set_diff'])   return $y['set_diff'] <=> $x['set_diff'];
                if ($x['game_diff'] !== $y['game_diff']) return $y['game_diff'] <=> $x['game_diff'];
                return $y['games_for'] <=> $x['games_for'];
            })->values()->all();
        }

        $keys = \App\Support\TiebreakCriteria::sanitize($order);

        return $rows->sort(function ($x, $y) use ($keys) {
            foreach ($keys as $k) {
                $field = \App\Support\TiebreakCriteria::field($k);
                if ($field === null) continue; // head_to_head: not valid across groups
                $xv = $x[$field] ?? 0;
                $yv = $y[$field] ?? 0;
                if ($xv !== $yv) return $yv <=> $xv; // descending
            }
            // Stable fallback: the legacy automatic chain, then pair_id.
            if (($x['points'] ?? 0) !== ($y['points'] ?? 0))     return ($y['points'] ?? 0) <=> ($x['points'] ?? 0);
            if (($x['set_diff'] ?? 0) !== ($y['set_diff'] ?? 0)) return ($y['set_diff'] ?? 0) <=> ($x['set_diff'] ?? 0);
            if (($x['game_diff'] ?? 0) !== ($y['game_diff'] ?? 0)) return ($y['game_diff'] ?? 0) <=> ($x['game_diff'] ?? 0);
            return ($x['pair_id'] ?? 0) <=> ($y['pair_id'] ?? 0);
        })->values()->all();
    }

    /** True when two standing rows tie on every automatic cross-group criterion. */
    protected function crossGroupRowsTied(array $a, array $b): bool
    {
        return $a['points'] === $b['points']
            && $a['set_diff'] === $b['set_diff']
            && $a['game_diff'] === $b['game_diff']
            && $a['games_for'] === $b['games_for'];
    }
}
