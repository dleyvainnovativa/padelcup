<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Analytics\UmamiClient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    private const TZ = 'America/Mexico_City';
    private const RANGES = ['all' => 'Todo', 'today' => 'Hoy', '7d' => '7 días', '30d' => '30 días', '90d' => '90 días'];

    /** Dimension breakdowns pulled from Umami's metrics endpoint (type → label), grouped. */
    private const GROUPS = [
        'Ubicación' => ['country' => 'Países', 'region' => 'Regiones', 'city' => 'Ciudades'],
        'Orígenes' => ['referrer' => 'Referentes', 'channel' => 'Canales'],
        'Entorno' => ['browser' => 'Navegadores', 'os' => 'Sistema', 'device' => 'Dispositivos'],
    ];

    private const DAILY_CAP = 185; // safety ceiling on per-day stats calls

    /** Umami channel keys → Spanish. */
    private const CHANNELS = [
        'direct' => 'Directo',
        'organicSearch' => 'Búsqueda orgánica',
        'organicSocial' => 'Social orgánico',
        'organicVideo' => 'Video orgánico',
        'organicShopping' => 'Compras orgánico',
        'paidSearch' => 'Búsqueda pagada',
        'paidSocial' => 'Social pagado',
        'paidShopping' => 'Compras pagado',
        'display' => 'Display',
        'referral' => 'Referido',
        'email' => 'Correo',
        'affiliates' => 'Afiliados',
        'audio' => 'Audio',
        'sms' => 'SMS',
        'unknown' => 'Desconocido',
    ];

    /** Umami device keys → Spanish. */
    private const DEVICES = [
        'mobile' => 'Móvil',
        'laptop' => 'Laptop',
        'desktop' => 'Escritorio',
        'tablet' => 'Tableta',
        'wearable' => 'Wearable',
        'console' => 'Consola',
        'tv' => 'TV',
        'unknown' => 'Desconocido',
    ];

    /** ISO 3166-2:MX state codes → full names (Umami emits these for Mexican regions). */
    private const MX_REGIONS = [
        'MX-AGU' => 'Aguascalientes', 'MX-BCN' => 'Baja California', 'MX-BCS' => 'Baja California Sur',
        'MX-CAM' => 'Campeche', 'MX-CHP' => 'Chiapas', 'MX-CHH' => 'Chihuahua', 'MX-COA' => 'Coahuila',
        'MX-COL' => 'Colima', 'MX-CMX' => 'Ciudad de México', 'MX-DIF' => 'Ciudad de México',
        'MX-DUR' => 'Durango', 'MX-GUA' => 'Guanajuato', 'MX-GRO' => 'Guerrero', 'MX-HID' => 'Hidalgo',
        'MX-JAL' => 'Jalisco', 'MX-MEX' => 'Estado de México', 'MX-MIC' => 'Michoacán', 'MX-MOR' => 'Morelos',
        'MX-NAY' => 'Nayarit', 'MX-NLE' => 'Nuevo León', 'MX-OAX' => 'Oaxaca', 'MX-PUE' => 'Puebla',
        'MX-QUE' => 'Querétaro', 'MX-ROO' => 'Quintana Roo', 'MX-SLP' => 'San Luis Potosí', 'MX-SIN' => 'Sinaloa',
        'MX-SON' => 'Sonora', 'MX-TAB' => 'Tabasco', 'MX-TAM' => 'Tamaulipas', 'MX-TLA' => 'Tlaxcala',
        'MX-VER' => 'Veracruz', 'MX-YUC' => 'Yucatán', 'MX-ZAC' => 'Zacatecas',
    ];

    /** ISO 3166-1 alpha-2 → Spanish country names (common set; code kept as fallback). */
    private const COUNTRIES = [
        'MX' => 'México', 'US' => 'Estados Unidos', 'CA' => 'Canadá', 'GT' => 'Guatemala', 'BZ' => 'Belice',
        'SV' => 'El Salvador', 'HN' => 'Honduras', 'NI' => 'Nicaragua', 'CR' => 'Costa Rica', 'PA' => 'Panamá',
        'CO' => 'Colombia', 'VE' => 'Venezuela', 'EC' => 'Ecuador', 'PE' => 'Perú', 'BO' => 'Bolivia',
        'CL' => 'Chile', 'AR' => 'Argentina', 'UY' => 'Uruguay', 'PY' => 'Paraguay', 'BR' => 'Brasil',
        'CU' => 'Cuba', 'DO' => 'República Dominicana', 'PR' => 'Puerto Rico', 'HT' => 'Haití', 'JM' => 'Jamaica',
        'ES' => 'España', 'PT' => 'Portugal', 'FR' => 'Francia', 'GB' => 'Reino Unido', 'IE' => 'Irlanda',
        'DE' => 'Alemania', 'IT' => 'Italia', 'NL' => 'Países Bajos', 'BE' => 'Bélgica', 'CH' => 'Suiza',
        'AT' => 'Austria', 'SE' => 'Suecia', 'NO' => 'Noruega', 'DK' => 'Dinamarca', 'FI' => 'Finlandia',
        'PL' => 'Polonia', 'CZ' => 'Chequia', 'GR' => 'Grecia', 'RO' => 'Rumanía', 'HU' => 'Hungría',
        'RU' => 'Rusia', 'UA' => 'Ucrania', 'TR' => 'Turquía', 'IL' => 'Israel', 'SA' => 'Arabia Saudita',
        'AE' => 'Emiratos Árabes Unidos', 'EG' => 'Egipto', 'MA' => 'Marruecos', 'ZA' => 'Sudáfrica',
        'NG' => 'Nigeria', 'KE' => 'Kenia', 'IN' => 'India', 'PK' => 'Pakistán', 'CN' => 'China',
        'JP' => 'Japón', 'KR' => 'Corea del Sur', 'TW' => 'Taiwán', 'HK' => 'Hong Kong', 'SG' => 'Singapur',
        'TH' => 'Tailandia', 'VN' => 'Vietnam', 'PH' => 'Filipinas', 'ID' => 'Indonesia', 'MY' => 'Malasia',
        'AU' => 'Australia', 'NZ' => 'Nueva Zelanda',
    ];

    public function index(Request $request, UmamiClient $umami)
    {
        $tournaments = Tournament::orderByDesc('starts_on')->get(['id', 'name', 'slug', 'starts_on', 'created_at']);

        $selected = $request->filled('tournament')
            ? $tournaments->firstWhere('slug', $request->string('tournament'))
            : null;

        // Only hit Umami when the user actually presses "Buscar" — opening the
        // page from the sidebar should NOT fire a slow fetch for the first tournament.
        $searched = $request->boolean('buscar') && $selected;

        [$startMs, $endMs, $rangeLabel] = $this->resolveWindow($request, $selected);

        $report = null;
        if ($searched) {
            $path = '/t/' . $selected->slug;
            $event = (string) config('services.umami.register_event');
            $stats = $umami->stats($path, $startMs, $endMs);
            $report = [
                'path' => $path,
                'configured' => $umami->configured(),
                'error' => $stats['error'] ?? null,
                'stats' => ($stats['error'] ?? false) ? null : $stats,
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
            'range' => $request->get('range', 'all'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
            'rangeLabel' => $rangeLabel,
            'searched' => (bool) $request->boolean('buscar'),
            'report' => $report,
        ]);
    }

    /** Stylized one-document brief for a manager: summary + day-by-day tables. */
    public function exportPdf(Request $request, UmamiClient $umami)
    {
        $tournament = Tournament::where('slug', $request->string('tournament'))->firstOrFail();
        [$startMs, $endMs, $rangeLabel] = $this->resolveWindow($request, $tournament);
        $event = (string) config('services.umami.register_event');
        $on = $umami->configured();
        $wantDaily = $on && $request->boolean('daily'); // "Detalle día por día" checkbox (off by default)

        $overallPath = '/t/' . $tournament->slug;
        $overall = $umami->stats($overallPath, $startMs, $endMs);

        $rows = [];
        foreach ($tournament->categories()->orderBy('name')->get() as $c) {
            $path = '/t/' . $tournament->slug . '/' . $c->slug;
            $s = $umami->stats($path, $startMs, $endMs);
            $rows[] = [
                'name' => $c->name,
                'path' => $path,
                'stats' => ($s['error'] ?? false) ? null : $s,
                'daily' => $wantDaily ? $this->dailyLight($umami, $path, $startMs, $endMs) : [],
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.analytics.pdf', [
            'tournament' => $tournament,
            'rangeLabel' => $rangeLabel,
            'overall' => ($overall['error'] ?? false) ? null : $overall,
            'overallPath' => $overallPath,
            'rows' => $rows,
            'eventName' => $event,
            'eventCount' => $on ? $umami->eventCount($event, $startMs, $endMs) : 0,
            'configured' => $on,
            'error' => $overall['error'] ?? null,
            'breakdowns' => $this->breakdowns($umami, $overallPath, $startMs, $endMs),
            'dailyMain' => $wantDaily ? $this->dailyMain($umami, $overallPath, $startMs, $endMs) : [],
            'generatedAt' => now(self::TZ),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-analitica.pdf');
    }

    /**
     * Full daily rows for ONE path (the main page). One pageviews call finds the
     * days that actually have traffic; each gets a stats call for the 5 metrics.
     *
     * @return array<int, array{date:\Carbon\Carbon, stats:?array}>
     */
    private function dailyMain(UmamiClient $umami, string $path, int $startMs, int $endMs): array
    {
        $series = $umami->pageviewsSeries($path, $startMs, $endMs, 'day');
        $rows = [];
        $n = 0;
        foreach (($series['pageviews'] ?? []) as $pt) {
            if ((int) ($pt['y'] ?? 0) <= 0) continue;         // skip empty days
            if (++$n > self::DAILY_CAP) break;
            $day = Carbon::parse(substr((string) $pt['t'], 0, 10), self::TZ)->startOfDay();
            $s = $umami->stats($path, $day->valueOf(), $day->copy()->endOfDay()->valueOf());
            $rows[] = ['date' => $day, 'stats' => ($s['error'] ?? false) ? null : $s];
        }
        return $rows;
    }

    /**
     * Light daily rows (date + pageviews + sessions) for ONE path, straight from
     * the pageviews series — a single call, no per-day stats.
     *
     * @return array<int, array{date:\Carbon\Carbon, pageviews:int, sessions:int}>
     */
    private function dailyLight(UmamiClient $umami, string $path, int $startMs, int $endMs): array
    {
        $series = $umami->pageviewsSeries($path, $startMs, $endMs, 'day');
        $sess = collect($series['sessions'] ?? [])->mapWithKeys(fn($p) => [substr((string) $p['t'], 0, 10) => (int) ($p['y'] ?? 0)]);
        $rows = [];
        foreach (($series['pageviews'] ?? []) as $pt) {
            if ((int) ($pt['y'] ?? 0) <= 0) continue;
            $d = substr((string) $pt['t'], 0, 10);
            $rows[] = ['date' => Carbon::parse($d, self::TZ), 'pageviews' => (int) $pt['y'], 'sessions' => $sess[$d] ?? 0];
        }
        return $rows;
    }

    /**
     * Location / Sources / Environment breakdowns for ONE path.
     * @return array<string, array<int, array{label:string, rows:array}>>
     */
    private function breakdowns(UmamiClient $umami, string $path, int $startMs, int $endMs): array
    {
        if (! $umami->configured()) return [];
        $out = [];
        foreach (self::GROUPS as $group => $types) {
            $subs = [];
            foreach ($types as $type => $label) {
                $rows = array_map(
                    fn($r) => ['name' => $this->label($type, (string) ($r['name'] ?? '')), 'value' => $r['value'] ?? 0],
                    $umami->metrics($type, $path, $startMs, $endMs, 8)
                );
                $subs[] = ['label' => $label, 'rows' => $rows];
            }
            $out[$group] = $subs;
        }
        return $out;
    }

    /** Humanize a raw Umami dimension value (channel/device/country/region) into Spanish. */
    private function label(string $type, string $value): string
    {
        if ($value === '') return '';            // empty → view shows "(directo / ninguno)"
        return match ($type) {
            'channel' => self::CHANNELS[$value] ?? $value,
            'device' => self::DEVICES[$value] ?? $value,
            'country' => self::COUNTRIES[strtoupper($value)] ?? $value,
            'region' => $this->regionName($value),
            default => $value,
        };
    }

    /** ISO 3166-2 region code → full name. Mexican states get full names; others drop the country prefix. */
    private function regionName(string $code): string
    {
        $up = strtoupper($code);
        if (isset(self::MX_REGIONS[$up])) return self::MX_REGIONS[$up];
        $dash = strpos($code, '-');
        return $dash !== false ? substr($code, $dash + 1) : $code;
    }

    /**
     * [startMs, endMs, label]. A custom desde/hasta wins; otherwise the preset
     * range; "Todo" runs from the tournament's created_at to now.
     */
    private function resolveWindow(Request $request, ?Tournament $tournament): array
    {
        $now = now(self::TZ);
        $from = $request->date('from', 'Y-m-d', self::TZ);
        $to = $request->date('to', 'Y-m-d', self::TZ);

        if ($from && $to) {
            return [
                $from->copy()->startOfDay()->valueOf(),
                $to->copy()->endOfDay()->valueOf(),
                $from->format('d/m/Y') . ' – ' . $to->format('d/m/Y'),
            ];
        }

        $range = array_key_exists($request->get('range'), self::RANGES) ? $request->get('range') : 'all';
        $start = match ($range) {
            'all' => ($tournament?->created_at ?? $now->copy()->subYears(2))->copy()->setTimezone(self::TZ)->startOfDay(),
            'today' => $now->copy()->startOfDay(),
            '7d' => $now->copy()->subDays(7)->startOfDay(),
            '90d' => $now->copy()->subDays(90)->startOfDay(),
            default => $now->copy()->subDays(30)->startOfDay(),
        };
        return [$start->valueOf(), $now->valueOf(), self::RANGES[$range]];
    }
}
