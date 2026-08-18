{{--
    Phase 4 — public leaderboard. Adjust @extends to your public layout (the
    same one Bundle 3's winners page uses).
--}}
@extends('layouts.public')

@section('title', $system->name)

@section('content')
<div class="pub-rank">

    <div class="pub-rank__head">
        <div class="pub-rank__eyebrow">Ranking @if($system->owner_label) · {{ $system->owner_label }}@endif</div>
        <h1 class="pub-rank__title">{{ $system->name }}</h1>
        <div class="pub-rank__sub">Puntos acumulados de todos los torneos</div>
    </div>

    @if($board->isEmpty())
    <div class="pub-rank__empty">Este ranking todavía no tiene puntos.</div>
    @else
    <div class="pub-rank__list">
        @foreach($board as $row)
        <div class="pub-rank__row {{ $row['rank'] <= 3 ? 'pub-rank__row--podium' : '' }}">
            <div class="pub-rank__pos">
                @if($row['rank'] === 1) 🥇
                @elseif($row['rank'] === 2) 🥈
                @elseif($row['rank'] === 3) 🥉
                @else <span class="pub-rank__num">{{ $row['rank'] }}</span>
                @endif
            </div>
            <div class="pub-rank__name">{{ $row['name'] }}</div>
            <div class="pub-rank__pts">
                {{ number_format($row['points']) }}
                <span class="pub-rank__pts-label">pts</span>
            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
@endsection