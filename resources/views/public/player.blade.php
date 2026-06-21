@extends('layouts.public')

@section('title', $player->name.' · '.$tournament->name)

@section('content')
<div class="pub-wrap" data-auto-refresh="60">
    <div class="pub-crumb">
        <a href="{{ route('public.tournament', $tournament) }}"><i class="fa-solid fa-chevron-left"></i> {{ $tournament->name }}</a>
    </div>

    <div class="pub-player-head">
        <div class="pub-player-avatar">{{ \Illuminate\Support\Str::of($player->name)->explode(' ')->map(fn($w) => mb_substr($w,0,1))->take(2)->implode('') }}</div>
        <div>
            <h1 style="margin:0;">{{ $player->name }}</h1>
            <div class="pub-sub" style="margin:4px 0 0;">
                @foreach($categories as $cat)
                <a href="{{ route('public.category', [$tournament, $cat]) }}" class="pub-chip">{{ $cat->name }}</a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="pub-stats">
        <div class="pub-stat">
            <div class="pub-stat__n">{{ $stats['played'] }}</div>
            <div class="pub-stat__l">Jugados</div>
        </div>
        <div class="pub-stat">
            <div class="pub-stat__n">{{ $stats['won'] }}</div>
            <div class="pub-stat__l">Ganados</div>
        </div>
        <div class="pub-stat">
            <div class="pub-stat__n">{{ $stats['lost'] }}</div>
            <div class="pub-stat__l">Perdidos</div>
        </div>
        <div class="pub-stat">
            <div class="pub-stat__n pub-mono">{{ $stats['setsWon'] }}-{{ $stats['setsLost'] }}</div>
            <div class="pub-stat__l">Games</div>
        </div>
    </div>

    {{-- Upcoming highlight --}}
    @if($upcoming->isNotEmpty())
    @php $next = $upcoming->first(); @endphp
    <div class="pub-next">
        <div class="pub-next__tag"><span class="pub-status__dot pub-status__dot--soon"></span> Próximo partido</div>
        <div class="pub-next__body">
            <div class="pub-next__when">
                {{ $next->starts_at->timezone('America/Mexico_City')->translatedFormat('D d M · H:i') }}
                @if($next->court) · <i class="fa-solid fa-location-dot"></i> {{ $next->court->name }}@endif
            </div>
            <div class="pub-next__match">{{ $next->sideLabel('a') }} <span class="pub-muted">vs</span> {{ $next->sideLabel('b') }}</div>
            <div class="pub-next__ctx">{{ $next->category->name }} · {{ $next->contextLabel() }}</div>
        </div>
    </div>
    @endif

    {{-- All matches --}}
    <h2 class="pub-section-title" style="margin-top:24px;">Partidos</h2>
    @if($matches->isEmpty())
    <div class="pub-empty">Sin partidos todavía.</div>
    @else
    <div class="pub-card">
        <div class="pub-card__body" style="padding:0;">
            @foreach($matches as $m)
            @php
            $played = $m->state->value === 'confirmed';
            $mineA = in_array($m->pair_a_id, $pairIds);
            $myPairId = $mineA ? $m->pair_a_id : $m->pair_b_id;
            $iWon = $played && $m->winner_pair_id === $myPairId;
            $iLost = $played && $m->winner_pair_id && $m->winner_pair_id !== $myPairId;
            @endphp
            <div class="pub-pmatch {{ $iWon ? 'is-win' : ($iLost ? 'is-loss' : '') }}">
                <div class="pub-pmatch__res">
                    @if($iWon)<span class="pub-pmatch__badge pub-pmatch__badge--w">G</span>
                    @elseif($iLost)<span class="pub-pmatch__badge pub-pmatch__badge--l">P</span>
                    @else<span class="pub-pmatch__badge">·</span>@endif
                </div>
                <div class="pub-pmatch__main">
                    <div class="pub-pmatch__pairs">
                        {{ $m->sideLabel('a') }} <span class="pub-muted">vs</span> {{ $m->sideLabel('b') }}
                    </div>
                    <div class="pub-pmatch__meta">
                        {{ $m->category->name }} · {{ $m->contextLabel() }}
                        @if($m->starts_at) · {{ $m->starts_at->timezone('America/Mexico_City')->translatedFormat('d M H:i') }}@endif
                        @if($m->court) · {{ $m->court->name }}@endif
                    </div>
                </div>
                <div class="pub-pmatch__score pub-mono">
                    @if($played && $m->sets)
                    @foreach($m->sets as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last) @endif @endforeach
                    @else
                    <span class="pub-muted">—</span>
                    @endif
                </div>
                @if($played)
                @php
                $shareData = [
                'tournament' => $tournament->name,
                'category' => $m->category->name,
                'context' => $m->contextLabel(),
                'pairA' => $m->sideLabel('a'),
                'pairB' => $m->sideLabel('b'),
                'sets' => $m->sets ?? [],
                'winner' => $m->winner_pair_id === $m->pair_a_id ? 'a' : ($m->winner_pair_id === $m->pair_b_id ? 'b' : null),
                ];
                @endphp
                <button type="button" class="pub-share-btn" data-share-match='@json($shareData)' title="Compartir imagen">
                    <i class="fa-solid fa-image"></i>
                </button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection