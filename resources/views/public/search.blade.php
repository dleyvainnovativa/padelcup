@extends('layouts.public')

@section('title', 'Buscar')

@section('content')
<div class="pp">
    <div class="pp-head">
        <!-- <div class="lp-aurora" aria-hidden="true">
            <span class="lp-aurora__blob lp-aurora__blob--1"></span>
            <span class="lp-aurora__blob lp-aurora__blob--3"></span>
        </div> -->
        <div class="pp-head__inner">
            <h1>Buscar</h1>
            <p>Encuentra jugadores o torneos publicados.</p>
            <form method="GET" action="{{ route('public.search') }}" class="pp-search">
                <div class="pp-search__field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Nombre de jugador o torneo…" autocomplete="off" autofocus>
                </div>
                <button type="submit" class="pp-btn pp-btn--primary">Buscar</button>
                @if($q !== '')
                <a href="{{ route('public.search') }}" class="pp-btn pp-btn--ghost">Limpiar</a>
                @endif
            </form>
        </div>
    </div>

    <div class="pp-body">
        @if($q !== '' && mb_strlen($q) < 2)
            <div class="pp-empty">Escribe al menos 2 letras para buscar.</div>
    @elseif($q !== '' && $players->isEmpty() && $tournaments->isEmpty())
    <div class="pp-empty">No se encontraron resultados para "{{ $q }}".</div>
    @endif

    @if($tournaments->isNotEmpty())
    <h2 class="pp-section-title"><i class="fa-solid fa-trophy"></i> Torneos</h2>
    <div class="pp-grid">
        @foreach($tournaments as $t)
        @include('public.partials.tournament-card', ['t' => $t, 'live' => false])
        @endforeach
    </div>
    @endif

    @if($players->isNotEmpty())
    <h2 class="pp-section-title"><i class="fa-solid fa-user"></i> Jugadores</h2>
    <div class="pp-player-list">
        @foreach($players as $person)
        <div class="pp-player-row">
            <span class="pp-player-row__name">
                <span class="pp-player-row__avatar">{{ mb_strtoupper(mb_substr($person['name'], 0, 1)) }}</span>
                {{ $person['name'] }}
            </span>
            <span class="pp-player-row__links">
                @foreach($person['tournaments'] as $t)
                <a href="{{ route('public.player', [$t['slug'], $t['player_id']]) }}" class="pp-tlink">{{ $t['name'] }}</a>
                @endforeach
            </span>
        </div>
        @endforeach
    </div>
    @endif
</div>
</div>
@endsection