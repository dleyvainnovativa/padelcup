@extends('layouts.public')

@section('title', 'Torneos')

@section('content')
<div class="pub-wrap">
    <h1>Torneos</h1>

    <form method="GET" action="{{ route('public.directory') }}" class="pub-search">
        <div class="pub-search__field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" value="{{ $search }}" placeholder="Buscar torneo por nombre…" autocomplete="off">
        </div>
        <button type="submit" class="pub-btn pub-btn--primary">Buscar</button>
        @if($search !== '')
            <a href="{{ route('public.directory') }}" class="pub-btn">Limpiar</a>
        @endif
    </form>

    @if($active->isEmpty() && $past->isEmpty())
        <div class="pub-empty">
            @if($search !== '')No se encontraron torneos para "{{ $search }}".@else No hay torneos publicados.@endif
        </div>
    @endif

    @if($active->isNotEmpty())
        <h2 class="pub-section-title">En curso y próximos</h2>
        <div class="pub-grid">
            @foreach($active as $t)
                @include('public.partials.tournament-card', ['t' => $t, 'live' => true])
            @endforeach
        </div>
    @endif

    @if($past->isNotEmpty())
        <h2 class="pub-section-title" style="margin-top:28px;">Finalizados</h2>
        <div class="pub-grid">
            @foreach($past as $t)
                @include('public.partials.tournament-card', ['t' => $t, 'live' => false])
            @endforeach
        </div>
    @endif
</div>
@endsection
