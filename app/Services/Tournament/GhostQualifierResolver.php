<?php

namespace App\Services\Tournament;

use App\Models\Category;
use App\Models\Group;
use App\Models\Pair;

/**
 * DISPLAY-ONLY resolver that maps a positional seed label ("A1", "B2", "Q1")
 * to the pair that currently occupies it — but ONLY when that position is
 * already decided (its group is complete). Returns null otherwise.
 *
 * This does NOT bind anything, persist anything, or advance the bracket. It's a
 * pure read used to show "ghost" qualifier names in the bracket the moment a
 * single group finishes, without waiting for the whole category. The real
 * binding (BracketService::bindQualifiers) is untouched and still runs when the
 * category completes.
 *
 * Label grammar (from BracketService::positionalSeedLabels):
 *   "A1" = group at position index 0 ("A"), standings place 1
 *   "B2" = group at position index 1 ("B"), place 2
 *   "Q1" = 1st extra qualifier (best boundary finisher ACROSS groups) — only
 *          resolvable once EVERY group is complete, since it's a cross-group rank.
 */
class GhostQualifierResolver
{
    use \App\Services\Tournament\Concerns\RanksCrossGroupQualifiers;

    public function __construct(private StandingsService $standings) {}

    /** Request-scoped cache: [category_id => map]. Tournament-wide views (public
     *  calendar, dashboard board) resolve many matches across the same handful of
     *  categories; without this, each match would recompute its category's
     *  standings. Cleared naturally at end of request. */
    private array $cache = [];

    /**
     * Cached mapFor(): builds the category's ghost map once per request.
     * Prefer this in any view that renders many matches.
     */
    public function mapForCached(Category $category): array
    {
        return $this->cache[$category->id] ??= $this->mapFor($category);
    }

    /**
     * Ghost maps for EVERY hybrid category in a tournament, keyed by category id:
     *   [ category_id => [ seedLabel => pairName ] ]
     * For tournament-wide views (public calendar, dashboard schedule). Only
     * categories that have a bracket are included; others map to an empty array.
     */
    public function mapForTournament(\App\Models\Tournament $tournament): array
    {
        $categories = $tournament->categories()->get();
        $out = [];
        foreach ($categories as $category) {
            $out[$category->id] = $this->mapForCached($category);
        }
        return $out;
    }

    /**
     * Resolve a category's seed labels to pair names where decided.
     * Returns [ seedLabel => pairName ] for every label that's currently known.
     * Building this once per category is cheaper than resolving label-by-label.
     *
     * @return array<string,string>
     */
    public function mapFor(Category $category): array
    {
        $groups = $category->groups()->with(['pairs.player1', 'pairs.player2'])->orderBy('position')->orderBy('id')->get();
        if ($groups->isEmpty()) return [];

        // Letter (A, B, C…) → group, in the same order BracketService lettered them.
        $byLetter = [];
        $completeStandings = []; // letter => standings collection (only if complete)
        $allComplete = true;

        $i = 0;
        foreach ($groups as $group) {
            $letter = chr(ord('A') + $i);
            $byLetter[$letter] = $group;

            if ($this->groupComplete($group)) {
                $completeStandings[$letter] = $this->standings->forGroup($group);
            } else {
                $allComplete = false;
            }
            $i++;
        }

        $out = [];

        // Direct group positions: A1, A2, B1, …
        foreach ($completeStandings as $letter => $standing) {
            foreach ($standing as $idx => $row) {
                $pos = $idx + 1; // 1-indexed place
                $pair = $this->pairName($row['pair_id']);
                if ($pair !== null) {
                    $out["{$letter}{$pos}"] = $pair;
                }
            }
        }

        // Extra qualifiers (Q1, Q2…) are a CROSS-group rank of boundary finishers —
        // only meaningful once every group is complete. Reuse the same ranking the
        // real binder uses so the ghost matches the eventual binding.
        $extra = (int) ($category->extra_qualifiers ?? 0);
        if ($extra > 0 && $allComplete) {
            $adv = (int) $category->advance_per_group;
            $pool = [];
            foreach ($byLetter as $letter => $group) {
                $standing = $completeStandings[$letter];
                if ($standing->count() > $adv) {
                    $pool[] = $standing->get($adv); // the (N+1)-th place row
                }
            }
            if (! empty($pool)) {
                $ranked = $this->rankCrossGroup(collect($pool));
                $k = 1;
                foreach (collect($ranked)->take($extra) as $row) {
                    $name = $this->pairName($row['pair_id']);
                    if ($name !== null) $out["Q{$k}"] = $name;
                    $k++;
                }
            }
        }

        return $out;
    }

    /** A group is "complete" when it has matches and all are confirmed. */
    private function groupComplete(Group $group): bool
    {
        $total = \App\Models\GameMatch::where('group_id', $group->id)->count();
        if ($total === 0) return false;
        $confirmed = \App\Models\GameMatch::where('group_id', $group->id)
            ->where('state', 'confirmed')->count();
        return $confirmed === $total;
    }

    private function pairName(int $pairId): ?string
    {
        $pair = Pair::with(['player1', 'player2'])->find($pairId);
        return $pair?->name();
    }
}
