<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Tournament\PodiumService;
use App\Services\Tournament\WinnersExport;

/**
 * Manager "Ganadores" page: a quick end-of-tournament view of every category's
 * champion / finalist (and 3rd place when a real playoff exists), with an Excel
 * export for prize handout. Read-only — derives everything from confirmed
 * bracket finals via PodiumService.
 */
class WinnersController extends Controller
{
    public function __construct(private PodiumService $podiums) {}

    public function index(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $summaries = $this->podiums->forTournament($tournament);

        return view('dashboard.winners.index', [
            'tournament' => $tournament,
            'summaries'  => $summaries,
            'decidedCount' => $summaries->where('decided', true)->count(),
            'pendingCount' => $summaries->where('has_bracket', true)->where('decided', false)->count(),
        ]);
    }

    public function export(Tournament $tournament, WinnersExport $export)
    {
        $this->authorize('view', $tournament);

        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            return back()->withErrors(['export' => 'La librería de Excel no está disponible en el servidor.']);
        }

        $spreadsheet = $export->build($tournament);
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = \Illuminate\Support\Str::slug($tournament->name ?: 'torneo') . '-ganadores.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
