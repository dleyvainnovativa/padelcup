@extends('layouts.app')

@section('title', 'Resumen de ranking · ' . $system->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => $system->name, 'url' => route('ranking-systems.show', $system)],
        ['label' => 'Resumen'],
    ]" />

<div class="page-head">
    <div>
        <h1>Resumen por categorías</h1>
        <div class="page-sub">{{ $system->name }}@if($system->owner_label) · {{ $system->owner_label }}@endif</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('ranking-systems.leaderboard', $system) }}" class="btn btn-soft">
            <i class="fa-solid fa-table-list me-1"></i> Tabla general
        </a>
    </div>
</div>

@include('dashboard.partials.flash')

{{-- Combined total first --}}
<div class="tc-card mb-3">
    <div class="tc-card__head"><h3><i class="fa-solid fa-trophy me-1"></i> General (todas las categorías)</h3></div>
    <div class="tc-card__body" style="padding:0;">
        @include('dashboard.rankings.partials.board-table', ['board' => $combined, 'compact' => true])
    </div>
</div>

{{-- One section per category --}}
@forelse($byCategory as $entry)
    <div class="tc-card mb-3">
        <div class="tc-card__head" style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <h3 style="margin:0;">{{ $entry['label'] }}</h3>
            <a href="{{ route('ranking-systems.leaderboard', [$system, 'cat' => $entry['key']]) }}"
               style="font-size:12px;">Ver completa →</a>
        </div>
        <div class="tc-card__body" style="padding:0;">
            @include('dashboard.rankings.partials.board-table', ['board' => $entry['board'], 'compact' => true])
        </div>
    </div>
@empty
    <div class="tc-card"><div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay categorías con puntos.
    </div></div>
@endforelse
@endsection
