@extends('layouts.app')

@section('title', 'Analítica (admin)')

@section('content')
<div class="page-head">
    <div>
        <h1>Analítica</h1>
        <div class="page-sub">Tráfico de la página pública del torneo (Umami)</div>
    </div>
</div>

@include('dashboard.partials.flash')

{{-- Nothing is fetched until the manager presses Buscar (the fetch is slow), so
     opening the page from the sidebar stays instant. Exportar PDF submits the
     same form to the PDF route and downloads without touching the page. --}}
<form method="GET" action="{{ route('admin.analytics.index') }}" class="row g-2 align-items-end mb-3">
    <input type="hidden" name="buscar" value="1">
    <div class="col-12 col-md-4">
        <label class="form-label" style="font-size:13px;font-weight:500;">Torneo</label>
        <select name="tournament" class="form-select" style="border-radius:var(--radius);">
            <option value="">— elige —</option>
            @foreach($tournaments as $t)
                <option value="{{ $t->slug }}" @selected($selected && $selected->id === $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-md-2">
        <label class="form-label" style="font-size:13px;font-weight:500;">Rango</label>
        <select name="range" class="form-select" style="border-radius:var(--radius);">
            @foreach($ranges as $key => $label)
                <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-3 col-md-2">
        <label class="form-label" style="font-size:13px;font-weight:500;">Desde</label>
        <input type="date" name="from" value="{{ $from }}" class="form-control" style="border-radius:var(--radius);">
    </div>
    <div class="col-3 col-md-2">
        <label class="form-label" style="font-size:13px;font-weight:500;">Hasta</label>
        <input type="date" name="to" value="{{ $to }}" class="form-control" style="border-radius:var(--radius);">
    </div>
    <div class="col-12 col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-dark flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i> Buscar</button>
        <button type="submit" class="btn btn-soft" formaction="{{ route('admin.analytics.pdf') }}" formtarget="_blank"
                title="Exportar PDF"><i class="fa-solid fa-file-pdf"></i></button>
    </div>
    <div class="col-12 d-flex align-items-center gap-2" style="font-size:12px;">
        <input type="checkbox" name="daily" value="1" id="daily-cb" class="form-check-input mt-0" @checked(request()->boolean('daily'))>
        <label for="daily-cb" style="cursor:pointer;">Incluir <strong>detalle día por día</strong> en el PDF <span style="color:var(--muted);">(más lento; solo sale en el PDF)</span></label>
    </div>
    <div class="col-12" style="font-size:11px;color:var(--muted);">Desde/Hasta tienen prioridad sobre el rango. «Todo» = desde que se creó el torneo.</div>
</form>

@if($tournaments->isEmpty())
    <div class="tc-card"><div class="tc-card__body">No hay torneos todavía.</div></div>
@elseif(! $searched || ! $selected)
    <div class="tc-card"><div class="tc-card__body" style="color:var(--muted);">
        <i class="fa-solid fa-magnifying-glass me-1"></i> Elige un torneo y un rango, y haz clic en <strong>Buscar</strong>.
        El detalle día por día está en <strong>Exportar PDF</strong>.
    </div></div>
@elseif($report && ! $report['configured'])
    <div class="tc-card"><div class="tc-card__body">
        <strong>Umami no está configurado.</strong>
        Define <code>UMAMI_API_KEY</code> y <code>UMAMI_WEBSITE_ID</code> en tu <code>.env</code> para ver el reporte.
    </div></div>
@else
    <div class="section-title">{{ $selected->name }}</div>
    <div class="mb-2" style="font-size:13px;color:var(--muted);">
        <i class="fa-solid fa-link"></i> Página principal del torneo · <code>{{ $report['path'] }}</code>
        <div style="font-size:11px;">Estos números son de la página principal del torneo — no la suma de las categorías (cada categoría tiene su propia fila abajo).</div>
    </div>

    @if($report['error'])
        <div class="tc-card mb-3"><div class="tc-card__body" style="color:#b3261e;">{{ $report['error'] }}</div></div>
    @endif

    @php
        $s = $report['stats'] ?? [];
        $visits = $s['visits'] ?? 0;
        $bounce = $visits > 0 ? round(($s['bounces'] ?? 0) / $visits * 100) : 0;
        $avg = $visits > 0 ? round(($s['totaltime'] ?? 0) / $visits) : 0;
        $avgTxt = $avg >= 60 ? intdiv($avg, 60) . 'm ' . ($avg % 60) . 's' : $avg . 's';
    @endphp

    <div class="row g-3 mb-1">
        @foreach([
            ['Visitas', $s['pageviews'] ?? 0, 'fa-eye'],
            ['Visitantes', $s['visitors'] ?? 0, 'fa-user'],
            ['Sesiones', $visits, 'fa-signal'],
            ['Rebote', $bounce . '%', 'fa-arrow-right-from-bracket'],
            ['Tiempo medio', $avgTxt, 'fa-clock'],
            [ucfirst($report['event_name']), $report['event_count'], 'fa-bolt'],
        ] as [$label, $value, $icon])
        <div class="col-6 col-md-4 col-lg-2">
            <div class="tc-card h-100"><div class="tc-card__body text-center">
                <i class="fa-solid {{ $icon }}" style="color:var(--accent);"></i>
                <div style="font-size:22px;font-weight:700;color:var(--accent);line-height:1.1;margin-top:4px;">{{ is_int($value) ? number_format($value) : $value }}</div>
                <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;">{{ $label }}</div>
            </div></div>
        </div>
        @endforeach
    </div>

    <div class="section-title mt-2">Visitas por día</div>
    <div class="tc-card mb-3"><div class="tc-card__body">
        @php $series = $report['series']['pageviews'] ?? []; $max = max([1, ...array_map(fn($p) => $p['y'], $series)]); @endphp
        @if(count($series) === 0)
            <div style="color:var(--muted);">Sin datos en el rango.</div>
        @else
            <svg viewBox="0 0 {{ max(100, count($series) * 14) }} 120" preserveAspectRatio="none" style="width:100%;height:160px;">
                @foreach($series as $i => $p)
                    @php $h = round(($p['y'] / $max) * 104); @endphp
                    <rect x="{{ $i * 14 + 2 }}" y="{{ 110 - $h }}" width="10" height="{{ max(1, $h) }}" rx="2" fill="var(--accent)"></rect>
                @endforeach
            </svg>
            <div style="font-size:11px;color:var(--muted);">Máximo diario: {{ $max }} visitas</div>
        @endif
    </div></div>

    {{-- Location / Sources / Environment, each with its subsections (countries/regions/cities, referrers/channels, browsers/os/devices). --}}
    @foreach($report['breakdowns'] as $group => $subs)
        <div class="section-title">{{ $group }}</div>
        <div class="row g-3 mb-2">
            @foreach($subs as $sub)
            <div class="col-12 col-md-6 col-lg-4">
                <div class="tc-card h-100"><div class="tc-card__body">
                    <div style="font-size:12px;font-weight:600;color:var(--accent);margin-bottom:6px;text-transform:uppercase;letter-spacing:.03em;">{{ $sub['label'] }}</div>
                    @forelse(array_slice($sub['rows'], 0, 8) as $r)
                        <div class="d-flex justify-content-between" style="padding:3px 0;border-bottom:1px solid var(--border);">
                            <span style="font-size:13px;">{{ $r['name'] !== '' ? $r['name'] : '(directo / ninguno)' }}</span>
                            <strong style="font-size:13px;color:var(--accent);">{{ number_format($r['value']) }}</strong>
                        </div>
                    @empty
                        <div style="color:var(--muted);font-size:13px;">Sin datos.</div>
                    @endforelse
                </div></div>
            </div>
            @endforeach
        </div>
    @endforeach

    {{-- Glossary --}}
    <div class="section-title">Definiciones</div>
    <div class="tc-card mb-3"><div class="tc-card__body">
        <div class="row">
            @foreach([
                ['Visitas', 'Páginas vistas en total (cada carga de página cuenta).'],
                ['Visitantes', 'Personas únicas, contadas por dispositivo y navegador.'],
                ['Sesiones', 'Visitas: la actividad de un visitante hasta ~30 min de inactividad.'],
                ['Rebote', '% de sesiones que vieron una sola página y se fueron.'],
                ['Tiempo medio', 'Duración media de una visita.'],
                [ucfirst($report['event_name']), 'Evento personalizado «'.$report['event_name'].'» del sitio completo (no por página).'],
                ['Referentes', 'Sitios web que enviaron el tráfico.'],
                ['Canales', 'Tipo de origen agrupado: directo, búsqueda, social, etc.'],
                ['Países / Regiones / Ciudades', 'Ubicación aproximada del visitante.'],
                ['Navegadores / Sistema / Dispositivos', 'Entorno desde el que visitan.'],
            ] as [$term, $def])
            <div class="col-12 col-md-6" style="padding:4px 0;">
                <strong style="color:var(--accent);">{{ $term }}</strong>
                <span style="color:var(--muted);font-size:13px;"> — {{ $def }}</span>
            </div>
            @endforeach
        </div>
    </div></div>
@endif
@endsection
