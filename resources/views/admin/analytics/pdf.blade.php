@php
    $fmt = fn($n) => number_format((int) $n);
    $bounce = function ($s) {
        $v = (int) ($s['visits'] ?? 0);
        return $v > 0 ? round(($s['bounces'] ?? 0) / $v * 100) . '%' : '—';
    };
    $avg = function ($s) {
        $v = (int) ($s['visits'] ?? 0);
        if ($v <= 0) return '—';
        $a = round(($s['totaltime'] ?? 0) / $v);
        return $a >= 60 ? intdiv($a, 60) . 'm ' . ($a % 60) . 's' : $a . 's';
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 32px 34px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; color: #1a1a2e; font-size: 11px; }

    .head { border-bottom: 2.5px solid #0e3b2e; padding-bottom: 12px; margin-bottom: 16px; }
    .head img { height: 30px; }
    .head .r { float: right; text-align: right; color: #6b756f; font-size: 10px; margin-top: 4px; }
    .head h1 { font-size: 19px; margin: 10px 0 2px; color: #0e3b2e; }
    .head .sub { font-size: 11px; color: #6b756f; }

    .tiles { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 4px 0 18px; }
    .tiles td {
        width: 16.6%; background: #f4f6f5; border: 0.7px solid #dde4e0;
        border-radius: 6px; padding: 9px 6px; text-align: center; vertical-align: middle;
    }
    .tiles .v { font-size: 20px; font-weight: bold; color: #0e3b2e; }
    .tiles .l { font-size: 8.5px; color: #6b756f; text-transform: uppercase; letter-spacing: .03em; }

    .section { font-size: 12px; font-weight: bold; color: #0e3b2e; margin: 6px 0 6px; }
    table.cat { width: 100%; border-collapse: collapse; }
    table.cat th {
        background: #0e3b2e; color: #fff; font-size: 9.5px; text-transform: uppercase;
        letter-spacing: .03em; padding: 6px 8px; text-align: left;
    }
    table.cat th.n, table.cat td.n { text-align: right; }
    table.cat td { border-bottom: 0.7px solid #e6ebe8; padding: 6px 8px; font-size: 10.5px; vertical-align: top; }
    table.cat tr:nth-child(even) td { background: #fafbfa; }
    .cat .name { font-weight: bold; color: #1a1a2e; }
    .cat .url { color: #8a948f; font-size: 8.5px; }
    .muted { color: #9aa29d; font-style: italic; }

    .section-note { font-size: 9.5px; color: #8a948f; margin: -2px 0 6px; }

    table.brk { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 8px; }
    table.brk > tr > td { width: 33%; vertical-align: top; }
    .brk-h { font-size: 9px; font-weight: bold; color: #0e3b2e; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 3px; }
    .brk-row { font-size: 10px; padding: 2.5px 0; border-bottom: 0.6px solid #eef2f0; }
    .brk-row span { color: #1a1a2e; }
    .brk-row b { float: right; color: #0e3b2e; }

    table.gloss { width: 100%; border-collapse: collapse; }
    table.gloss td.gt { width: 50%; padding: 3px 8px 3px 0; font-size: 9.5px; vertical-align: top; }
    table.gloss td.gt b { color: #0e3b2e; }
    table.gloss td.gt span { color: #6b756f; }

    .note { margin-top: 14px; font-size: 9.5px; color: #8a948f; }
    .foot { margin-top: 10px; border-top: 0.7px solid #e6ebe8; padding-top: 6px; font-size: 9px; color: #9aa29d; }
</style>
</head>
<body>
    <div class="head">
        <div class="r">
            Reporte de analítica<br>
            {{ $rangeLabel }} · {{ $generatedAt->translatedFormat('d M Y, H:i') }}
        </div>
        <img src="{{ public_path('img/voleo-logo.png') }}" alt="Voleo">
        <h1>{{ $tournament->name }}</h1>
        <div class="sub">Tráfico de la página pública · <span style="color:#0e3b2e;">{{ $overallPath }}</span></div>
    </div>

    @if(! $configured)
        <p>Umami no está configurado (define <b>UMAMI_API_KEY</b> y <b>UMAMI_WEBSITE_ID</b>).</p>
    @else
        @if($error)<p class="muted">{{ $error }}</p>@endif

        <div class="section">Página principal del torneo</div>
        <div class="section-note">Números de la página principal (<code>{{ $overallPath }}</code>) — no la suma de las categorías.</div>
        <table class="tiles">
            <tr>
                <td><div class="v">{{ $overall ? $fmt($overall['pageviews'] ?? 0) : '—' }}</div><div class="l">Visitas</div></td>
                <td><div class="v">{{ $overall ? $fmt($overall['visitors'] ?? 0) : '—' }}</div><div class="l">Visitantes</div></td>
                <td><div class="v">{{ $overall ? $fmt($overall['visits'] ?? 0) : '—' }}</div><div class="l">Sesiones</div></td>
                <td><div class="v">{{ $overall ? $bounce($overall) : '—' }}</div><div class="l">Rebote</div></td>
                <td><div class="v">{{ $overall ? $avg($overall) : '—' }}</div><div class="l">Tiempo medio</div></td>
                <td><div class="v">{{ $fmt($eventCount) }}</div><div class="l">{{ ucfirst($eventName) }}</div></td>
            </tr>
        </table>

        <div class="section">Por categoría</div>
        <table class="cat">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th class="n">Visitas</th>
                    <th class="n">Visitantes</th>
                    <th class="n">Sesiones</th>
                    <th class="n">Rebote</th>
                    <th class="n">Tiempo medio</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <div class="name">{{ $row['name'] }}</div>
                            <div class="url">{{ $row['path'] }}</div>
                        </td>
                        @if($row['stats'])
                            <td class="n">{{ $fmt($row['stats']['pageviews'] ?? 0) }}</td>
                            <td class="n">{{ $fmt($row['stats']['visitors'] ?? 0) }}</td>
                            <td class="n">{{ $fmt($row['stats']['visits'] ?? 0) }}</td>
                            <td class="n">{{ $bounce($row['stats']) }}</td>
                            <td class="n">{{ $avg($row['stats']) }}</td>
                        @else
                            <td class="n muted" colspan="5">sin datos</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">Este torneo no tiene categorías.</td></tr>
                @endforelse
            </tbody>
        </table>

        @foreach(($breakdowns ?? []) as $group => $subs)
            <div class="section">{{ $group }}</div>
            <table class="brk"><tr>
                @foreach($subs as $sub)
                <td>
                    <div class="brk-h">{{ $sub['label'] }}</div>
                    @forelse(array_slice($sub['rows'], 0, 8) as $r)
                        <div class="brk-row">
                            <span>{{ $r['name'] !== '' ? $r['name'] : '(directo / ninguno)' }}</span>
                            <b>{{ $fmt($r['value']) }}</b>
                        </div>
                    @empty
                        <div class="muted" style="font-size:9.5px;">Sin datos.</div>
                    @endforelse
                </td>
                @endforeach
            </tr></table>
        @endforeach

        <div class="section">Definiciones</div>
        <table class="gloss">
            @foreach([
                ['Visitas', 'Páginas vistas en total (cada carga cuenta).'],
                ['Visitantes', 'Personas únicas (por dispositivo y navegador).'],
                ['Sesiones', 'Visitas: actividad de un visitante hasta ~30 min de inactividad.'],
                ['Rebote', '% de sesiones de una sola página.'],
                ['Tiempo medio', 'Duración media de la visita.'],
                [ucfirst($eventName), 'Evento «'.$eventName.'» del sitio completo (no por página).'],
                ['Referentes', 'Sitios que enviaron el tráfico.'],
                ['Canales', 'Tipo de origen: directo, búsqueda, social, etc.'],
                ['Países / Regiones / Ciudades', 'Ubicación aproximada del visitante.'],
                ['Navegadores / Sistema / Dispositivos', 'Entorno desde el que visitan.'],
            ] as $i => $pair)
                @if($i % 2 === 0)<tr>@endif
                    <td class="gt"><b>{{ $pair[0] }}</b> — <span>{{ $pair[1] }}</span></td>
                @if($i % 2 === 1)</tr>@endif
            @endforeach
        </table>

        <div class="note">
            Ubicación, orígenes y entorno corresponden a la página principal del torneo.
            El conteo de «{{ $eventName }}» es del sitio completo en el rango.
        </div>
    @endif

    <div class="foot">Voleo · {{ $tournament->name }} · Generado {{ $generatedAt->translatedFormat('d M Y, H:i') }}</div>
</body>
</html>
