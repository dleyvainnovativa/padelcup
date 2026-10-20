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

    /** Request-scoped cache for the group-tag map. */
    private array $tagCache = [];

    /**
     * Group tags for a category: which group each pair belongs to, plus each
     * seed label's group letter. Lets the bracket show "Grupo A" next to any
     * qualifier — a plain seed label, a ghost, or a bound pair (incl. resolved
     * "Q" extra qualifiers, which don't reveal their group in the label).
     *
     * Returns:
     *   [ 'pairs'  => [ pair_id => 'A' ],
     *     'labels' => [ 'A1' => 'A', 'B2' => 'B', ... ] ]   // Q labels excluded
     */
    public function groupTagsFor(Category $category): array
    {
        return $this->tagCache[$category->id] ??= (function () use ($category) {
            $groups = $category->groups()->with('pairs:id')->orderBy('position')->orderBy('id')->get();
            $pairs = [];
            $labels = [];
            $i = 0;
            foreach ($groups as $group) {
                $letter = chr(ord('A') + $i);
                foreach ($group->pairs as $p) {
                    $pairs[$p->id] = $letter;
                }
                // Positional seed labels for this group letter (A1, A2, … up to its size).
                $size = $group->pairs->count();
                for ($pos = 1; $pos <= max($size, 1); $pos++) {
                    $labels["{$letter}{$pos}"] = $letter;
                }
                $i++;
            }
            return ['pairs' => $pairs, 'labels' => $labels];
        })();
    }

    /** Tournament-wide group tags: [category_id => ['pairs'=>, 'labels'=>]]. */
    public function groupTagsForTournament(\App\Models\Tournament $tournament): array
    {
        $out = [];
        foreach ($tournament->categories()->get() as $category) {
            $out[$category->id] = $this->groupTagsFor($category);
        }
        return $out;
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

        // Overall-seeded: project O1..ON from the LIVE overall table (updates with
        // every result). The bracket structure/schedule is fixed; only who sits at
        // each rank moves.
        if ($category->usesOverallSeeding()) {
            return $this->overallRankMap($category, $groups);
        }

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
            $pool = [];
            foreach ($byLetter as $letter => $group) {
                // Per-group winners (override or global) — must mirror
                // BracketService::qualifiers() so the ghost matches the binding.
                $adv = $category->winnersForGroupPosition(ord($letter) - ord('A'));
                $standing = $completeStandings[$letter];
                if ($standing->count() > $adv) {
                    $pool[] = $standing->get($adv); // the (N+1)-th place row
                }
            }
            if (! empty($pool)) {
                $order = \App\Support\TiebreakCriteria::sanitize($category->tournament->tiebreak_order ?? null);
                $ranked = $this->rankCrossGroup(collect($pool), $order);
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

    /**
     * Live O1..ON → pair name projection for an overall-seeded category, from the
     * CURRENT (possibly partial) standings. Mirrors how the qualifiers are chosen
     * — top N per group + boundary extras — then ranks them across groups by the
     * tournament's tiebreak order, so the preview updates with every result and
     * matches the eventual binding.
     *
     * @return array<string,string>
     */
    private function overallRankMap(Category $category, $groups): array
    {
        $order = \App\Support\TiebreakCriteria::sanitize($category->tournament->tiebreak_order ?? null);
        $extraNeeded = (int) ($category->extra_qualifiers ?? 0);

        $auto = [];  // rows (top N per group)
        $pool = [];  // boundary finisher rows (compete for the extra slots)
        $i = 0;
        foreach ($groups as $group) {
            $standing = $this->standings->forGroup($group);
            $n = $category->winnersForGroupPosition($i);
            foreach ($standing->take($n) as $row) {
                $auto[] = $row;
            }
            if ($extraNeeded > 0 && $standing->count() > $n) {
                $pool[] = $standing->get($n);
            }
            $i++;
        }

        $extras = [];
        if ($extraNeeded > 0 && ! empty($pool)) {
            $extras = array_slice($this->rankCrossGroup(collect($pool), $order), 0, $extraNeeded);
        }

        $ranked = $this->rankCrossGroup(collect(array_merge($auto, $extras)), $order);

        $out = [];
        foreach ($ranked as $k => $row) {
            $name = $this->pairName($row['pair_id']);
            if ($name !== null) {
                $out['O' . ($k + 1)] = $name;
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
