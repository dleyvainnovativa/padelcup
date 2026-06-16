<?php

namespace App\Services\Tournament;

use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Pair;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generates groups for a category and the round-robin matches inside them.
 *
 *  - Preferred group size 3 or 4; the engine flexes to absorb remainders,
 *    reaching for 5 only when forced (e.g. 5 pairs total).
 *  - Shared-player separation: two pairs that share a player are placed in
 *    DIFFERENT groups where possible (they can't meaningfully play each other).
 *    If unsatisfiable, returns a warning.
 *  - preview() computes the distribution without persisting; generate()
 *    commits groups + matches (only allowed pre-lock).
 */
class GroupGenerationService
{
    /**
     * Compute a group-size distribution for n pairs and a preferred size.
     * Returns an array of group sizes, e.g. [4,4,3] for 11 pairs preferring 4.
     *
     * Strategy: start with ceil(n / preferred) groups, then balance sizes so
     * they differ by at most 1, avoiding any group of 1 or 2 (merge up). Falls
     * back to a single group of 5 only when n is too small to split cleanly.
     */
    public function distribution(int $n, int $preferred): array
    {
        if ($n <= 0) return [];
        if ($n <= 5 && $n < 2 * $preferred) {
            // Too small to split into 2 valid groups → single group.
            return [$n];
        }

        // Round UP the group count so an uneven split leans to SMALLER groups
        // (e.g. 3s) rather than oversized ones (5s). With preferred 4 and 9
        // pairs this gives [3,3,3], not [5,4].
        $groups = max(1, (int) ceil($n / $preferred));
        // Ensure no group would be smaller than 3 (pull groups down if needed).
        while ($groups > 1 && ($n / $groups) < 3) {
            $groups--;
        }

        // Balance: base size + distribute remainder one-by-one.
        $base = intdiv($n, $groups);
        $remainder = $n % $groups;

        $sizes = array_fill(0, $groups, $base);
        for ($i = 0; $i < $remainder; $i++) {
            $sizes[$i]++;
        }

        rsort($sizes); // larger groups first (A bigger than B, etc.)
        return $sizes;
    }

    /**
     * Preview the assignment of pairs to groups (no DB writes).
     *
     * @param  Collection<int,Pair>  $pairs
     * @return array{sizes: array, groups: array<int, array>, match_count: int, warnings: array}
     */
    public function preview(Category $category, Collection $pairs): array
    {
        $n = $pairs->count();
        $sizes = $this->distribution($n, $category->preferred_group_size ?: 4);
        $assignment = $this->assign($pairs, $sizes);

        $isMexicano = $category->group_format === \App\Enums\GroupFormat::Mexicano;

        $matchCount = collect($assignment['groups'])
            ->sum(function ($g) use ($isMexicano) {
                $size = count($g);
                // Mexicano 4-pair groups play 2 rounds = 4 matches; all other
                // groups (and non-Mexicano) play round-robin.
                if ($isMexicano && $size === 4) {
                    return 4;
                }
                return $this->roundRobinMatchCount($size);
            });

        return [
            'sizes' => $sizes,
            'groups' => $assignment['groups'],   // array of arrays of Pair
            'match_count' => $matchCount,
            'warnings' => $assignment['warnings'],
        ];
    }

    /**
     * Assign pairs to groups of the given sizes, separating pairs that share a
     * player. Greedy: place each pair into the emptiest group that doesn't
     * already contain a pair sharing one of its players.
     *
     * @return array{groups: array<int, array<int,Pair>>, warnings: array}
     */
    private function assign(Collection $pairs, array $sizes): array
    {
        $groups = array_map(fn() => [], $sizes);
        $warnings = [];

        // Order pairs so the most "constrained" (sharing players) are placed
        // first — those with shared players are harder to fit.
        $ordered = $this->orderByConstraint($pairs);

        foreach ($ordered as $pair) {
            $placed = false;

            // Candidate groups sorted by remaining capacity (emptiest first).
            $candidates = collect(range(0, count($sizes) - 1))
                ->filter(fn($i) => count($groups[$i]) < $sizes[$i])
                ->sortBy(fn($i) => count($groups[$i]))
                ->values();

            foreach ($candidates as $i) {
                if (! $this->groupHasSharedPlayer($groups[$i], $pair)) {
                    $groups[$i][] = $pair;
                    $placed = true;
                    break;
                }
            }

            if (! $placed) {
                // Couldn't satisfy separation — place in emptiest available and warn.
                $i = $candidates->first();
                if ($i !== null) {
                    $groups[$i][] = $pair;
                    $warnings[] = "No se pudo separar a «{$pair->name()}» de una pareja con jugador en común; revisa el grupo manualmente.";
                } else {
                    $warnings[] = "No hubo cupo para «{$pair->name()}».";
                }
            }
        }

        return ['groups' => $groups, 'warnings' => $warnings];
    }

    /** Pairs sharing a player with others first (harder to place). */
    private function orderByConstraint(Collection $pairs): Collection
    {
        $playerCounts = [];
        foreach ($pairs as $pair) {
            foreach ($pair->playerIds() as $pid) {
                $playerCounts[$pid] = ($playerCounts[$pid] ?? 0) + 1;
            }
        }

        return $pairs->sortByDesc(function (Pair $pair) use ($playerCounts) {
            return collect($pair->playerIds())->max(fn($pid) => $playerCounts[$pid] ?? 0);
        })->values();
    }

    /** Does any pair already in $group share a player with $pair? */
    private function groupHasSharedPlayer(array $group, Pair $pair): bool
    {
        foreach ($group as $existing) {
            if ($existing->sharesPlayerWith($pair)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Move a pair between groups, OR to/from the unassigned pool. A null group
     * represents the pool: pool→group attaches + rebuilds the destination;
     * group→pool detaches + rebuilds the source; group→group does both. Only
     * the affected groups' matches are rebuilt — nothing else is touched.
     * Pre-lock only (caller enforces the lock).
     *
     * A pair in the pool has NO matches (it isn't playing until placed).
     *
     * @return array{warning: ?string}
     */
    public function movePair(Pair $pair, ?Group $from, ?Group $to): array
    {
        if ($from && $to && $from->id === $to->id) {
            return ['warning' => null];
        }

        return DB::transaction(function () use ($pair, $from, $to) {
            $warning = null;

            // Shared-player check against destination group (warn, don't block).
            if ($to) {
                $to->loadMissing('pairs');
                foreach ($to->pairs as $existing) {
                    if ($existing->id !== $pair->id && $existing->sharesPlayerWith($pair)) {
                        $warning = "«{$pair->name()}» comparte un jugador con «{$existing->name()}» en el grupo destino.";
                        break;
                    }
                }
            }

            // Detach from source group (if coming from a real group).
            if ($from) {
                $from->pairs()->detach($pair->id);
                $this->rebuildGroupMatches($from->fresh('pairs'));
            }

            // Attach to destination group (if going to a real group).
            if ($to) {
                $to->pairs()->syncWithoutDetaching([$pair->id]);
                $this->rebuildGroupMatches($to->fresh('pairs'));
            }

            // group→pool: detaching from the source above already removed the
            // pair's matches (rebuild excludes it). Nothing more to do — a
            // pooled pair simply has no matches until placed in a group.

            return ['warning' => $warning];
        });
    }

    /**
     * Confirmed pairs in the category that are not yet in any group (late
     * registrations land here automatically). These form the "Sin asignar" pool.
     *
     * @return \Illuminate\Support\Collection<int,Pair>
     */
    public function unassignedPairs(Category $category): Collection
    {
        $groupedPairIds = DB::table('group_pair')
            ->join('groups', 'groups.id', '=', 'group_pair.group_id')
            ->where('groups.category_id', $category->id)
            ->pluck('group_pair.pair_id')
            ->all();

        return $category->poolPairs()
            ->with(['player1', 'player2'])
            ->whereNotIn('pairs.id', $groupedPairIds)
            ->get();
    }

    /** Number of round-robin matches for a group of the given size: n(n-1)/2. */
    private function roundRobinMatchCount(int $size): int
    {
        return $size < 2 ? 0 : intdiv($size * ($size - 1), 2);
    }

    /** Delete and recreate the round-robin matches for a single group. */
    private function rebuildGroupMatches(Group $group): void
    {
        GameMatch::where('group_id', $group->id)->delete();

        $pairIds = $group->pairs->pluck('id')->values()->all();
        $category = $group->category;

        // Mexicano only applies to 4-pair groups; everything else round-robin.
        $isMexicano = $category->group_format === \App\Enums\GroupFormat::Mexicano
            && count($pairIds) === 4;

        if ($isMexicano) {
            $this->buildMexicanoMatches($group, $pairIds, $category);
            return;
        }

        // Round-robin: everyone plays everyone.
        for ($i = 0; $i < count($pairIds); $i++) {
            for ($j = $i + 1; $j < count($pairIds); $j++) {
                GameMatch::create([
                    'category_id' => $group->category_id,
                    'group_id' => $group->id,
                    'pair_a_id' => $pairIds[$i],
                    'pair_b_id' => $pairIds[$j],
                ]);
            }
        }
    }

    /**
     * Mexicano 4-pair group (2 rounds):
     *   R1: A = P1 vs P2, B = P3 vs P4   (fixed)
     *   R2 cross  : W(A) vs L(B), W(B) vs L(A)
     *   R2 classic: W(A) vs W(B), L(A) vs L(B)
     * Round-2 participants resolve from R1 results via feeder source winner/loser.
     * Final ranking is cumulative (StandingsService handles Mexicano scoring).
     */
    private function buildMexicanoMatches(Group $group, array $pairIds, Category $category): void
    {
        [$p1, $p2, $p3, $p4] = $pairIds;

        // Round 1 (fixed participants).
        $a = GameMatch::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'pair_a_id' => $p1,
            'pair_b_id' => $p2,
            'round' => 1,
            'slot' => 1,
        ]);
        $b = GameMatch::create([
            'category_id' => $category->id,
            'group_id' => $group->id,
            'pair_a_id' => $p3,
            'pair_b_id' => $p4,
            'round' => 1,
            'slot' => 2,
        ]);

        $cross = ($category->mexicano_pairing ?? \App\Enums\MexicanoPairing::Cross)
            === \App\Enums\MexicanoPairing::Cross;

        if ($cross) {
            // R2: W(A) vs L(B), W(B) vs L(A)
            GameMatch::create([
                'category_id' => $category->id,
                'group_id' => $group->id,
                'round' => 2,
                'slot' => 1,
                'feeder_a_id' => $a->id,
                'feeder_a_source' => 'winner',
                'feeder_b_id' => $b->id,
                'feeder_b_source' => 'loser',
            ]);
            GameMatch::create([
                'category_id' => $category->id,
                'group_id' => $group->id,
                'round' => 2,
                'slot' => 2,
                'feeder_a_id' => $b->id,
                'feeder_a_source' => 'winner',
                'feeder_b_id' => $a->id,
                'feeder_b_source' => 'loser',
            ]);
        } else {
            // R2 classic: W(A) vs W(B), L(A) vs L(B)
            GameMatch::create([
                'category_id' => $category->id,
                'group_id' => $group->id,
                'round' => 2,
                'slot' => 1,
                'feeder_a_id' => $a->id,
                'feeder_a_source' => 'winner',
                'feeder_b_id' => $b->id,
                'feeder_b_source' => 'winner',
            ]);
            GameMatch::create([
                'category_id' => $category->id,
                'group_id' => $group->id,
                'round' => 2,
                'slot' => 2,
                'feeder_a_id' => $a->id,
                'feeder_a_source' => 'loser',
                'feeder_b_id' => $b->id,
                'feeder_b_source' => 'loser',
            ]);
        }
    }

    /**
     * Persist the groups + round-robin matches for a category. Wipes any
     * existing groups/matches first (pre-lock regeneration only — caller must
     * enforce the lock).
     *
     * @param  Collection<int,Pair>  $pairs
     */
    public function generate(Category $category, Collection $pairs): array
    {
        $preview = $this->preview($category, $pairs);

        DB::transaction(function () use ($category, $preview) {
            // Clear existing group structure for this category.
            GameMatch::where('category_id', $category->id)
                ->whereNotNull('group_id')->delete();
            Group::where('category_id', $category->id)->delete();

            foreach ($preview['groups'] as $index => $groupPairs) {
                if (empty($groupPairs)) continue;

                $group = Group::create([
                    'category_id' => $category->id,
                    'name' => 'Grupo ' . chr(65 + $index), // A, B, C...
                    'position' => $index,
                ]);

                $pairIds = collect($groupPairs)->pluck('id')->all();
                $group->pairs()->sync($pairIds);

                // Build matches via the shared builder so the Mexicano vs
                // round-robin branch is applied consistently (don't duplicate
                // the round-robin loop here — that ignored the format).
                $this->rebuildGroupMatches($group->fresh('pairs'));
            }
        });

        return $preview;
    }
}
