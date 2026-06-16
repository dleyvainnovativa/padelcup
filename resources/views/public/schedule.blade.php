@extends('layouts.public')

@section('title', 'Calendario · '.$tournament->name)

@section('content')
<div class="pub-wrap" data-auto-refresh="60">
    <div class="pub-crumb">
        <a href="{{ route('public.tournament', $tournament) }}"><i class="fa-solid fa-chevron-left"></i> {{ $tournament->name }}</a>
    </div>
    <div class="pub-title-row">
        <h1>Calendario</h1>
        <div class="pub-title-actions">
            <span class="pub-live"><span class="pub-status__dot pub-status__dot--live"></span> En vivo</span>
            <button type="button" class="pub-btn pub-btn--icon" data-share="{{ route('public.schedule', $tournament) }}" data-share-title="Calendario · {{ $tournament->name }}">
                <i class="fa-solid fa-share-nodes"></i> Compartir
            </button>
        </div>
    </div>

    {{-- Buscar mi partido --}}
    <form method="GET" action="{{ route('public.schedule', $tournament) }}" class="pub-search">
        <div class="pub-search__field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar mi partido (nombre del jugador o pareja)…" autocomplete="off">
        </div>
        <button type="submit" class="pub-btn pub-btn--primary">Buscar</button>
        @if($search !== '')
        <a href="{{ route('public.schedule', $tournament) }}" class="pub-btn">Limpiar</a>
        @endif
    </form>

    @if($search !== '')
    <div class="pub-search-meta">
        {{ $total }} {{ $total === 1 ? 'partido' : 'partidos' }} para "{{ $search }}"
    </div>
    @if($matchedPlayers->isNotEmpty())
    <div class="pub-matched-players">
        <span class="pub-muted" style="font-size:12px;">Ver perfil:</span>
        @foreach($matchedPlayers as $p)
        <a href="{{ route('public.player', [$tournament, $p]) }}" class="pub-chip pub-chip--link">
            <i class="fa-solid fa-user"></i> {{ $p->name }}
        </a>
        @endforeach
    </div>
    @endif
    @endif

    @if($byDay->isEmpty())
    <div class="pub-empty">
        @if($search !== '')
        No se encontraron partidos para "{{ $search }}".
        @else
        Aún no hay partidos programados.
        @endif
    </div>
    @else
    @foreach($byDay as $day => $matches)
    @php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
    <div class="pub-day">
        <div class="pub-day__title">{{ $d->translatedFormat('l d \d\e F') }}</div>
        <div class="pub-day__matches">
            @foreach($matches as $m)
            @php $status = $m->scheduleStatus(); @endphp
            <div class="pub-match pub-match--{{ $status }}">
                <div class="pub-match__time">
                    {{ $m->starts_at->timezone('America/Mexico_City')->format('H:i') }}
                    @if($m->court)<span class="pub-match__court"><i class="fa-solid fa-location-dot"></i> {{ $m->court->name }}</span>@endif
                </div>
                <div class="pub-match__body">
                    <div class="pub-match__ctx">{{ $m->contextLabel() }}</div>
                    <div class="pub-match__pairs">
                        <span class="{{ $m->winner_pair_id === $m->pair_a_id && $m->pair_a_id ? 'is-win' : '' }}">{{ $m->sideLabel('a') }}</span>
                        @if($status === 'played' && $m->sets)
                        <span class="pub-match__sc pub-mono">
                            @foreach($m->sets as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last) @endif @endforeach
                        </span>
                        @else
                        <span class="pub-match__vs">vs</span>
                        @endif
                        <span class="{{ $m->winner_pair_id === $m->pair_b_id && $m->pair_b_id ? 'is-win' : '' }}">{{ $m->sideLabel('b') }}</span>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif
</div>
@endsection