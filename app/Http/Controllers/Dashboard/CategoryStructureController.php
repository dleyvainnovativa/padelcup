<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tournament;
use App\Services\Tournament\CategoryStructureState;
use App\Services\Tournament\StructureRegenerationService;
use Illuminate\Http\Request;

/**
 * Phase 1 — per-category structure regeneration.
 *
 * Safety comes from CategoryStructureState: a fresh category regenerates without
 * a destructive confirm; anything with results requires an explicit force=1 and
 * the UI shows how many results will be deleted first.
 */
class CategoryStructureController extends Controller
{
    public function __construct(
        private StructureRegenerationService $regen,
        private CategoryStructureState $state,
    ) {}

    /**
     * POST tournaments/{tournament}/categories/{category}/regenerate
     * Body: force (bool, optional)
     */
    public function regenerate(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        // Respect the tournament-wide lock exactly like DrawController does.
        abort_if($tournament->isLocked(), 403, 'El torneo ya inició; no se pueden regenerar los grupos.');

        $force = $request->boolean('force');
        $result = $this->regen->regenerateAll($category, $force);

        if (! $result['ok']) {
            return match ($result['reason']) {
                'has_results' => back()->withErrors([
                    'regen' => 'Esta categoría tiene resultados. Confirma la regeneración para borrarlos.',
                ]),
                'too_few_pairs' => back()->withErrors([
                    'regen' => 'Se necesitan al menos 2 parejas confirmadas.',
                ]),
                default => back()->withErrors(['regen' => 'No se pudo regenerar.']),
            };
        }

        $msg = 'Grupos'
            . ($category->format->hasBracket() ? ' y llave' : '')
            . ' regenerados.';
        if ($result['deleted_results'] > 0) {
            $msg .= " Se borraron {$result['deleted_results']} resultado(s) previos.";
        }

        return back()->with('status', $msg);
    }

    /**
     * POST tournaments/{tournament}/categories/{category}/rebuild-bracket
     * Lossless bracket-only rebuild (keeps group results).
     */
    public function rebuildBracket(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);
        abort_if($tournament->isLocked(), 403, 'El torneo ya inició.');

        $result = $this->regen->rebuildBracketOnly($category);

        if (! $result['ok']) {
            return back()->withErrors([
                'regen' => $result['reason'] === 'bracket_has_results'
                    ? 'La llave ya tiene resultados; no se puede reconstruir sin borrarlos.'
                    : 'No aplica una reconstrucción de llave aquí.',
            ]);
        }

        return back()->with('status', 'Llave reconstruida (se conservaron los resultados de grupos).');
    }
}
