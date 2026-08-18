<?php

namespace App\Services\Tournament;

use App\Enums\CategoryFormat;
use App\Enums\MatchState;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Pair;
use Illuminate\Support\Facades\DB;

/**
 * Bracket logic for elimination and hybrid (groups → knockout) categories.
 *
 * Qualification (hybrid):
 *   - top `advance_per_group` from each group auto-qualify
 *   - `extra_qualifiers` best finishers from the next place down, ranked ACROSS
 *     groups, fill the remaining slots
 *   - if a cross-group tie on the boundary can't be broken, we surface it for
 *     manual resolution rather than picking arbitrarily
 *
 * Bracket:
 *   - size = next power of 2 ≥ qualifiers; top seeds get byes
 *   - standard seeding pairs 1-vs-last, etc.
 *   - confirming a match auto-advances the winner into its parent feeder slot
 */
class BracketService
{
    use \App\Services\Tournament\Concerns\RanksCrossGroupQualifiers;

    public function __construct(private StandingsService $standings) {}

    // --- Qualification (hybrid) ---------------------------------------

    /**
     * Determine the qualifiers for a hybrid category.
     *
     * @return array{
     *   qualifiers: array<int, int>,   // ordered pair ids (seeds)
     *   tie: null|array,               // unresolved boundary tie, if any
     * }
     */
    public function qualifiers(Category $category): array
    {
        $groups = $category->groups()->with('pairs')->orderBy('position')->get();
        $auto = [];
        $pool = []; // boundary finishers competing for extra slots

        foreach ($groups as $group) {
            $standing = $this->standings->forGroup($group);
            $n = $category->advance_per_group;

            // Auto qualifiers: top N.
            foreach ($standing->take($n) as $row) {
                $auto[] = $row['pair_id'];
            }
            // Boundary finisher: the (N+1)-th place → extra-qualifier pool.
            if ($category->extra_qualifiers > 0 && $standing->count() > $n) {
                $pool[] = $standing->get($n); // the row just below the line
            }
        }

        $extras = [];
        $tie = null;

        if ($category->extra_qualifiers > 0 && ! empty($pool)) {
            $rankedPool = $this->rankCrossGroup(collect($pool));
            $need = $category->extra_qualifiers;

            // Detect an unbreakable tie on the boundary (more tied than slots).
            $tie = $this->boundaryTie($rankedPool, $need);

            if (! $tie) {
                $extras = collect($rankedPool)->take($need)->pluck('pair_id')->all();
            }
        }

        return [
            'qualifiers' => array_merge($auto, $extras),
            'tie' => $tie,
        ];
    }

    /**
     * If the pairs straddling the cut (positions `need-1` and `need`) are tied
     * on every automatic criterion, return the tied set for manual resolution.
     */
    private function boundaryTie(array $ranked, int $need): ?array
    {
        if ($need <= 0 || $need >= count($ranked)) return null;

        $last = $ranked[$need - 1];   // last auto-in
        $first = $ranked[$need];      // first out

        if (! $this->crossGroupRowsTied($last, $first)) {
            return null;
        }

        // Collect everyone tied with the boundary on the automatic criteria.
        $tiedRows = array_values(array_filter($ranked, fn($r) => $this->crossGroupRowsTied($r, $last)));

        return [
            'pairs' => collect($tiedRows)->pluck('pair_id')->all(),
            'slots' => $need - $this->countStrictlyAbove($ranked, $last),
            'rows' => $tiedRows,
        ];
    }

    private function countStrictlyAbove(array $ranked, array $ref): int
    {
        return count(array_filter($ranked, fn($r) => ! $this->crossGroupRowsTied($r, $ref)
            && ($r['points'] > $ref['points']
                || ($r['points'] === $ref['points'] && $r['set_diff'] > $ref['set_diff'])
                || ($r['points'] === $ref['points'] && $r['set_diff'] === $ref['set_diff'] && $r['game_diff'] > $ref['game_diff']))));
    }



    // --- Bracket construction -----------------------------------------

    /**
     * Build the elimination bracket from an ordered list of qualifier pair ids
     * (index 0 = top seed). Persists matches with feeder links. Wipes existing
     * bracket matches first (pre-lock only).
     */
    public function build(Category $category, array $seedPairIds): void
    {
        $count = count($seedPairIds);
        if ($count < 2) {
            throw new \RuntimeException('Se necesitan al menos 2 parejas para la llave.');
        }

        $size = $this->nextPowerOfTwo($count);
        $seedOrder = $this->seedPositions($size); // 1-indexed seed at each slot

        DB::transaction(function () use ($category, $seedPairIds, $size, $seedOrder) {
            // Clear existing bracket (group_id null = bracket matches).
            GameMatch::where('category_id', $category->id)
                ->whereNull('group_id')->delete();

            $rounds = (int) log($size, 2);

            // Build round 1 with byes: a seeded pair vs the slot's opponent.
            // seedOrder gives the seed number (1..size) occupying each position.
            $firstRound = [];
            for ($slot = 0; $slot < $size / 2; $slot++) {
                $seedTop = $seedOrder[$slot * 2] - 1;       // 0-indexed seed
                $seedBot = $seedOrder[$slot * 2 + 1] - 1;

                $pairTop = $seedPairIds[$seedTop] ?? null;  // null = bye
                $pairBot = $seedPairIds[$seedBot] ?? null;

                $match = GameMatch::create([
                    'category_id' => $category->id,
                    'round' => 1,
                    'slot' => $slot,
                    'pair_a_id' => $pairTop,
                    'pair_b_id' => $pairBot,
                ]);

                // A bye: one side empty → the present pair auto-advances.
                if ($pairTop && ! $pairBot) {
                    $this->autoWin($match, $pairTop);
                } elseif ($pairBot && ! $pairTop) {
                    $this->autoWin($match, $pairBot);
                }

                $firstRound[] = $match;
            }

            // Build subsequent rounds, linking feeders.
            $prev = $firstRound;
            for ($round = 2; $round <= $rounds; $round++) {
                $current = [];
                for ($slot = 0; $slot < count($prev) / 2; $slot++) {
                    $fa = $prev[$slot * 2];
                    $fb = $prev[$slot * 2 + 1];

                    $match = GameMatch::create([
                        'category_id' => $category->id,
                        'round' => $round,
                        'slot' => $slot,
                        'feeder_a_id' => $fa->id,
                        'feeder_b_id' => $fb->id,
                    ]);
                    $current[] = $match;
                }
                $prev = $current;
            }

            // Propagate any bye winners already known into round 2 feeders.
            foreach ($firstRound as $m) {
                if ($m->winner_pair_id) {
                    $this->advanceWinner($m->fresh());
                }
            }

            // Optional third-place match (between the two semi-final losers) is
            // created lazily when semis confirm (handled in advanceWinner).
        });
    }

    // --- Positional bracket (labels before standings are final) -------

    /**
     * Build a bracket using POSITIONAL seed labels (A1, B2, …) for a hybrid
     * category, WITHOUT requiring final standings. The first round pairs each
     * group winner against a different group's runner-up (classic cross rule);
     * pairs bind later via bindQualifiers() once groups complete.
     *
     * Supports the common configs (advance 1 or 2 per group). Returns the count
     * of first-round matches built.
     */
    public function buildPositional(Category $category): void
    {
        $groups = $category->groups()->orderBy('position')->orderBy('id')->get();
        $groupCount = $groups->count();
        $adv = (int) $category->advance_per_group;
        $extra = (int) ($category->extra_qualifiers ?? 0);

        if ($groupCount < 1 || $adv < 1) {
            throw new \RuntimeException('Configura los grupos y cuántos avanzan antes de generar la llave.');
        }

        // Ordered seed-LABEL list (strongest first), then standard fold to a
        // power-of-2 bracket with byes. Always crash-safe for any count.
        $seedLabels = $this->positionalSeedLabels($groupCount, $adv, $extra);

        // Minimum bracket size is 2: a single qualifier (one group, advance=1)
        // yields a valid bye-final (A1 vs BYE) rather than an error. Guard against
        // the empty case, which genuinely can't form a bracket.
        if (count($seedLabels) < 1) {
            throw new \RuntimeException('No hay suficientes posiciones para una llave.');
        }
        $size = max(2, $this->nextPowerOfTwo(count($seedLabels)));
        $padded = array_pad($seedLabels, $size, 'BYE');

        // Place seeds into bracket-slot order using STANDARD tournament seeding,
        // then pair adjacent slots. This guarantees seed 1 & 2 land in opposite
        // halves, 3 & 4 in opposite quarters, etc. — and because seedLabels are
        // in strength order (all winners, then all runners-up by group), a
        // group's #1 and #2 can only meet in the final, never in an early round.
        //
        // The old naive fold (seed[i] vs seed[size-1-i]) collapsed here: it
        // paired A1 vs A2 whenever a group had 2+ qualifiers. See
        // standardSeedOrder() for the slot-order construction.
        $order = $this->standardSeedOrder($size);   // 1-based seed per slot
        $slots = array_map(fn($seedNo) => $padded[$seedNo - 1], $order);

        // Adjacent slots form the first-round matches.
        // Size 2 → one match [A1, BYE] → the Final.
        $pairings = [];
        for ($i = 0; $i < $size; $i += 2) {
            $pairings[] = [$slots[$i], $slots[$i + 1]];
        }

        $rounds = (int) log($size, 2); // size 2 → 1 round (the final)

        DB::transaction(function () use ($category, $pairings, $rounds) {
            GameMatch::where('category_id', $category->id)->whereNull('group_id')->delete();

            $firstRound = [];
            foreach ($pairings as $slot => [$labelA, $labelB]) {
                $firstRound[] = GameMatch::create([
                    'category_id' => $category->id,
                    'round' => 1,
                    'slot' => $slot,
                    'seed_label_a' => $labelA,
                    'seed_label_b' => $labelB,
                ]);
            }

            // Subsequent rounds with feeder links. (None for a size-2 bye-final.)
            $prev = $firstRound;
            for ($round = 2; $round <= $rounds; $round++) {
                $current = [];
                for ($slot = 0; $slot < count($prev) / 2; $slot++) {
                    $current[] = GameMatch::create([
                        'category_id' => $category->id,
                        'round' => $round,
                        'slot' => $slot,
                        'feeder_a_id' => $prev[$slot * 2]->id,
                        'feeder_b_id' => $prev[$slot * 2 + 1]->id,
                    ]);
                }
                $prev = $current;
            }
        });
    }

    /**
     * Ordered seed-label list in TRUE STRENGTH ORDER (strongest first):
     * all group winners (A1, B1, C1…), then all runners-up (A2, B2, C2…), then
     * all third-places, and finally extra-qualifier slots (Q1, Q2…).
     *
     * No rotation here — separation of same-group qualifiers is handled by
     * standardSeedOrder() when placing these into bracket slots, which is the
     * mathematically correct place for it. Emitting a clean strength order lets
     * standard seeding do its job (seed 1 vs 2 in opposite halves, and a group's
     * #1/#2 meeting only in the final).
     *
     * @return array<int,string>
     */
    private function positionalSeedLabels(int $groupCount, int $adv, int $extra): array
    {
        $letters = [];
        for ($i = 0; $i < $groupCount; $i++) $letters[] = chr(ord('A') + $i);

        $seeds = [];
        // Tier by placement: all 1st places, then all 2nd, etc. Within a tier,
        // group order (A, B, C…) is the strength order.
        for ($place = 1; $place <= $adv; $place++) {
            foreach ($letters as $L) {
                $seeds[] = $L . $place;
            }
        }
        // Extra cross-group qualifiers come last (weakest seeds).
        for ($k = 1; $k <= $extra; $k++) {
            $seeds[] = 'Q' . $k;
        }

        return $seeds;
    }

    /**
     * Standard single-elimination seed order for a bracket of $size (a power of
     * two). Returns the 1-based seed number that occupies each slot, top to
     * bottom, such that pairing adjacent slots gives the classic bracket:
     *   size 2 → [1, 2]
     *   size 4 → [1, 4, 2, 3]        pairs (1v4)(2v3)
     *   size 8 → [1, 8, 4, 5, 2, 7, 3, 6]
     *
     * Built recursively: each seed s at level n/2 expands to [s, (n+1 - s)] at
     * level n, which is the standard "fold the bracket" construction. This is
     * what puts seed 1 and seed 2 in opposite halves, 3 and 4 in opposite
     * quarters, and so on.
     *
     * @return array<int,int>  slotIndex => seedNumber (1-based)
     */
    private function standardSeedOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $n = count($order) * 2;      // next level size
            $next = [];
            foreach ($order as $s) {
                $next[] = $s;
                $next[] = $n + 1 - $s;
            }
            $order = $next;
        }
        return $order;
    }

    /**
     * Bind real qualifier pairs into a positional bracket once standings are
     * final. Resolves each seed label (e.g. "B2") to the pair that finished in
     * that group position, fills round-1 pairs, applies byes, and advances.
     */
    public function bindQualifiers(Category $category): void
    {
        // Map seed label → pair id from final standings.
        $map = $this->seedLabelMap($category);

        DB::transaction(function () use ($category, $map) {
            $firstRound = GameMatch::where('category_id', $category->id)
                ->whereNull('group_id')->where('round', 1)->orderBy('slot')->get();

            foreach ($firstRound as $m) {
                $pairA = $m->seed_label_a ? ($map[$m->seed_label_a] ?? null) : null;
                $pairB = $m->seed_label_b ? ($map[$m->seed_label_b] ?? null) : null;
                $m->update(['pair_a_id' => $pairA, 'pair_b_id' => $pairB]);

                if ($pairA && ! $pairB && $m->seed_label_b === 'BYE') {
                    $this->autoWin($m->fresh(), $pairA);
                } elseif ($pairB && ! $pairA && $m->seed_label_a === 'BYE') {
                    $this->autoWin($m->fresh(), $pairB);
                }
            }

            foreach ($firstRound as $m) {
                $fresh = $m->fresh();
                if ($fresh->winner_pair_id) $this->advanceWinner($fresh);
            }
        });
    }

    /** label (A1, B2…, Q1…) → pair id, from each group's final standings. */
    private function seedLabelMap(Category $category): array
    {
        $groups = $category->groups()->orderBy('position')->orderBy('id')->get();
        $letters = array_map(fn($i) => chr(ord('A') + $i), array_keys($groups->all()));

        $map = [];
        // Track which (group, position) pairs are auto-qualifiers so the Q-slots
        // can be filled from the NEXT-best finishers without double-assigning.
        foreach ($groups as $i => $group) {
            $L = $letters[$i];
            $standing = $this->standings->forGroup($group);
            foreach ($standing as $pos => $row) {
                $map[$L . ($pos + 1)] = $row['pair_id']; // A1, A2…
            }
        }

        // Extra qualifiers (Q1, Q2…): the best boundary finishers ranked across
        // groups, as computed by qualifiers(). They come AFTER the auto pairs in
        // the returned list, so take the tail.
        $extra = (int) ($category->extra_qualifiers ?? 0);
        if ($extra > 0) {
            $auto = $groups->count() * (int) $category->advance_per_group;
            $ranked = $this->qualifiers($category)['qualifiers'] ?? [];
            $extraIds = array_slice($ranked, $auto, $extra);
            foreach ($extraIds as $k => $pairId) {
                $map['Q' . ($k + 1)] = $pairId;
            }
        }

        return $map;
    }

    /** True when every group in the category has all its matches confirmed. */
    public function groupsComplete(Category $category): bool
    {
        $pending = GameMatch::where('category_id', $category->id)
            ->whereNotNull('group_id')
            ->where('state', '!=', MatchState::Confirmed->value)
            ->exists();
        return ! $pending;
    }

    /** Swap the two seed labels (or pairs) between two round-1 bracket slots. */
    public function swapSlots(GameMatch $a, string $sideA, GameMatch $b, string $sideB): void
    {
        $labelCol = fn($s) => $s === 'a' ? 'seed_label_a' : 'seed_label_b';
        $pairCol = fn($s) => $s === 'a' ? 'pair_a_id' : 'pair_b_id';

        $tmpLabel = $a->{$labelCol($sideA)};
        $tmpPair = $a->{$pairCol($sideA)};

        $a->update([
            $labelCol($sideA) => $b->{$labelCol($sideB)},
            $pairCol($sideA) => $b->{$pairCol($sideB)},
        ]);
        $b->update([
            $labelCol($sideB) => $tmpLabel,
            $pairCol($sideB) => $tmpPair,
        ]);
    }

    /** Mark a bye/auto win without a score. */
    private function autoWin(GameMatch $match, int $pairId): void
    {
        $match->update([
            'state' => MatchState::Confirmed,
            'winner_pair_id' => $pairId,
            'result_type' => 'walkover',
            'incident_note' => 'Bye',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Push a confirmed match's winner into the correct slot of its parent.
     * Called after a result is confirmed (here and from the result flow in
     * Phase 6).
     */
    public function advanceWinner(GameMatch $match): void
    {
        if (! $match->winner_pair_id) return;

        $parent = GameMatch::where('feeder_a_id', $match->id)->first();
        $slot = 'pair_a_id';
        if (! $parent) {
            $parent = GameMatch::where('feeder_b_id', $match->id)->first();
            $slot = 'pair_b_id';
        }
        if (! $parent) return; // final has no parent

        $parent->update([$slot => $match->winner_pair_id]);

        // If both sides of the parent are byes/known winners and one is empty,
        // nothing else to do; the parent becomes playable when both filled.
    }

    // --- Seeding helpers ----------------------------------------------

    private function nextPowerOfTwo(int $n): int
    {
        $p = 1;
        while ($p < $n) $p *= 2;
        return $p;
    }

    /**
     * Standard bracket seed positions for a bracket of given size.
     * Returns an array where index = slot position, value = seed number (1..size).
     * E.g. size 4 → [1,4,3,2] meaning slot0=seed1, slot1=seed4, etc.
     */
    private function seedPositions(int $size): array
    {
        $rounds = (int) log($size, 2);
        $seeds = [1, 2];
        for ($r = 1; $r < $rounds; $r++) {
            $next = [];
            $sum = count($seeds) * 2 + 1;
            foreach ($seeds as $s) {
                $next[] = $s;
                $next[] = $sum - $s;
            }
            $seeds = $next;
        }
        return $seeds;
    }
}
