<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Analytics\UmamiClient;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    private const RANGES = ['all' => 'Todo', 'today' => 'Hoy', '7d' => '7 días', '30d' => '30 días', '90d' => '90 días'];

    /** Dimension breakdowns pulled from Umami's metrics endpoint (type → label), grouped. */
    private const GROUPS = [
        'Ubicación' => ['country' => 'Países', 'region' => 'Regiones', 'city' => 'Ciudades'],
        'Orígenes' => ['referrer' => 'Referentes', 'channel' => 'Canales'],
        'Entorno' => ['browser' => 'Navegadores', 'os' => 'Sistema', 'device' => 'Dispositivos'],
    ];

    public function index(Request $request, UmamiClient $umami)
    {
        $tournaments = Tournament::orderByDesc('starts_on')->get(['id', 'name', 'slug', 'starts_on']);

        $selected = null;
        if ($request->filled('tournament')) {
            $selected = $tournaments->firstWhere('slug', $request->string('tournament'));
        }
        $selected ??= $tournaments->first();

        $range = array_key_exists($request->get('range'), self::RANGES) ? $request->get('range') : 'all';
        [$startMs, $endMs] = $this->window($range, $selected);

        $report = null;
        if ($selected) {
            // The public page path Umami records for this tournament.
            $path = '/t/' . $selected->slug;
            $event = (string) config('services.umami.register_event');

            $stats = $umami->stats($path, $startMs, $endMs);
            $report = [
                'path' => $path,
                'configured' => $umami->configured(),
                'error' => $stats['error'] ?? null,
                'stats' => $stats['error'] ?? false ? null : $stats,
                'event_name' => $event,
                'event_count' => $umami->configured() ? $umami->eventCount($event, $startMs, $endMs) : 0,
                'series' => $umami->pageviewsSeries($path, $startMs, $endMs),
                'breakdowns' => $this->breakdowns($umami, $path, $startMs, $endMs),
            ];
        }

        return view('admin.analytics.index', [
            'tournaments' => $tournaments,
            'selected' => $selected,
            'ranges' => self::RANGES,
            'range' => $range,
            'report' => $report,
        ]);
    }

    /** Stylized one-document brief for a manager: tournament + every category's traffic. */
    public function exportPdf(Request $request, Tournament $tournament, UmamiClient $umami)
    {
        $range = array_key_exists($request->get('range'), self::RANGES) ? $request->get('range') : 'all';
        [$startMs, $endMs] = $this->window($range, $tournament);
        $event = (string) config('services.umami.register_event');

        $overallPath = '/t/' . $tournament->slug;
        $overall = $umami->stats($overallPath, $startMs, $endMs);

        $rows = [];
        foreach ($tournament->categories()->orderBy('name')->get() as $c) {
            $path = '/t/' . $tournament->slug . '/' . $c->slug;
            $s = $umami->stats($path, $startMs, $endMs);
            $rows[] = ['name' => $c->name, 'path' => $path, 'stats' => ($s['error'] ?? false) ? null : $s];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.analytics.pdf', [
            'tournament' => $tournament,
            'rangeLabel' => self::RANGES[$range],
            'overall' => ($overall['error'] ?? false) ? null : $overall,
            'overallPath' => $overallPath,
            'rows' => $rows,
            'eventName' => $event,
            'eventCount' => $umami->configured() ? $umami->eventCount($event, $startMs, $endMs) : 0,
            'configured' => $umami->configured(),
            'error' => $overall['error'] ?? null,
            'breakdowns' => $this->breakdowns($umami, $overallPath, $startMs, $endMs),
            'generatedAt' => now('America/Mexico_City'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-analitica.pdf');
    }

    /**
     * Location / Sources / Environment breakdowns for ONE path (the tournament
     * main page), each via Umami's metrics endpoint. Grouped and labelled for the
     * view + PDF. Empty when Umami isn't configured.
     *
     * @return array<string, array<int, array{label:string, rows:array}>>
     */
    private function breakdowns(UmamiClient $umami, string $path, int $startMs, int $endMs): array
    {
        if (! $umami->configured()) return [];

        $out = [];
        foreach (self::GROUPS as $group => $types) {
            $subs = [];
            foreach ($types as $type => $label) {
                $subs[] = ['label' => $label, 'rows' => $umami->metrics($type, $path, $startMs, $endMs, 8)];
            }
            $out[$group] = $subs;
        }
        return $out;
    }

    /** [startMs, endMs] for a range key, in the tournament timezone's day boundaries. */
    private function window(string $range, ?Tournament $tournament = null): array
    {
        $now = now('America/Mexico_City');
        $start = match ($range) {
            // "Todo": everything since the tournament page could first get traffic.
            'all' => ($tournament?->created_at ?? $now->copy()->subYears(2))->copy()->startOfDay(),
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(7)->startOfDay(),
            '90d' => $now->copy()->subDays(90)->startOfDay(),
            default => $now->copy()->subDays(30)->startOfDay(),
        };
        return [$start->valueOf(), $now->valueOf()]; // milliseconds
    }
}
