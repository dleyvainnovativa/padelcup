@extends('layouts.app')

@section('title', 'Panel')

@section('content')
<div class="page-head">
    <div>
        <h1>Panel</h1>
        <div class="page-sub">Hola, {{ auth()->user()->name }} · resumen de tus torneos</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('tournaments.index') }}" class="btn btn-soft"><i class="fa-solid fa-trophy me-1"></i> Mis torneos</a>
        <a href="{{ route('tournaments.create') }}" class="btn btn-accent"><i class="fa-solid fa-plus me-1"></i> Nuevo torneo</a>
    </div>
</div>

{{-- Stat tiles --}}
<div class="row g-3 mb-2">
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-trophy" label="Torneos activos"
            value="{{ $activeCount }}"
            delta="{{ $setupCount > 0 ? $setupCount.' en preparación' : 'al día' }}"
            trend="none" accent />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-people-group" label="Parejas"
            value="{{ $pairCount }}"
            delta="{{ $playerCount }} jugadores" trend="none" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-calendar-day" label="Partidos hoy"
            value="{{ $todayCount }}"
            delta="{{ $upcoming->count() }} próximos" trend="none" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-money-bill-trend-up" label="Recaudado (neto)"
            value="${{ number_format($netCentavos / 100, 0) }}"
            delta="de ${{ number_format($grossCentavos / 100, 0) }} bruto" trend="none" />
    </div>
</div>

{{-- Needs attention --}}
@if($pendingResults > 0)
<div class="dash-alert">
    <i class="fa-solid fa-circle-exclamation"></i>
    <span><strong>{{ $pendingResults }}</strong> {{ $pendingResults === 1 ? 'partido jugado sin resultado' : 'partidos jugados sin resultado' }} capturado.</span>
</div>
@endif

<div class="row g-3">
    {{-- Upcoming matches --}}
    <div class="col-12 col-lg-7">
        <div class="section-title">Próximos partidos</div>
        <div class="tc-card">
            @if($upcoming->isEmpty())
            <div class="tc-card__body" style="color:var(--text-muted);font-size:14px;">
                No hay partidos próximos programados.
            </div>
            @else
            <table class="tc-table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Partido</th>
                        <th>Torneo</th>
                        <th>Cancha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($upcoming as $m)
                    <tr>
                        <td style="white-space:nowrap;font-weight:600;">
                            {{ $m->starts_at->timezone('America/Mexico_City')->translatedFormat('D H:i') }}
                        </td>
                        <td>{{ $m->pairA?->name() ?? '—' }} <span style="color:var(--text-faint);">vs</span> {{ $m->pairB?->name() ?? '—' }}</td>
                        <td>
                            <a href="{{ route('tournaments.show', $m->category->tournament) }}" style="color:var(--accent);text-decoration:none;">
                                {{ \Illuminate\Support\Str::limit($m->category->tournament->name, 22) }}
                            </a>
                        </td>
                        <td style="color:var(--text-muted);">{{ $m->court?->name ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    {{-- My tournaments --}}
    <div class="col-12 col-lg-5">
        <div class="section-title">Mis torneos</div>
        <div class="tc-card">
            @if($tournaments->isEmpty())
            <div class="tc-card__body" style="color:var(--text-muted);font-size:14px;">
                Aún no tienes torneos. <a href="{{ route('tournaments.create') }}">Crea el primero</a>.
            </div>
            @else
            <div class="dash-tourneys">
                @foreach($tournaments->take(6) as $t)
                <a href="{{ route('tournaments.show', $t) }}" class="dash-tourney">
                    <div class="dash-tourney__main">
                        <div class="dash-tourney__name">{{ $t->name }}</div>
                        <div class="dash-tourney__meta">
                            {{ $t->categories_count }} {{ $t->categories_count === 1 ? 'categoría' : 'categorías' }}
                            @if($t->starts_on) · {{ $t->starts_on->translatedFormat('d M') }}@endif
                        </div>
                    </div>
                    <x-pill :variant="$t->isSetup() ? 'neutral' : ($t->isLocked() ? 'accent' : 'ok')" dot>
                        {{ $t->phase->label() }}
                    </x-pill>
                </a>
                @endforeach
            </div>
            @if($tournaments->count() > 6)
            <div class="tc-card__foot">
                <a href="{{ route('tournaments.index') }}" class="btn btn-soft btn-sm">Ver todos ({{ $tournaments->count() }})</a>
            </div>
            @endif
            @endif
        </div>
    </div>
</div>
@endsection