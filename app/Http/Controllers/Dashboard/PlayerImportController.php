<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tournament;
use App\Services\Registration\PlayerImportService;
use Illuminate\Http\Request;
use App\Enums\GroupFormat;

class PlayerImportController extends Controller
{
    public function __construct(private PlayerImportService $import) {}

    public function form(Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        return view('dashboard.pairs.import', compact('tournament', 'category'));
    }

    /** Parse the upload and show a preview with duplicate flags. */
    public function preview(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        // Singles vs doubles is known from the category — pass it to the parser
        // so a singles CSV isn't rejected for missing player 2.
        $isSingles = $category->isSingles();

        $parsed = $this->import->parse($request->file('file')->getRealPath(), $isSingles);
        $rows = $this->import->withDuplicateFlags($parsed['rows']);

        $remaining = $category->max_pairs
            ? max(0, $category->max_pairs - $category->occupiedSlots())
            : null;

        $existingPairs = $category->occupiedSlots();
        $preferredSize = $category->preferred_group_size ?: 4;
        $currentFormat = ($category->group_format === GroupFormat::Mexicano) ? 'mex' : 'rr';

        return view('dashboard.pairs.import-preview', [
            'tournament'    => $tournament,
            'category'      => $category,
            'rows'          => $rows,
            'errors'        => $parsed['errors'],
            'remaining'     => $remaining,
            'existingPairs' => $existingPairs,
            'preferredSize' => $preferredSize,
            'currentFormat' => $currentFormat,
            'isSingles'     => $isSingles,   // for the preview view
        ]);
    }

    /** Commit the previewed rows into the category. */
    public function commit(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $isSingles = $category->isSingles();

        // Player 2 fields are required only for doubles categories.
        $rules = [
            'group_format' => ['nullable', 'in:mex,rr'],
            'rows' => ['required', 'array'],
            'rows.*.player1.name' => ['required', 'string', 'max:255'],
            'rows.*.player1.email' => ['nullable', 'email'],
            'rows.*.player1.phone' => ['nullable', 'string', 'max:30'],
            'rows.*.player1.link_player_id' => ['nullable', 'integer', 'exists:players,id'],
        ];

        if (! $isSingles) {
            $rules += [
                'rows.*.player2.name' => ['required', 'string', 'max:255'],
                'rows.*.player2.email' => ['nullable', 'email'],
                'rows.*.player2.phone' => ['nullable', 'string', 'max:30'],
                'rows.*.player2.link_player_id' => ['nullable', 'integer', 'exists:players,id'],
            ];
        } else {
            // Tolerate (and ignore) player2 fields if the form still posts them.
            $rules += [
                'rows.*.player2' => ['nullable', 'array'],
                'rows.*.player2.name' => ['nullable', 'string', 'max:255'],
                'rows.*.player2.email' => ['nullable', 'email'],
                'rows.*.player2.phone' => ['nullable', 'string', 'max:30'],
                'rows.*.player2.link_player_id' => ['nullable', 'integer', 'exists:players,id'],
            ];
        }

        $data = $request->validate($rules);

        if (! empty($data['group_format'])) {
            $category->group_format = $data['group_format'] === 'rr'
                ? GroupFormat::RoundRobin
                : GroupFormat::Mexicano;
            $category->save();
        }

        $result = $this->import->commit($data['rows'], $category, $request->user());

        $unit = $isSingles ? 'jugadores' : 'parejas';
        $msg = "{$result['imported']} {$unit} importados.";
        if ($result['skipped'] > 0) {
            $msg .= " {$result['skipped']} omitidos (categoría llena o error).";
        }

        return redirect()
            ->route('categories.show', [$tournament, $category])
            ->with('status', $msg);
    }
}
