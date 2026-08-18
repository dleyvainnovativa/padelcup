<?php

namespace App\Services\Tournament;

use App\Enums\MatchState;
use App\Models\Category;
use App\Models\GameMatch;

/**
 * Phase 0 — the single source of truth for a category's structural state and
 * whether regenerating its groups/bracket is safe (lossless) or destructive.
 *
 * Everything in the regeneration feature (per-category action, banner, bulk
 * view) reads from here, so the safety rules live in exactly ONE place.
 *
 * State machine:
 *   fresh              — no groups yet, OR groups exist but ZERO confirmed
 *                        matches. Regenerating is lossless.
 *   groups_in_progress — at least one GROUP match confirmed, but no bracket
 *                        results yet. Regenerating groups destroys results.
 *   bracket_started    — at least one BRACKET match confirmed (or qualifiers
 *                        bound). Regenerating groups is almost never intended.
 *
 * Staleness (independent of state):
 *   group-stale   — the pairs in groups no longer match the current pool
 *                   (a pair was added/removed, or the size distribution shifted).
 *   bracket-stale — pool + groups fine, but advance_per_group / extra_qualifiers
 *                   changed so the existing bracket's slot count is wrong.
 *
 * Domain facts this relies on (verified against the app):
 *   - groups:            $category->groups (hasMany, ordered by position)
 *   - group membership:  $group->pairs (group_pair pivot)
 *   - the draw pool:     $category->poolPairs() (confirmed registrations)
 *   - bracket matches:   whereNull('group_id')
 *   - group matches:     whereNotNull('group_id')
 *   - played:            state === MatchState::Confirmed
 *   - expected sizes:    GroupGenerationService::distribution()
 *   - qualifier count:   Category::qualifiersTotal($groupCount)
 */
class CategoryStructureState
{
    public const FRESH = 'fresh';
    public const GROUPS_IN_PROGRESS = 'groups_in_progress';
    public const BRACKET_STARTED = 'bracket_started';

    public function __construct(private GroupGenerationService $groupGen) {}

    /**
     * Full structural snapshot for a category.
     *
     * @return array{
     *   state: string,
     *   has_groups: bool,
     *   has_bracket: bool,
     *   pool_pairs: int,
     *   grouped_pairs: int,
     *   group_count: int,
     *   expected_sizes: array<int,int>,
     *   actual_sizes: array<int,int>,
     *   group_stale: bool,
     *   bracket_stale: bool,
     *   stale: bool,
     *   confirmed_group_matches: int,
     *   confirmed_bracket_matches: int,
     *   results_at_risk: int,
     *   can_regenerate_safely: bool,
     *   can_rebuild_bracket_only: bool,
     * }
     */
    public function for(Category $category): array
    {
        $format = $category->format;
        $hasGroups  = $format->hasGroups();
        $hasBracket = $format->hasBracket();

        $groups = $category->groups()->with('pairs')->get();
        $groupCount = $groups->count();

        $poolPairs = $category->poolPairs()->count();
        $groupedPairs = (int) $groups->sum(fn ($g) => $g->pairs->count());

        // Confirmed match counts, split by group vs bracket.
        $confirmedGroup = GameMatch::where('category_id', $category->id)
            ->whereNotNull('group_id')
            ->where('state', MatchState::Confirmed->value)
            ->count();

        $confirmedBracket = GameMatch::where('category_id', $category->id)
            ->whereNull('group_id')
            ->where('state', MatchState::Confirmed->value)
            ->count();

        // State machine.
        if ($confirmedBracket > 0) {
            $state = self::BRACKET_STARTED;
        } elseif ($confirmedGroup > 0) {
            $state = self::GROUPS_IN_PROGRESS;
        } else {
            $state = self::FRESH;
        }

        // Expected vs actual group shape.
        $expectedSizes = [];
        $actualSizes = [];
        if ($hasGroups) {
            $preferred = $category->preferred_group_size ?: 4;
            $expectedSizes = $this->groupGen->distribution($poolPairs, $preferred);
            $actualSizes = $groups
                ->map(fn ($g) => $g->pairs->count())
                ->sortDesc()
                ->values()
                ->all();
        }

        // Group-stale: pairs in groups differ from the pool, OR the size shape
        // the pool now wants differs from what's actually built. Only meaningful
        // once groups exist (a category with no groups yet is "fresh", not stale
        // — it just needs first-time generation, handled by has_groups=false).
        $groupStale = false;
        if ($hasGroups && $groupCount > 0) {
            $groupStale = ($groupedPairs !== $poolPairs)
                || ($expectedSizes !== $actualSizes);
        }

        // Bracket-stale: bracket exists, pool/groups unchanged, but the current
        // qualifier config wants a different number of slots than the bracket has.
        $bracketStale = false;
        if ($hasBracket && ! $groupStale) {
            $bracketSlots = $this->bracketFirstRoundSlots($category);
            if ($bracketSlots > 0) {
                $wantedQualifiers = $category->qualifiersTotal($groupCount);
                $wantedSlots = $this->nextPow2(max(2, $wantedQualifiers));
                $bracketStale = $wantedSlots !== $bracketSlots;
            }
        }

        $stale = $groupStale || $bracketStale;

        // What a FULL regeneration would destroy.
        $resultsAtRisk = $confirmedGroup + $confirmedBracket;

        // Safe to regenerate losslessly only when nothing is confirmed.
        $canRegenerateSafely = ($state === self::FRESH);

        // Bracket-only rebuild is lossless when the groups/results stand and only
        // the bracket is stale (group results are kept; bracket is rebuilt).
        $canRebuildBracketOnly = $hasBracket
            && $bracketStale
            && ! $groupStale
            && $confirmedBracket === 0;

        return [
            'state' => $state,
            'has_groups' => $hasGroups,
            'has_bracket' => $hasBracket,
            'pool_pairs' => $poolPairs,
            'grouped_pairs' => $groupedPairs,
            'group_count' => $groupCount,
            'expected_sizes' => $expectedSizes,
            'actual_sizes' => $actualSizes,
            'group_stale' => $groupStale,
            'bracket_stale' => $bracketStale,
            'stale' => $stale,
            'confirmed_group_matches' => $confirmedGroup,
            'confirmed_bracket_matches' => $confirmedBracket,
            'results_at_risk' => $resultsAtRisk,
            'can_regenerate_safely' => $canRegenerateSafely,
            'can_rebuild_bracket_only' => $canRebuildBracketOnly,
        ];
    }

    /** Convenience: just the state string. */
    public function state(Category $category): string
    {
        return $this->for($category)['state'];
    }

    /** Convenience: does this category need attention (stale)? */
    public function isStale(Category $category): bool
    {
        return $this->for($category)['stale'];
    }

    /**
     * Number of first-round slots in the current bracket = 2 × (round-1 match
     * count). 0 if no bracket built yet.
     */
    private function bracketFirstRoundSlots(Category $category): int
    {
        $firstRoundMatches = GameMatch::where('category_id', $category->id)
            ->whereNull('group_id')
            ->where('round', 1)
            ->count();

        return $firstRoundMatches * 2;
    }

    /** Smallest power of two ≥ n (min 2). Mirrors BracketService sizing. */
    private function nextPow2(int $n): int
    {
        $p = 2;
        while ($p < $n) $p *= 2;
        return $p;
    }
}
