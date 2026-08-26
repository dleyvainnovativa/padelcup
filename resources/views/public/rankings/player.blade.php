@extends('layouts.public')

@section('title', $p['name'] . ' · ' . $system->name)

@section('content')
<div class="pub-rank">
    <div class="pub-rank__head">
        <div class="pub-rank__eyebrow">
            <a href="{{ route('public.rankings.show', [$system, 'cat' => $scope]) }}" style="color:inherit;text-decoration:none;">
                ← {{ $system->name }}
            </a>
        </div>
        <h1 class="pub-rank__title">{{ $p['name'] }}</h1>
        <div class="pub-rank__sub">
            @if($p['rank'])#{{ $p['rank'] }} en {{ $p['scope_label'] }} · @endif{{ $p['tournaments'] }} torneo(s)
            · <strong>{{ number_format($p['total']) }} pts</strong>
        </div>
    </div>

    <div class="rk-pd rk-pd--public">
        @include('dashboard.rankings.partials.player-detail', ['hideHeader' => true])
    </div>
</div>
@endsection
