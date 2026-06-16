<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Registration\TournamentImportService;
use Illuminate\Http\Request;

class TournamentImportController extends Controller
{
    public function __construct(private TournamentImportService $import) {}

    /** Upload / paste form. */
    public function form(Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        return view('dashboard.tournaments.import', compact('tournament'));
    }

    /** Parse the upload/paste and show the preview. */
    public function preview(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $request->validate([
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx,xls', 'max:8192'],
            'pasted' => ['nullable', 'string'],
        ]);

        $csvText = $this->extractCsvText($request);
        if ($csvText === null || trim($csvText) === '') {
            return back()->withErrors(['file' => 'Sube un archivo CSV/XLSX o pega los datos.'])->withInput();
        }

        $parsed = $this->import->parse($csvText);

        if (! empty($parsed['errors']) && empty($parsed['groups'])) {
            return back()->withErrors(['file' => implode(' ', $parsed['errors'])])->withInput();
        }

        $preview = $this->import->preview($tournament, $parsed['groups']);

        // Stash the parsed groups so commit doesn't need to re-upload.
        session(['import_groups_' . $tournament->id => $parsed['groups']]);

        return view('dashboard.tournaments.import-preview', [
            'tournament' => $tournament,
            'preview' => $preview,
            'errors' => $parsed['errors'],
            'total' => $parsed['total'],
        ]);
    }

    /** Create missing categories and import all pairs. */
    public function commit(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $groups = session('import_groups_' . $tournament->id);
        if (empty($groups)) {
            return redirect()->route('tournaments.import.form', $tournament)
                ->withErrors(['file' => 'La sesión de importación expiró. Vuelve a subir el archivo.']);
        }

        $result = $this->import->commit($tournament, $groups, $request->user());
        session()->forget('import_groups_' . $tournament->id);

        $msg = "Importación completa: {$result['imported']} parejas";
        if ($result['categories_created'] > 0) $msg .= ", {$result['categories_created']} categorías creadas";
        if ($result['skipped'] > 0) $msg .= ", {$result['skipped']} omitidas";
        $msg .= '.';

        return redirect()->route('tournaments.show', $tournament)->with('status', $msg);
    }

    /** Pull CSV text from a pasted textarea or an uploaded CSV/XLSX file. */
    private function extractCsvText(Request $request): ?string
    {
        if ($request->filled('pasted')) {
            return $request->input('pasted');
        }

        if (! $request->hasFile('file')) {
            return null;
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['csv', 'txt'])) {
            return file_get_contents($file->getRealPath());
        }

        // XLSX/XLS → CSV via PhpSpreadsheet, if available.
        if (in_array($ext, ['xlsx', 'xls']) && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            $lines = array_map(function ($row) {
                return implode(',', array_map(function ($cell) {
                    $cell = (string) ($cell ?? '');
                    // Quote cells containing commas/quotes.
                    if (str_contains($cell, ',') || str_contains($cell, '"')) {
                        $cell = '"' . str_replace('"', '""', $cell) . '"';
                    }
                    return $cell;
                }, $row));
            }, $rows);
            return implode("\n", $lines);
        }

        return null;
    }
}
