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
     * @param  Collection<int,array>  $rows
     * @return array<int,array>
     */
    protected function rankCrossGroup(Collection $rows): array
    {
        return $rows->sort(function ($x, $y) {
            if ($x['points'] !== $y['points'])       return $y['points'] <=> $x['points'];
            if ($x['set_diff'] !== $y['set_diff'])   return $y['set_diff'] <=> $x['set_diff'];
            if ($x['game_diff'] !== $y['game_diff']) return $y['game_diff'] <=> $x['game_diff'];
            return $y['games_for'] <=> $x['games_for'];
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
