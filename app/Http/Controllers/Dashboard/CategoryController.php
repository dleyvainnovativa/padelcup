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

        $category->update($request->validated());

        // Format changed → reconcile the structure so the old stages don't linger
        // with no UI to delete them (the "can't delete the group matches after
        // switching to elimination" trap). Safe (no confirmed results) → rebuild
        // for the new format, clearing what it no longer has. With results → leave
        // them and point the manager at Regenerar instead of destroying silently.
        if ($category->format?->value !== $oldFormat) {
            $res = $structureRegen->regenerateAll($category->fresh());
            if (! empty($res['ok'])) {
                return redirect()
                    ->route('categories.show', [$tournament, $category])
                    ->with('status', 'Categoría actualizada y estructura regenerada para el nuevo formato.');
            }
            if (($res['reason'] ?? null) === 'has_results') {
                return redirect()
                    ->route('categories.show', [$tournament, $category])
                    ->with('status', 'Categoría actualizada. El formato cambió, pero ya hay resultados: usa «Regenerar» en Grupos o Llave para reconstruir la estructura.');
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
