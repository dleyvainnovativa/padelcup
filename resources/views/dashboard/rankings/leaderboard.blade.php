@extends('layouts.app')

@section('title', 'Ranking · ' . $system->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => $system->name, 'url' => route('ranking-systems.show', $system)],
        ['label' => 'Tabla'],
    ]" />

<div class="page-head">
    <div>
        <h1>Tabla de posiciones</h1>
        <div class="page-sub">
            {{ $system->name }}@if($system->owner_label) · {{ $system->owner_label }}@endif
            @if($activeTourLabel) · {{ $activeTourLabel }}@endif
            @if(($activeLabel ?? 'General') !== 'General') · {{ $activeLabel }}@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('public.rankings.show', $system) }}" class="btn btn-soft" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver pública
        </a>
    </div>
</div>

@include('dashboard.partials.flash')

@include('dashboard.rankings.partials.filter-bar', ['routeName' => 'ranking-systems.leaderboard'])

<div class="tc-card">
    <div class="tc-card__body" style="padding:0;">
        @include('dashboard.rankings.partials.board-table', ['board' => $board, 'scope' => $activeCat ?? 'all'])
    </div>
</div>

<p style="font-size:11px;color:var(--text-faint);margin-top:10px;">
    Filtra por torneo y luego por categoría. En “Todos los torneos” las categorías con el mismo nombre se combinan; el “Resumen” muestra la vista combinada por categoría.
</p>
@endsection
