@extends('layouts.public')

@section('title', 'Buscar')

@section('content')
<div class="pub-wrap">
    <h1>Buscar</h1>
    <p style="color:var(--text-muted);font-size:14px;margin-top:-4px;">
        Encuentra jugadores o torneos publicados.
    </p>

    <form method="GET" action="{{ route('public.search') }}" class="pub-search">
        <div class="pub-search__field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $q }}" placeholder="Nombre de jugador o torneo…" autocomplete="off" autofocus>
        </div>
        <button type="submit" class="pub-btn pub-btn--primary">Buscar</button>
        @if($q !== '')
            <a href="{{ route('public.search') }}" class="pub-btn">Limpiar</a>
        @endif
    </form>

    @if($q !== '' && mb_strlen($q) < 2)
        <div class="pub-empty">Escribe al menos 2 letras para buscar.</div>
    @elseif($q !== '' && $players->isEmpty() && $tournaments->isEmpty())
        <div class="pub-empty">No se encontraron resultados para "{{ $q }}".</div>
    @endif

    @if($tournaments->isNotEmpty())
        <h2 class="pub-section-title">Torneos</h2>
        <div class="pub-grid">
            @foreach($tournaments as $t)
                <a href="{{ route('public.tournament', $t) }}" class="pub-result-card">
                    <span class="pub-result-card__title"><i class="fa-solid fa-trophy"></i> {{ $t->name }}</span>
                    @if($t->starts_on)
                        <span class="pub-result-card__meta">
                            {{ $t->starts_on->timezone('America/Mexico_City')->translatedFormat('d M Y') }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    @if($players->isNotEmpty())
        <h2 class="pub-section-title" style="margin-top:28px;">Jugadores</h2>
        <div class="pub-player-list">
            @foreach($players as $person)
                <div class="pub-player-row">
                    <span class="pub-player-row__name"><i class="fa-solid fa-user"></i> {{ $person['name'] }}</span>
                    <span class="pub-player-row__links">
                        @foreach($person['tournaments'] as $t)
                            <a href="{{ route('public.player', [$t['slug'], $t['player_id']]) }}" class="pub-chip">
                                {{ $t['name'] }}
                            </a>
                        @endforeach
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
