<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;

/**
 * Thin read-only client for Umami Cloud (umami.is).
 *
 * Auth is a single API-key header (x-umami-api-key) against api.umami.is/v1.
 * Every method is scoped to one page path (the tournament's public URL) via the
 * version-dependent filter key (config services.umami.path_param — 'url' or
 * 'path'). All calls fail soft: on a missing config or an API error they return
 * an empty shape plus an 'error' string, so the report page degrades instead of
 * throwing.
 */
class UmamiClient
{
    public function __construct(
        private ?string $base = null,
        private ?string $apiKey = null,
        private ?string $websiteId = null,
        private ?string $pathParam = null,
    ) {
        $this->base ??= rtrim((string) config('services.umami.base'), '/');
        $this->apiKey ??= (string) config('services.umami.api_key');
        $this->websiteId ??= (string) config('services.umami.website_id');
        $this->pathParam ??= (string) (config('services.umami.path_param') ?: 'url');
    }

    public function configured(): bool
    {
        return $this->base !== '' && $this->apiKey !== '' && $this->websiteId !== '';
    }

    /** Aggregate stats for a path over [startMs, endMs]: pageviews, visitors, visits, bounces, totaltime. */
    public function stats(string $path, int $startMs, int $endMs): array
    {
        $raw = $this->get("/websites/{$this->websiteId}/stats", [
            'startAt' => $startMs,
            'endAt' => $endMs,
            $this->pathParam => $path,
        ]);
        if (isset($raw['error'])) return $raw;

        // Response shape changed across versions: either flat ints or {value,prev}.
        $num = fn($v) => is_array($v) ? (int) ($v['value'] ?? 0) : (int) $v;
        return [
            'pageviews' => $num($raw['pageviews'] ?? 0),
            'visitors' => $num($raw['visitors'] ?? 0),
            'visits' => $num($raw['visits'] ?? ($raw['sessions'] ?? 0)),
            'bounces' => $num($raw['bounces'] ?? 0),
            'totaltime' => $num($raw['totaltime'] ?? 0),
        ];
    }

    /** Pageviews / sessions time series for a path. Returns ['pageviews'=>[[t,y]], 'sessions'=>[[t,y]]]. */
    public function pageviewsSeries(string $path, int $startMs, int $endMs, string $unit = 'day', string $tz = 'America/Mexico_City'): array
    {
        $raw = $this->get("/websites/{$this->websiteId}/pageviews", [
            'startAt' => $startMs,
            'endAt' => $endMs,
            'unit' => $unit,
            'timezone' => $tz,
            $this->pathParam => $path,
        ]);
        if (isset($raw['error'])) return ['pageviews' => [], 'sessions' => [], 'error' => $raw['error']];

        $pts = fn($rows) => collect($rows ?? [])->map(fn($r) => ['t' => $r['x'] ?? '', 'y' => (int) ($r['y'] ?? 0)])->all();
        return ['pageviews' => $pts($raw['pageviews'] ?? []), 'sessions' => $pts($raw['sessions'] ?? [])];
    }

    /** Top metric rows of a type (referrer, browser, device, path, event, …) for a path: [['name'=>, 'value'=>], …]. */
    public function metrics(string $type, string $path, int $startMs, int $endMs, int $limit = 10): array
    {
        $params = [
            'type' => $type,
            'startAt' => $startMs,
            'endAt' => $endMs,
            'limit' => $limit,
        ];
        // 'event' metrics are site-wide (not path-filtered) in Umami.
        if ($type !== 'event') $params[$this->pathParam] = $path;

        $raw = $this->get("/websites/{$this->websiteId}/metrics", $params);
        if (isset($raw['error'])) return [];

        return collect($raw)->map(fn($r) => ['name' => (string) ($r['x'] ?? ''), 'value' => (int) ($r['y'] ?? 0)])->all();
    }

    /** Count of a named custom event over the window (0 if absent). */
    public function eventCount(string $event, int $startMs, int $endMs): int
    {
        foreach ($this->metrics('event', '', $startMs, $endMs, 100) as $row) {
            if (strcasecmp($row['name'], $event) === 0) return $row['value'];
        }
        return 0;
    }

    private function get(string $path, array $query): array
    {
        if (! $this->configured()) {
            return ['error' => 'Umami no está configurado (define UMAMI_API_KEY y UMAMI_WEBSITE_ID).'];
        }
        try {
            $res = Http::withHeaders(['x-umami-api-key' => $this->apiKey, 'Accept' => 'application/json'])
                ->timeout(12)
                ->get($this->base . $path, array_filter($query, fn($v) => $v !== '' && $v !== null));

            if ($res->failed()) {
                return ['error' => 'Umami respondió ' . $res->status() . '.'];
            }
            return $res->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => 'No se pudo contactar Umami: ' . $e->getMessage()];
        }
    }
}
