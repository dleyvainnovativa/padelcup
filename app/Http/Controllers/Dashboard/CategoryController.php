<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Models\Category;
use App\Models\Tournament;

class CategoryController extends Controller
{
    public function create(Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        return view('dashboard.categories.create', compact('tournament'));
    }

    public function store(StoreCategoryRequest $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $category = Category::create([
            ...$request->validated(),
            'tournament_id' => $tournament->id,
            'tint' => $this->nextTint($tournament),
        ]);

        return redirect()
            ->route('categories.show', [$tournament, $category])
            ->with('status', 'Categoría creada.');
    }

    public function show(Tournament $tournament, Category $category)
    {
        $this->authorize('view', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $category->load([
            'pairs' => fn($q) => $q->whereHas('registration', fn($r) => $r->whereNotIn('status', [
                \App\Enums\RegistrationStatus::Cancelled->value,
                \App\Enums\RegistrationStatus::Withdrawn->value,
            ])),
            'pairs.player1',
            'pairs.player2',
            'pairs.registration',
        ]);

        return view('dashboard.categories.show', compact('tournament', 'category'));
    }

    public function edit(Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        return view('dashboard.categories.edit', compact('tournament', 'category'));
    }

    public function update(
        StoreCategoryRequest $request,
        Tournament $tournament,
        Category $category,
        \App\Services\Tournament\StructureRegenerationService $structureRegen,
    ) {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);
        $oldFormat = $category->format?->value;
        // Guard: can't lower max_pairs below current occupancy.
        $occupied = $category->occupiedSlots();
        if ($request->filled('max_pairs') && (int) $request->input('max_pairs') < $occupied) {
            return back()
                ->withInput()
                ->withErrors(['max_pairs' => "Ya hay {$occupied} parejas; el cupo no puede ser menor."]);
        }
        if (
            $request->filled('play_format')
            && $request->input('play_format') !== $category->play_format?->value
            && $category->pairs()->exists()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'play_format' => 'No puedes cambiar entre dobles y singles cuando ya hay inscripciones. Crea una categoría nueva.',
                ]);
        }

        // Changing the format drops a stage, and that stage's matches/results are
        // removed with it (e.g. Grupos→eliminación → Solo eliminación deletes the
        // group matches). On a category that already has results that's
        // destructive, so require explicit confirmation — but NEVER leave the old
        // stage orphaned with no way to delete it (the bug this fixes). Gate
        // BEFORE saving so a refused change can't half-switch the category.
        $formatChanging = $request->filled('format') && $request->input('format') !== $oldFormat;
        $hasResults = $category->matches()
            ->where('state', \App\Enums\MatchState::Confirmed)->exists();

        if ($formatChanging && $hasResults && ! $request->boolean('confirm_structure_reset')) {
            return back()->withInput()->withErrors([
                'format' => 'Esta categoría ya tiene resultados. Confirma el cambio de formato para reconstruir la estructura (se perderán los partidos y resultados actuales).',
            ]);
        }

        $category->update($request->validated());

        // Rebuild for the new format, clearing the stage it no longer has. force:
        // the results case was already confirmed above; a fresh category rebuilds
        // losslessly; too few pairs → nothing to build (just saved).
        if ($formatChanging) {
            $res = $structureRegen->regenerateAll($category->fresh(), force: true);
            if (! empty($res['ok'])) {
                $deleted = (int) ($res['deleted_results'] ?? 0);
                return redirect()
                    ->route('categories.show', [$tournament, $category])
                    ->with('status', $deleted > 0
                        ? "Categoría actualizada; estructura regenerada ({$deleted} resultado(s) borrados)."
                        : 'Categoría actualizada y estructura regenerada para el nuevo formato.');
            }
        }

        return redirect()
            ->route('categories.show', [$tournament, $category])
            ->with('status', 'Categoría actualizada.');
    }

    public function destroy(Tournament $tournament, Category $category)
    {
        $this->authorize('delete', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $category->delete();

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('status', 'Categoría eliminada.');
    }

    /** Auto-assign the next tint (1..6, cycling) based on existing categories. */
    private function nextTint(Tournament $tournament): int
    {
        $used = $tournament->categories()->count();

        return ($used % 6) + 1;
    }
}
