@extends('layouts.app')

@section('title', 'Sedes y canchas')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Sedes y canchas'],
    ]" />

<div class="page-head">
    <div>
        <h1>Sedes y canchas</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-calendar-days me-1"></i> Ir al calendario</a>
</div>

@include('dashboard.partials.flash')
@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--bg-subtle);color:var(--text-muted);">
    <i class="fa-solid fa-circle-info me-1"></i>
    El horario de cada cancha se crea automáticamente con la ventana de juego del torneo
    ({{ \Illuminate\Support\Str::of($tournament->play_start)->substr(0,5) }}–{{ \Illuminate\Support\Str::of($tournament->play_end)->substr(0,5) }},
    partidos de {{ $tournament->match_duration_minutes }} min).
    <a href="{{ route('tournaments.edit', $tournament) }}">Cambiar en ajustes</a>.
    <form method="POST" action="{{ route('availability.resync', $tournament) }}" class="d-inline">
        @csrf
        <button class="btn btn-soft btn-sm ms-2">Actualizar horarios</button>
    </form>
</div>

<div class="tc-card mb-3">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('venues.store', $tournament) }}" class="d-flex gap-2 flex-wrap align-items-end">
            @csrf
            <div>
                <label class="form-label" style="font-size:12px;">Nueva sede</label>
                <input type="text" name="name" placeholder="Nombre de la sede" required class="form-control" style="border-radius:var(--radius);">
            </div>
            <div style="flex:1;min-width:180px;">
                <label class="form-label" style="font-size:12px;">Dirección (opcional)</label>
                <input type="text" name="address" class="form-control" style="border-radius:var(--radius);">
            </div>
            <button class="btn btn-accent">Agregar sede</button>
        </form>
    </div>
</div>

@forelse($tournament->venues as $venue)
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3>{{ $venue->name }}</h3>
        <span style="font-size:12px;color:var(--text-faint);">{{ $venue->address }}</span>
    </div>
    <div class="tc-card__body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($venue->courts as $court)
            <div class="d-flex align-items-center gap-2" style="border:1px solid var(--border);border-radius:var(--radius);padding:6px 10px;">
                <span style="font-size:13px;font-weight:600;">{{ $court->name }}</span>
                <form method="POST" action="{{ route('courts.destroy', [$tournament, $court]) }}"
                    data-confirm="¿Eliminar «{{ $court->name }}»? Se quitará del calendario."
                    data-confirm-title="Eliminar cancha" data-confirm-variant="danger" data-confirm-ok="Eliminar">
                    @csrf @method('DELETE')
                    <button class="btn btn-soft btn-sm" style="color:var(--danger-text);padding:0 6px;">×</button>
                </form>
            </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('courts.store', [$tournament, $venue]) }}" class="d-flex gap-2 align-items-end">
            @csrf
            <div>
                <label class="form-label" style="font-size:12px;">Nueva cancha</label>
                <input type="text" name="name" placeholder="Cancha 1" required class="form-control form-control-sm" style="border-radius:var(--radius);">
            </div>
            <button class="btn btn-soft btn-sm">Agregar cancha</button>
        </form>
    </div>
</div>
@empty
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay sedes. Agrega una para empezar a definir canchas.
    </div>
</div>
@endforelse
@endsection