<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tournament;
use App\Services\Registration\PlayerImportService;
use Illuminate\Http\Request;

class PlayerImportController extends Controller
{
    public function __construct(private PlayerImportService $import) {}

    public function form(Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        return view('dashboard.pairs.import', compact('tournament', 'category'));
    }

    /** Parse the upload and show a pair preview with duplicate flags. */
    public function preview(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $parsed = $this->import->parse($request->file('file')->getRealPath());
        $rows = $this->import->withDuplicateFlags($parsed['rows']);

        // Capacity warning: remaining slots vs pairs in the file.
        $remaining = $category->max_pairs
            ? max(0, $category->max_pairs - $category->occupiedSlots())
            : null;


        return view('dashboard.pairs.import-preview', [
            'tournament' => $tournament,
            'category' => $category,
            'rows' => $rows,
            'errors' => $parsed['errors'],
            'remaining' => $remaining,
        ]);
    }

    /** Commit the previewed pair rows into the category. */
    public function commit(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.player1.name' => ['required', 'string', 'max:255'],
            'rows.*.player1.email' => ['nullable', 'email'],
            'rows.*.player1.phone' => ['nullable', 'string', 'max:30'],
            'rows.*.player1.link_player_id' => ['nullable', 'integer', 'exists:players,id'],
            'rows.*.player2.name' => ['required', 'string', 'max:255'],
            'rows.*.player2.email' => ['nullable', 'email'],
            'rows.*.player2.phone' => ['nullable', 'string', 'max:30'],
            'rows.*.player2.link_player_id' => ['nullable', 'integer', 'exists:players,id'],
        ]);

        $result = $this->import->commit($data['rows'], $category, $request->user());

        $msg = "{$result['imported']} parejas importadas.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} omitidas (categoría llena o error).";
        }

        return redirect()
            ->route('categories.show', [$tournament, $category])
            ->with('status', $msg);
    }
}
