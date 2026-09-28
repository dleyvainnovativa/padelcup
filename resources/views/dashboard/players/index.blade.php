@extends('layouts.app')

@section('title', 'Jugadores')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Jugadores'],
    ]" />

<div class="page-head w-100">
    <div class="w-100">
        <h1>Jugadores</h1>
        <div class="page-sub w-100">{{ $tournament->name }} · {{ $rows->count() }} inscripciones

            <a href="{{ route('tournaments.players.xlsx', $tournament) }}" class="btn btn-accent ms-auto">
                <i class="fa-solid fa-file-excel me-1"></i><span class="btn-label">Exportar jugadores</span>
            </a>
        </div>
        {{-- Exportar jugadores a Excel --}}
    </div>
</div>

@include('dashboard.partials.flash')

<div class="tc-card">
    <div class="tc-card__body">
        @include('shared.player-directory', [
        'rows' => $rows,
        'categories' => $categories,
        'tournament' => $tournament,
        'linkPlayers' => $linkPlayers,
        'rowClass' => 'pl-dir__row--dash',
        ])
    </div>
</div>
@endsection