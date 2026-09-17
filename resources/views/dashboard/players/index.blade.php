@extends('layouts.app')

@section('title', 'Jugadores')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Jugadores'],
    ]" />

<div class="page-head">
    <div>
        <h1>Jugadores</h1>
        <div class="page-sub">{{ $tournament->name }} · {{ $rows->count() }} inscripciones</div>
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
