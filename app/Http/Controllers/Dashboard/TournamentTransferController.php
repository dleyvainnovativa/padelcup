<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Tournament\TournamentTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TournamentTransferController extends Controller
{
    public function __construct(private TournamentTransferService $transfer)
    {
    }

    /** Download the full tournament as a .json file. */
    public function export(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $payload = $this->transfer->export($tournament);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $filename = 'voleo-' . (Str::slug($tournament->name) ?: 'torneo') . '-' . now()->format('Ymd-His') . '.json';

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** Show the import form (file picker). */
    public function importForm()
    {
        $this->authorize('create', Tournament::class);

        return view('dashboard.tournaments.transfer-import');
    }

    /** Handle the uploaded JSON: create a new tournament, redirect to it. */
    public function import(Request $request)
    {
        $this->authorize('create', Tournament::class);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:20480'], // 20 MB
        ], [
            'file.required' => 'Selecciona un archivo .json exportado de Voleo.',
            'file.max' => 'El archivo es demasiado grande (máx. 20 MB).',
        ]);

        $raw = file_get_contents($request->file('file')->getRealPath());
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return back()->withErrors(['file' => 'El archivo no es un JSON válido.']);
        }

        try {
            $tournament = $this->transfer->import($data, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['file' => 'No se pudo importar el torneo. Revisa que el archivo sea un export completo y válido.']);
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('status', 'Torneo importado correctamente. Está en modo borrador para que lo revises antes de publicarlo.');
    }
}
