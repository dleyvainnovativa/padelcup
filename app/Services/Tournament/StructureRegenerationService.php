<?php

namespace App\Services\Tournament;

use App\Enums\CategoryFormat;
use App\Models\Category;
use App\Models\GameMatch;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — performs group/bracket regeneration for a category, gated by
 * CategoryStructureState so it can never silently destroy played results.
 *
 * Reuses the SAME generation calls the manual draw flow uses (DrawController):
 *   - groups:  GroupGenerationService::generate($category, $poolPairs)
 *   - bracket: BracketService::buildPositional($category)  (Hybrid, pre-finish)
 *
 * Three operations:
 *   regenerateAll()      — groups (+ positional bracket for Hybrid). LOSSLESS
 *                          only when state is fresh; otherwise requires $force
 *                          and reports how many results it deleted.
 *   rebuildBracketOnly() — rebuild the positional bracket, keep group results.
 *                          Lossless; refuses if any bracket match is confirmed.
 *
 * This service does NOT decide policy on its own — the controller consults
 * CategoryStructureState and passes an explicit $force for destructive cases.
 */
class StructureRegenerationService
{
    public function __construct(
        private GroupGenerationService $groups,
        private BracketService $brackets,
        private CategoryStructureState $state,
    ) {}

    /**
     * Regenerate groups (+ bracket for Hybrid) for a category.
     *
     * @param  bool  $force  allow regeneration even when results exist (they
     *                       will be deleted). Ignored when state is fresh.
     * @return array{ok:bool, regenerated:bool, deleted_results:int, reason:?string}
     */
    public function regenerateAll(Category $category, bool $force = false): array
    {
        $snapshot = $this->state->for($category);

        // Guard: safe (fresh) needs no force; anything else requires it.
        if (! $snapshot['can_regenerate_safely'] && ! $force) {
            return [
                'ok' => false,
                'regenerated' => false,
                'deleted_results' => 0,
                'reason' => 'has_results', // controller turns this into a confirm
            ];
        }

        $pairs = $category->poolPairs()->with(['player1', 'player2'])->get();
        if ($pairs->count() < 2) {
            return [
                'ok' => false,
                'regenerated' => false,
                'deleted_results' => 0,
                'reason' => 'too_few_pairs',
            ];
        }

        $deleted = $snapshot['results_at_risk'];

        DB::transaction(function () use ($category, $pairs) {
            // generate() rebuilds groups + group matches. For Hybrid we then
            // (re)build the positional bracket, mirroring DrawController.
            $this->groups->generate($category, $pairs);

            if ($category->format === CategoryFormat::Hybrid) {
                $this->brackets->buildPositional($category->fresh());
            }
        });

        return [
            'ok' => true,
            'regenerated' => true,
            'deleted_results' => $deleted,
            'reason' => null,
        ];
    }

    /**
     * Rebuild ONLY the positional bracket, keeping groups and their results.
     * Lossless — used when the bracket is stale (advance/extra changed) but no
     * bracket match has been played yet.
     *
     * @return array{ok:bool, rebuilt:bool, reason:?string}
     */
    public function rebuildBracketOnly(Category $category): array
    {
        $snapshot = $this->state->for($category);

        if (! $snapshot['can_rebuild_bracket_only']) {
            return [
                'ok' => false,
                'rebuilt' => false,
                'reason' => $snapshot['confirmed_bracket_matches'] > 0
                    ? 'bracket_has_results'
                    : 'not_applicable',
            ];
        }

        // buildPositional() deletes existing bracket matches (whereNull group_id)
        // and rebuilds — group matches are untouched.
        $this->brackets->buildPositional($category->fresh());

        return ['ok' => true, 'rebuilt' => true, 'reason' => null];
    }
}
