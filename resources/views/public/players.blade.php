@extends('layouts.public')

@section('title', 'Jugadores · '.$tournament->name)

@section('content')
<div class="pub-wrap">
    <div class="pub-crumb">
        <a href="{{ route('public.tournament', $tournament) }}"><i class="fa-solid fa-chevron-left"></i> {{ $tournament->name }}</a>
    </div>

    <h1 class="pub-section-title" style="margin-top:6px;">Jugadores</h1>
    <p class="pub-sub" style="margin:0 0 16px;">{{ $rows->count() }} inscripciones · toca un jugador para ver su perfil</p>

    @include('shared.player-directory', [
        'rows' => $rows,
        'categories' => $categories,
        'tournament' => $tournament,
        'linkPlayers' => $linkPlayers,
        'rowClass' => 'pl-dir__row--pub',
    ])
</div>
@endsection
