@extends('layouts.app')

@section('title', 'Mis estadísticas')

@section('content')
<div class="page-head">
    <div>
        <h1>Mis estadísticas</h1>
        <div class="page-sub">Tu desempeño en todos los torneos.</div>
    </div>
</div>

@include('dashboard.partials.flash')

{{-- Headline stat tiles --}}
<div class="pub-grid" style="margin-bottom:20px;">
    <div class="tc-card"><div class="tc-card__body" style="text-align:center;">
        <div style="font-size:34px;font-weight:800;color:var(--accent-text);line-height:1;">{{ $stats['win_rate'] }}%</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Efectividad</div>
    </div></div>
    <div class="tc-card"><div class="tc-card__body" style="text-align:center;">
        <div style="font-size:34px;font-weight:800;line-height:1;">{{ $stats['matches_played'] }}</div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Partidos jugados</div>
    </div></div>
    <div class="tc-card"><div class="tc-card__body" style="text-align:center;">
        <div style="font-size:34px;font-weight:800;line-height:1;">
            <span style="color:var(--success);">{{ $stats['wins'] }}</span><span style="color:var(--text-faint);font-size:20px;">/</span><span style="color:var(--text-muted);">{{ $stats['losses'] }}</span>
        </div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Ganados / Perdidos</div>
    </div></div>
</div>

{{-- Secondary stats --}}
<div class="pub-grid" style="margin-bottom:20px;">
    <div class="tc-card"><div class="tc-card__body">
        <div style="font-size:12px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Sets</div>
        <div style="font-size:20px;font-weight:700;">
            {{ $stats['sets_won'] }} <span style="color:var(--text-faint);font-size:14px;">ganados</span>
            · {{ $stats['sets_lost'] }} <span style="color:var(--text-faint);font-size:14px;">perdidos</span>
        </div>
    </div></div>
    <div class="tc-card"><div class="tc-card__body">
        <div style="font-size:12px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Racha actual</div>
        @if($stats['streak_type'] === 'win')
            <div style="font-size:20px;font-weight:700;color:var(--success);">🔥 {{ $stats['current_streak'] }} victoria{{ $stats['current_streak']==1?'':'s' }}</div>
        @elseif($stats['streak_type'] === 'loss')
            <div style="font-size:20px;font-weight:700;color:var(--text-muted);">{{ $stats['current_streak'] }} derrota{{ $stats['current_streak']==1?'':'s' }}</div>
        @else
            <div style="font-size:20px;font-weight:700;color:var(--text-faint);">—</div>
        @endif
    </div></div>
    <div class="tc-card"><div class="tc-card__body">
        <div style="font-size:12px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Participación</div>
        <div style="font-size:20px;font-weight:700;">
            {{ $stats['tournaments'] }} <span style="color:var(--text-faint);font-size:14px;">torneo{{ $stats['tournaments']==1?'':'s' }}</span>
            · {{ $stats['categories'] }} <span style="color:var(--text-faint);font-size:14px;">categoría{{ $stats['categories']==1?'':'s' }}</span>
        </div>
    </div></div>
</div>

{{-- Best finish --}}
@if($stats['best_position'])
<h2 class="pub-section-title" style="margin-bottom:12px;">Mejor posición</h2>
<div class="tc-card"><div class="tc-card__body" style="display:flex;align-items:center;gap:16px;">
    <div style="font-size:38px;font-weight:800;color:var(--accent-text);line-height:1;">{{ $stats['best_position']['position'] }}º</div>
    <div>
        <div style="font-weight:600;">{{ $stats['best_position']['group'] }} · {{ $stats['best_position']['category'] }}</div>
        <div style="font-size:12.5px;color:var(--text-muted);">{{ $stats['best_position']['tournament'] }} · de {{ $stats['best_position']['of'] }} parejas</div>
    </div>
</div></div>
@endif

@if($stats['matches_played'] === 0)
<div class="tc-card"><div class="tc-card__body" style="text-align:center;color:var(--text-faint);padding:32px;">
    Aún no tienes partidos jugados. Tus estadísticas aparecerán aquí cuando juegues.
</div></div>
@endif
@endsection
