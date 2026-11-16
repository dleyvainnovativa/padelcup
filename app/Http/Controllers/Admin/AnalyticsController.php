<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Analytics\UmamiClient;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    private const RANGES = ['today' => 'Hoy', '7d' => '7 días', '30d' => '30 días', '90d' => '90 días'];

    public function index(Request $request, UmamiClient $umami)
    {
        $tournaments = Tournament::orderByDesc('starts_on')->get(['id', 'name', 'slug', 'starts_on']);

        $selected = null;
        if ($request->filled('tournament')) {
            $selected = $tournaments->firstWhere('slug', $request->string('tournament'));
        }
        $selected ??= $tournaments->first();

        $range = array_key_exists($request->get('range'), self::RANGES) ? $request->get('range') : '30d';
        [$startMs, $endMs] = $this->window($range);

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
                'referrers' => $umami->metrics('referrer', $path, $startMs, $endMs),
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

    /** [startMs, endMs] for a range key, in the tournament timezone's day boundaries. */
    private function window(string $range): array
    {
        $now = now('America/Mexico_City');
        $start = match ($range) {
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(7)->startOfDay(),
            '90d' => $now->copy()->subDays(90)->startOfDay(),
            default => $now->copy()->subDays(30)->startOfDay(),
        };
        return [$start->valueOf(), $now->valueOf()]; // milliseconds
    }
}
