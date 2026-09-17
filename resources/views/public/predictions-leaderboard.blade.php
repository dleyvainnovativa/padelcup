@extends('layouts.public')
@section('title', 'Predicciones · ' . $tournament->name)

@section('content')
<div class="pub-wrap" style="padding: clamp(24px,4vw,44px) 20px;">
    <nav class="docs-breadcrumb" style="margin-bottom:14px;">
        <a href="{{ route('public.tournament', $tournament) }}">{{ $tournament->name }}</a>
        <i class="fa-solid fa-chevron-right"></i>
        <span>Predicciones</span>
    </nav>

    <h1 style="font-size:clamp(24px,3.4vw,36px);font-weight:800;letter-spacing:-.02em;margin:0 0 6px;">
        <i class="fa-solid fa-hand-sparkles" style="color:var(--lp-indigo);"></i> Predicciones
    </h1>
    <p style="color:var(--text-muted);font-size:15px;margin:0 0 28px;">
        Tabla de aciertos. Gana 1 punto por cada marcador exacto que adivines.
    </p>

    @if(empty($rows))
    <div class="pp-empty">
        <i class="fa-solid fa-hand-sparkles" style="font-size:28px;color:var(--text-faint);"></i>
        <p>Aún no hay predicciones puntuadas. ¡Sé el primero en adivinar un marcador!</p>
    </div>
    @else
    <div class="pred-board">
        <div class="pred-board__head">
            <span>#</span>
            <span>Jugador</span>
            <span class="pred-board__num">Aciertos</span>
            <span class="pred-board__num">Puntos</span>
        </div>
        @foreach($rows as $i => $r)
        <div class="pred-board__row {{ $i < 3 ? 'is-top' : '' }}">
            <span class="pred-board__rank">
                @if($i === 0)🥇@elseif($i === 1)🥈@elseif($i === 2)🥉@else{{ $i + 1 }}@endif
            </span>
            <span class="pred-board__name">{{ $r['user']?->name ?? 'Usuario' }}</span>
            <span class="pred-board__num">{{ $r['correct'] }}/{{ $r['total'] }}</span>
            <span class="pred-board__num pred-board__pts">{{ $r['points'] }}</span>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection