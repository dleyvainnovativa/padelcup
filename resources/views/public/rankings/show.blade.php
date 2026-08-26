@extends('layouts.public')

@section('title', $system->name)

@section('content')
<div class="pub-rank">

    @if($system->coverImageUrl())
    <div class="pub-rank__cover">
        <img src="{{ $system->coverImageUrl() }}" alt="{{ $system->name }}">
    </div>
    @endif

    <div class="pub-rank__head">
        <div class="pub-rank__eyebrow">Ranking@if($system->owner_label) · {{ $system->owner_label }}@endif</div>
        <h1 class="pub-rank__title">{{ $system->name }}</h1>
        <div class="pub-rank__sub">
            @if($activeTourLabel){{ $activeTourLabel }}@else Puntos acumulados de todos los torneos @endif
            @if(($activeLabel ?? 'General') !== 'General') · {{ $activeLabel }}@endif
        </div>
    </div>

    @include('dashboard.rankings.partials.filter-bar', ['routeName' => 'public.rankings.show'])

    @if($board->isEmpty())
    <div class="pub-rank__empty">Esta tabla todavía no tiene puntos.</div>
    @else
    <div class="pub-rank__list">
        @foreach($board as $row)
        <a href="{{ route('public.rankings.player', [$system, $row['key'], 'cat' => $activeCat ?? 'all']) }}"
            class="pub-rank__row {{ $row['rank'] <= 3 ? 'pub-rank__row--podium' : '' }}">
            <div class="pub-rank__pos">
                @if($row['rank'] === 1) 🥇
                @elseif($row['rank'] === 2) 🥈
                @elseif($row['rank'] === 3) 🥉
                @else <span class="pub-rank__num">{{ $row['rank'] }}</span>
                @endif
            </div>
            <div class="pub-rank__name">{{ $row['name'] }}</div>
            <div class="pub-rank__pts">{{ number_format($row['points']) }}<span class="pub-rank__pts-label">pts</span></div>
        </a>
        @endforeach
    </div>
    @endif

</div>
@endsection