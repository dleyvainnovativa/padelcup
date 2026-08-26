@extends('layouts.public')

@section('title', $system->name)

@section('content')
<div class="pp">
    <div class="pp-head pp-head--circuit">
        <div class="pp-head__inner">
            @if($system->coverImageUrl())
            <div class="pp-circuit__cover">
                <img src="{{ $system->coverImageUrl() }}" alt="{{ $system->name }}">
            </div>
            @endif
            <div class="pp-circuit__eyebrow">Circuito @if($system->owner_label) · {{ $system->owner_label }}@endif</div>
            <h1>{{ $system->name }}</h1>
            <div class="pp-circuit__actions">
                <a href="{{ route('public.rankings.show', $system) }}" class="pp-btn pp-btn--primary">
                    <i class="fa-solid fa-ranking-star"></i> Ver ranking
                </a>
            </div>
        </div>
    </div>

    <div class="pp-body">
        @if($active->isEmpty() && $past->isEmpty())
        <div class="pp-empty">Este circuito todavía no tiene torneos publicados.</div>
        @endif

        @if($active->isNotEmpty())
        <h2 class="pp-section-title"><i class="fa-solid fa-bolt"></i> En curso y próximos</h2>
        <div class="pp-grid">
            @foreach($active as $t)
            @include('public.partials.tournament-card', ['t' => $t, 'live' => true])
            @endforeach
        </div>
        @endif

        @if($past->isNotEmpty())
        <h2 class="pp-section-title"><i class="fa-solid fa-flag-checkered"></i> Finalizados</h2>
        <div class="pp-grid">
            @foreach($past as $t)
            @include('public.partials.tournament-card', ['t' => $t, 'live' => false])
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection