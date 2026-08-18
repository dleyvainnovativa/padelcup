<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Tournament\CategoryStructureState;
use App\Services\Tournament\StructureRegenerationService;
use Illuminate\Http\Request;

/**
 * Phase 2 — bulk structure regeneration across a tournament's categories.
 *
 * "Regenerar todas las seguras" regenerates ONLY the lossless ones (fresh full
 * regen, or bracket-only rebuild), and SILENTLY SKIPS anything with played
 * results, reporting a summary. Risky categories are never force-deleted in bulk
 * — they stay visible for a deliberate per-category decision (Phase 1).
 */
class TournamentStructureController extends Controller
{
    public function __construct(
        private CategoryStructureState $state,
        private StructureRegenerationService $regen,
    ) {}

    /**
     * POST tournaments/{tournament}/regenerate-safe
     * Regenerate every stale category that can be regenerated losslessly.
     */
    public function regenerateSafe(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);
        abort_if($tournament->isLocked(), 403, 'El torneo ya inició; no se pueden regenerar los grupos.');

        $regenerated = 0;   // fresh full regens
        $rebuilt = 0;       // bracket-only rebuilds
        $skipped = 0;       // stale but risky (has results)

        foreach ($tournament->categories as $category) {
            $snap = $this->state->for($category);

            if (! $snap['stale']) {
                continue; // nothing to do
            }

            // Lossless full regen (fresh).
            if ($snap['can_regenerate_safely']) {
                $res = $this->regen->regenerateAll($category, force: false);
                if ($res['ok']) {
                    $regenerated++;
                } else {
                    // e.g. too_few_pairs — count as skipped so the total is honest.
                    $skipped++;
                }
                continue;
            }

            // Lossless bracket-only rebuild.
            if ($snap['can_rebuild_bracket_only']) {
                $res = $this->regen->rebuildBracketOnly($category);
                $res['ok'] ? $rebuilt++ : $skipped++;
                continue;
            }

            // Stale but has results → never touched in bulk.
            $skipped++;
        }

        // Build an honest, specific report.
        $parts = [];
        if ($regenerated > 0) $parts[] = "{$regenerated} regenerada(s)";
        if ($rebuilt > 0)     $parts[] = "{$rebuilt} llave(s) reconstruida(s)";
        if ($skipped > 0)     $parts[] = "{$skipped} omitida(s) por tener resultados";

        $msg = empty($parts)
            ? 'No había categorías seguras para regenerar.'
            : implode(' · ', $parts) . '.';

        return back()->with('status', $msg);
    }
}
