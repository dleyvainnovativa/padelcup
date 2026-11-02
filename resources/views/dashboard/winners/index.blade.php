@extends('layouts.app')

@section('title', 'Ganadores')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Ganadores'],
    ]" />

<div class="page-head">
    <div>
        <h1>Ganadores</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <span class="win-count"><i class="fa-solid fa-trophy me-1"></i> {{ $decidedCount }} definida{{ $decidedCount === 1 ? '' : 's' }}@if($pendingCount) · {{ $pendingCount }} pendiente{{ $pendingCount === 1 ? '' : 's' }}@endif</span>
        <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft">
            <i class="fa-solid fa-calendar-days me-1"></i><span class="btn-label">Calendario</span>
        </a>
        <a href="{{ route('winners.export', $tournament) }}" class="btn btn-accent">
            <i class="fa-solid fa-file-excel me-1"></i> Exportar Excel
        </a>
    </div>
</div>

@error('export')<div class="alert alert-danger">{{ $message }}</div>@enderror

@if($summaries->isEmpty())
    <div class="tc-card"><div class="tc-card__body text-muted">Este torneo no tiene categorías.</div></div>
@else
<div class="win-grid">
    @foreach($summaries as $p)
    <div class="tc-card win-card {{ $p['decided'] ? 'is-decided' : '' }}">
        <div class="win-card__head">
            <h3>{{ $p['category'] }}</h3>
            @if(! $p['has_bracket'])
                <span class="win-badge win-badge--muted">Sin llave</span>
            @elseif($p['decided'])
                <span class="win-badge win-badge--ok">Definido</span>
            @else
                <span class="win-badge win-badge--pending">Pendiente</span>
            @endif
        </div>

        @if($p['decided'])
        <ul class="win-podium">
            <li class="win-podium__row win-podium__row--1">
                <span class="win-podium__pos"><i class="fa-solid fa-trophy"></i> 1º</span>
                <span class="win-podium__name">{{ $p['champion'] }}</span>
            </li>
            @if($p['runner_up'])
            <li class="win-podium__row win-podium__row--2">
                <span class="win-podium__pos"><i class="fa-solid fa-medal"></i> 2º</span>
                <span class="win-podium__name">{{ $p['runner_up'] }}</span>
            </li>
            @endif
            @if($p['third'])
            <li class="win-podium__row win-podium__row--3">
                <span class="win-podium__pos"><i class="fa-solid fa-medal"></i> 3º</span>
                <span class="win-podium__name">{{ $p['third'] }}</span>
            </li>
            @endif
        </ul>
        @if($p['final_score'])
        <div class="win-card__score">Final: <span class="font-mono">{{ $p['final_score'] }}</span></div>
        @endif
        @else
        <div class="win-card__empty">
            {{ $p['has_bracket'] ? 'La final aún no se confirma.' : 'Esta categoría no tiene fase de eliminación.' }}
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif
@endsection
