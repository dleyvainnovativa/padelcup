@extends('layouts.app')

@section('title', 'Calendario')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Calendario'],
    ]" />

<div x-data="{ showPhases: false, showCapacity: false }">
    <div class="page-head">
        <div>
            <h1>Calendario</h1>
            <div class="page-sub">{{ $tournament->name }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('venues.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-location-dot me-1"></i> Canchas</a>
            <button type="button" class="btn btn-soft" @click="showCapacity = !showCapacity">
                <i class="fa-solid fa-calculator me-1"></i> Capacidad
            </button>
            <button type="button" class="btn btn-soft" @click="showPhases = !showPhases">
                <i class="fa-solid fa-clock me-1"></i> Ventanas de fase
            </button>
            <form method="POST" action="{{ route('schedule.auto', $tournament) }}"
                data-confirm="Se programarán automáticamente los partidos sin horario en los espacios libres. ¿Continuar?"
                data-confirm-title="Auto-programar" data-confirm-ok="Programar">
                @csrf
                <button class="btn btn-accent"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Auto-programar</button>
            </form>
            <div x-data="{ open: false }" style="position:relative;">
                <button type="button" class="btn btn-soft" @click="open = !open" @click.outside="open = false">
                    <i class="fa-solid fa-file-pdf me-1"></i> Exportar PDF <i class="fa-solid fa-chevron-down" style="font-size:10px;margin-left:4px;"></i>
                </button>
                <div x-show="open" x-cloak class="pdf-menu">
                    <a href="{{ route('schedule.pdf', $tournament) }}" class="pdf-menu__item"><i class="fa-regular fa-clock me-1"></i> Por horario</a>
                    <a href="{{ route('schedule.pdf', ['tournament' => $tournament, 'order' => 'category']) }}" class="pdf-menu__item"><i class="fa-solid fa-layer-group me-1"></i> Por categoría</a>
                </div>
            </div>
            <form method="POST" action="{{ route('schedule.conflicts', $tournament) }}">
                @csrf
                <button class="btn btn-soft"><i class="fa-solid fa-user-clock me-1"></i> Revisar conflictos</button>
            </form>
            <form method="POST" action="{{ route('schedule.clear', $tournament) }}"
                data-confirm="Se quitarán TODOS los partidos del calendario (los resultados se conservan). ¿Continuar?"
                data-confirm-title="Limpiar calendario" data-confirm-ok="Limpiar" data-confirm-variant="danger">
                @csrf
                <button class="btn btn-soft"><i class="fa-solid fa-trash-can me-1"></i> Limpiar</button>
            </form>
        </div>
    </div>

    {{-- Capacity preview panel --}}
    <div x-show="showCapacity" x-cloak class="tc-card mb-3">
        <div class="tc-card__head">
            <h3><i class="fa-solid fa-calculator me-1"></i> Vista previa de capacidad</h3>
        </div>
        <div class="tc-card__body">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
                {{ $capacity['courts'] }} canchas · partidos de {{ $capacity['duration'] }} min. Los conteos de eliminación se estiman a partir de los clasificados por categoría.
            </p>

            {{-- Per-phase capacity summary --}}
            <div class="tc-table-wrap mb-3">
                <table class="tc-table">
                    <thead>
                        <tr>
                            <th>Fase</th>
                            <th>Partidos</th>
                            <th>Filas (canchas)</th>
                            <th>Horas de cancha</th>
                            <th>Ventana actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($capacity['perPhase'] as $phase => $info)
                        <tr>
                            <td style="font-weight:600;">{{ \App\Support\SchedulePhase::label($phase) }}</td>
                            <td>{{ $info['matches'] }}</td>
                            <td>{{ $info['slotRows'] }}</td>
                            <td class="font-mono">{{ $info['courtHours'] }} h</td>
                            <td>
                                @if($info['fits'] === true)
                                <span style="color:var(--success-text);font-size:12px;"><i class="fa-solid fa-check me-1"></i>Cabe</span>
                                @elseif($info['fits'] === false)
                                <span style="color:var(--danger-text);font-size:12px;"><i class="fa-solid fa-triangle-exclamation me-1"></i>Faltan {{ $info['shortfall'] }}</span>
                                @else
                                <span style="color:var(--text-faint);font-size:12px;">Sin ventana</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Per-category inventory --}}
            <h4 style="font-size:13px;font-weight:600;margin-bottom:8px;">Partidos por categoría</h4>
            <div class="tc-table-wrap">
                <table class="tc-table">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th>Gpos R1</th>
                            <th>Gpos R2</th>
                            @foreach(['r32'=>'32avos','r16'=>'Oct','quarterfinal'=>'4tos','semifinal'=>'SF','final'=>'F'] as $pk => $lbl)
                            @if(($capacity['phaseTotals'][$pk] ?? 0) > 0)<th>{{ $lbl }}</th>@endif
                            @endforeach
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($capacity['rows'] as $row)
                        <tr>
                            <td style="font-weight:500;">{{ $row['category'] }}</td>
                            <td>{{ $row['counts']['_groups_r1'] ?: '—' }}</td>
                            <td>{{ $row['counts']['_groups_r2'] ?: '—' }}</td>
                            @foreach(['r32','r16','quarterfinal','semifinal','final'] as $pk)
                            @if(($capacity['phaseTotals'][$pk] ?? 0) > 0)
                            <td>{{ $row['counts'][$pk] ?: '—' }}</td>
                            @endif
                            @endforeach
                            <td style="font-weight:700;">{{ $row['total'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--border-strong);">
                            <td style="font-weight:700;">Total</td>
                            <td style="font-weight:700;">{{ collect($capacity['rows'])->sum(fn($r) => $r['counts']['_groups_r1']) ?: '—' }}</td>
                            <td style="font-weight:700;">{{ collect($capacity['rows'])->sum(fn($r) => $r['counts']['_groups_r2']) ?: '—' }}</td>
                            @foreach(['r32','r16','quarterfinal','semifinal','final'] as $pk)
                            @if(($capacity['phaseTotals'][$pk] ?? 0) > 0)
                            <td style="font-weight:700;">{{ $capacity['phaseTotals'][$pk] }}</td>
                            @endif
                            @endforeach
                            <td style="font-weight:700;">{{ collect($capacity['rows'])->sum('total') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    {{-- Phase windows config panel --}}
    <div x-show="showPhases" x-cloak class="tc-card mb-3">
        <div class="tc-card__head">
            <h3><i class="fa-solid fa-clock me-1"></i> Ventanas de fase</h3>
        </div>
        <div class="tc-card__body">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
                Define las fechas y horas reservadas para cada fase. Al auto-programar, cada partido se coloca solo dentro de la ventana de su fase. Deja vacía una fase para no restringirla.
            </p>
            <form method="POST" action="{{ route('schedule.phases', $tournament) }}"
                x-data="{
                    proposal: {{ Illuminate\Support\Js::from($proposedWindows) }},
                    applyProposal() {
                        for (const [phase, win] of Object.entries(this.proposal)) {
                            const s = this.$refs['start_' + phase];
                            const e = this.$refs['end_' + phase];
                            if (s) s.value = win.starts_at.replace(' ', 'T');
                            if (e) e.value = win.ends_at.replace(' ', 'T');
                        }
                    }
                  }">
                @csrf
                <div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
                    <div style="max-width:260px;">
                        <label class="form-label" style="font-size:13px;font-weight:500;">Descanso mínimo entre partidos (min)</label>
                        <input type="number" name="min_rest_minutes" min="0" max="240" value="{{ $tournament->min_rest_minutes ?? 30 }}"
                            class="form-control" style="border-radius:var(--radius);">
                    </div>
                    @if(!empty($proposedWindows))
                    <button type="button" class="btn btn-soft btn-sm" @click="applyProposal()">
                        <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generar sugeridas
                    </button>
                    @endif
                </div>
                @forelse($presentPhases as $i => $phase)
                @php $win = ($phaseWindows[$phase] ?? collect())->first(); @endphp
                <div class="row g-2 align-items-end mb-2">
                    <input type="hidden" name="windows[{{ $i }}][phase]" value="{{ $phase }}">
                    <div class="col-12 col-md-3">
                        <label class="form-label" style="font-size:12px;font-weight:600;">{{ \App\Support\SchedulePhase::label($phase) }}</label>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label" style="font-size:11px;color:var(--text-faint);">Inicio</label>
                        <input type="datetime-local" name="windows[{{ $i }}][starts_at]" x-ref="start_{{ $phase }}"
                            value="{{ $win?->starts_at?->format('Y-m-d\TH:i') }}"
                            class="form-control form-control-sm" style="border-radius:var(--radius);">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label" style="font-size:11px;color:var(--text-faint);">Fin</label>
                        <input type="datetime-local" name="windows[{{ $i }}][ends_at]" x-ref="end_{{ $phase }}"
                            value="{{ $win?->ends_at?->format('Y-m-d\TH:i') }}"
                            class="form-control form-control-sm" style="border-radius:var(--radius);">
                    </div>
                </div>
                @empty
                <div style="font-size:13px;color:var(--text-faint);">Genera grupos o llave para ver las fases.</div>
                @endforelse
                @if(!empty($proposedWindows))
                @if($proposalOverflow)
                <p style="font-size:12px;color:var(--danger-text);margin:6px 0 0;">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>Los partidos no caben en los días de juego con las canchas actuales. Considera más canchas, más días, o partidos más cortos.
                </p>
                @endif
                <p style="font-size:11px;color:var(--text-faint);margin:6px 0 0;">
                    "Generar sugeridas" reparte las fases en los días de juego (con 15% de holgura y 30 min entre fases). Revisa y ajusta antes de guardar.
                </p>
                @endif
                <button type="submit" class="btn btn-accent btn-sm mt-2"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar ventanas</button>
            </form>
        </div>
    </div>
</div>

@include('dashboard.partials.flash')
@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

{{-- Conflict check report --}}
@if(session('conflictsChecked'))
@php $conflicts = session('conflicts', []); @endphp
@if(empty($conflicts))
<div class="tc-card mb-3" style="border-color:color-mix(in srgb, var(--success,#30a46c) 40%, transparent);">
    <div class="tc-card__body" style="color:var(--success-text);font-size:13px;">
        <i class="fa-solid fa-circle-check me-1"></i> Sin conflictos: ningún jugador está programado en partidos que se encimen.
    </div>
</div>
@else
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-user-clock me-1"></i> Conflictos de jugadores ({{ count($conflicts) }})</h3>
    </div>
    <div class="tc-card__body" style="display:flex;flex-direction:column;gap:8px;">
        @foreach($conflicts as $c)
        <div class="conflict-row conflict-row--{{ $c['severity'] }}">
            <div class="conflict-row__player">
                <i class="fa-solid {{ $c['severity'] === 'overlap' ? 'fa-triangle-exclamation' : 'fa-clock' }}"></i>
                {{ $c['player'] }}
                <span class="conflict-row__tag">{{ $c['severity'] === 'overlap' ? 'Se encima' : 'Poco descanso' }}</span>
            </div>
            <div class="conflict-row__matches">
                @foreach($c['matches'] as $mi)
                <div class="conflict-row__match">
                    <span class="conflict-row__label">{{ $mi['label'] }}</span>
                    <span class="conflict-row__when">
                        @if($mi['court'])<i class="fa-solid fa-location-dot"></i> {{ $mi['court'] }} · @endif
                        {{ $mi['time'] ?? 'sin hora' }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
        <p style="font-size:11px;color:var(--text-faint);margin:4px 0 0;">
            Mueve o quita uno de los partidos en conflicto para resolverlo, luego vuelve a revisar.
        </p>
    </div>
</div>
@endif
@endif

@if($courts->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Primero <a href="{{ route('venues.index', $tournament) }}">agrega canchas</a> para programar.
    </div>
</div>
@elseif(empty($slots))
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Configura la hora de inicio, fin y duración de partido en
        <a href="{{ route('tournaments.edit', $tournament) }}">los ajustes del torneo</a>.
    </div>
</div>
@else
@php
// Precompute slot start-minutes for range matching.
$step = $tournament->match_duration_minutes ?: 90;
$slotMins = [];
foreach ($slots as $s) {
[$h, $mi] = explode(':', $s);
$slotMins[$s] = ((int) $h) * 60 + (int) $mi;
}

// Bucket each scheduled match into the SLOT whose window contains
// its start time (so a 12:00 match shows in the 11:45 slot, not
// vanishing because 12:00 isn't an exact slot label).
$byCell = [];
foreach ($scheduled as $m) {
$local = $m->starts_at->timezone('America/Mexico_City');
$startMin = $local->hour * 60 + $local->minute;

// Find the slot this start time falls into.
$matchedSlot = null;
foreach ($slotMins as $label => $min) {
if ($startMin >= $min && $startMin < $min + $step) {
    $matchedSlot=$label;
    break;
    }
    }
    // Off-grid (before first / after last slot): clamp to nearest.
    if ($matchedSlot===null && ! empty($slotMins)) {
    $matchedSlot=$startMin < reset($slotMins)
    ? array_key_first($slotMins)
    : array_key_last($slotMins);
    }

    if ($matchedSlot !==null) {
    $key=$m->court_id.'|'.$local->format('Y-m-d').'|'.$matchedSlot;
    $byCell[$key][] = $m; // a cell can hold more than one match
    }
    }
    $boardData = [
    'placeUrl' => route('schedule.place', $tournament),
    'unplaceUrl' => route('schedule.unplace', $tournament),
    'duration' => $tournament->match_duration_minutes,
    'courts' => $courts->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
    'scheduled' => $scheduled->mapWithKeys(fn ($m) => [$m->id => [
    'id' => $m->id,
    'context' => $m->contextLabel(),
    'a' => $m->sideLabel('a'),
    'b' => $m->sideLabel('b'),
    'ready' => $m->isReadyForResult(),
    'status' => $m->scheduleStatus(),
    'sets' => $m->sets ?? [],
    'confirmUrl' => route('results.confirm', [$tournament, $m->category_id, $m->id]),
    'editUrl' => route('results.edit', [$tournament, $m->category_id, $m->id]),
    'courtName' => $courts->firstWhere('id', $m->court_id)?->name,
    ]])->all(),
    'unscheduled' => $unscheduled->map(fn ($m) => [
    'id' => $m->id,
    'category' => $m->category->name,
    'context' => $m->contextLabel(),
    'a' => $m->sideLabel('a'),
    'b' => $m->sideLabel('b'),
    'players' => $m->playerIds(),
    ])->values()->all(),
    ];
    @endphp

    <div class="sched" data-sched-board
        data-sched-config='@json($boardData)'
        x-data="{
                day: sessionStorage.getItem('sched_day_{{ $tournament->id }}') || '{{ $days->first()->format('Y-m-d') }}',
                setDay(d) { this.day = d; sessionStorage.setItem('sched_day_{{ $tournament->id }}', d); }
             }"
        x-init="
                // Validate stored day is within this tournament's days.
                const valid = @json($days->map->format('Y-m-d')->values());
                if (!valid.includes(day)) { day = valid[0]; }
             ">
        <div class="sched-days mb-3">
            @foreach($days as $day)
            <button type="button" class="sched-day"
                :class="{ 'is-active': day === '{{ $day->format('Y-m-d') }}' }"
                @click="setDay('{{ $day->format('Y-m-d') }}')">
                {{ $day->locale('es')->isoFormat('ddd D MMM') }}
            </button>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-3">
                <div class="tc-card">
                    <div class="tc-card__head">
                        <h3>Sin programar</h3>
                    </div>
                    <div class="tc-card__body" id="sched-tray" style="display:flex;flex-direction:column;gap:6px;min-height:60px;">
                        @forelse($unscheduled as $m)
                        <div class="sched-chip" draggable="true"
                            data-match-id="{{ $m->id }}"
                            data-title="{{ $m->sideLabel('a') }} vs {{ $m->sideLabel('b') }}">
                            <span class="sched-chip__context">{{ $m->contextLabel() }}</span>
                            <div>{{ $m->sideLabel('a') }} <span style="color:var(--text-faint);">vs</span> {{ $m->sideLabel('b') }}</div>
                        </div>
                        @empty
                        <div style="font-size:12px;color:var(--text-faint);">Todo está programado.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-9">
                <div class="tc-card">
                    <div class="sched-grid-wrap">
                        <table class="sched-grid">
                            <thead>
                                <tr>
                                    <th class="sched-grid__timecol">Hora</th>
                                    @foreach($courts as $court)
                                    <th>{{ $court->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($slots as $slot)
                                <tr>
                                    <td class="sched-grid__timecol font-mono">{{ $slot }}</td>
                                    @foreach($courts as $court)
                                    <td class="sched-cell" data-cell
                                        data-court="{{ $court->id }}"
                                        data-slot="{{ $slot }}"
                                        x-bind:data-date="day">
                                        <template x-for="d in []"></template>
                                        @foreach($days as $day)
                                        @php $key = $court->id.'|'.$day->format('Y-m-d').'|'.$slot; $cellMatches = $byCell[$key] ?? []; @endphp
                                        <div x-show="day === '{{ $day->format('Y-m-d') }}'" data-day="{{ $day->format('Y-m-d') }}">
                                            @forelse($cellMatches as $m)
                                            @php
                                            $status = $m->scheduleStatus();
                                            $mLocal = $m->starts_at->timezone('America/Mexico_City');
                                            $offGrid = $mLocal->format('H:i') !== $slot;
                                            @endphp
                                            <div class="sched-match is-{{ $status }} {{ $offGrid ? 'is-offgrid' : '' }}"
                                                data-match-id="{{ $m->id }}"
                                                data-ready="{{ $m->isReadyForResult() ? '1' : '0' }}"
                                                data-status="{{ $status }}"
                                                draggable="true">
                                                <div class="sched-match__context">
                                                    {{ $m->contextLabel() }}
                                                    @if($offGrid)<span class="sched-match__time" title="Horario fuera de la cuadrícula">{{ $mLocal->format('H:i') }}</span>@endif
                                                </div>
                                                <div style="font-weight:600;">{{ $m->sideLabel('a') }}</div>
                                                <div style="font-size:10px;color:var(--text-faint);">vs</div>
                                                <div style="font-weight:600;">{{ $m->sideLabel('b') }}</div>
                                                @if($status === 'played' && $m->sets)
                                                <div class="sched-match__scores">
                                                    @foreach($m->sets as $i => $s)
                                                    <span class="sched-score-badge">{{ $s[0] }}-{{ $s[1] }}</span>
                                                    @endforeach
                                                </div>
                                                @endif
                                                <button type="button" class="sched-match__remove" data-unplace="{{ $m->id }}" title="Quitar">&times;</button>
                                            </div>
                                            @empty
                                            <span class="sched-cell__add"><i class="fa-solid fa-plus"></i></span>
                                            @endforelse
                                        </div>
                                        @endforeach
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div style="font-size:12px;color:var(--text-faint);margin-top:8px;">
                    <span class="d-none d-md-inline">Arrastra un partido a una celda libre.</span>
                    <span class="d-md-none">Toca un partido y luego una celda libre.</span>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endsection