@extends('layouts.public')

@section('title', 'Circuitos')

@section('content')
<div class="pp">
    <div class="pp-head">
        <div class="pp-head__inner">
            <h1>Circuitos</h1>
            <p>Series de torneos con ranking acumulado. Explora cada circuito y sigue la tabla de posiciones.</p>
            <form method="GET" action="{{ route('public.circuits.index') }}" class="pp-search">
                <div class="pp-search__field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar circuito por nombre…" autocomplete="off">
                </div>
                <button type="submit" class="pp-btn pp-btn--primary">Buscar</button>
                @if($search !== '')
                <a href="{{ route('public.circuits.index') }}" class="pp-btn pp-btn--ghost">Limpiar</a>
                @endif
            </form>
        </div>
    </div>

    <div class="pp-body">
        @if($circuits->isEmpty())
        <div class="pp-empty">
            @if($search !== '')No se encontraron circuitos para "{{ $search }}".@else No hay circuitos publicados.@endif
        </div>
        @else
        <div class="pp-grid">
            @foreach($circuits as $c)
            @include('public.partials.circuit-card', ['c' => $c])
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
