@extends('layouts.public')

@section('title', 'Torneos')

@section('content')
<div class="pp">
    <div class="pp-head">
        <!-- <div class="lp-aurora" aria-hidden="true">
            <span class="lp-aurora__blob lp-aurora__blob--1"></span>
            <span class="lp-aurora__blob lp-aurora__blob--2"></span>
        </div> -->
        <div class="pp-head__inner">
            <h1>Torneos</h1>
            <p>Explora los torneos de pádel publicados y sigue cada partido en vivo.</p>
            <form method="GET" action="{{ route('public.directory') }}" class="pp-search">
                <div class="pp-search__field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar torneo por nombre…" autocomplete="off">
                </div>
                <button type="submit" class="pp-btn pp-btn--primary">Buscar</button>
                @if($search !== '')
                <a href="{{ route('public.directory') }}" class="pp-btn pp-btn--ghost">Limpiar</a>
                @endif
            </form>
        </div>
    </div>

    <div class="pp-body">
        @if($active->isEmpty() && $past->isEmpty())
        <div class="pp-empty">
            @if($search !== '')No se encontraron torneos para "{{ $search }}".@else No hay torneos publicados.@endif
        </div>
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