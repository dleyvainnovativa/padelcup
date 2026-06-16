@extends('layouts.app')

@section('title', 'Llave')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => $category->name, 'url' => route('categories.show', [$tournament, $category])],
        ['label' => 'Llave'],
    ]" />
<div class="page-head">
    <div>
        <h1>Llave · {{ $category->name }}</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('results.index', [$tournament, $category]) }}" class="btn btn-soft">
            <i class="fa-solid fa-flag-checkered me-1"></i> Resultados
        </a>
        @unless($tournament->isLocked())
        <button type="button" class="btn btn-soft" data-swap-toggle><i class="fa-solid fa-arrows-up-down-left-right me-1"></i> Editar posiciones</button>
        <form method="POST" action="{{ route('draw.bracket.build', [$tournament, $category]) }}"
            data-confirm="Se reemplazará la llave actual por una nueva. ¿Continuar?" data-confirm-title="Regenerar llave" data-confirm-ok="Regenerar">
            @csrf
            <button class="btn btn-soft"><i class="fa-solid fa-rotate me-1"></i> Regenerar llave</button>
        </form>
        @endunless
    </div>
</div>

@include('dashboard.partials.flash')

@if($matches->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay llave generada.
    </div>
</div>
@else
<div style="overflow-x:auto;" data-bracket-board data-swap-url="{{ route('draw.bracket.swap', [$tournament, $category]) }}">
    <div style="display:flex;gap:24px;min-width:max-content;padding-bottom:12px;">
        @foreach($matches as $round => $roundMatches)
        <div style="display:flex;flex-direction:column;gap:14px;justify-content:space-around;min-width:220px;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-faint);font-weight:600;">
                @php
                $label = $roundMatches->first()->bracketRoundName();
                @endphp
                {{ $label }}
            </div>
            @foreach($roundMatches as $m)
            @php
            $status = $m->scheduleStatus();
            $ready = $m->isReadyForResult();
            $swappable = $round == 1 && $status !== 'played';
            @endphp
            <div class="sched-match is-{{ $status }} {{ $ready ? 'is-tappable' : '' }}"
                @if($ready)
                data-bracket-match="{{ $m->id }}"
                data-ctx="{{ $m->bracketRoundName() }}"
                data-a="{{ $m->sideLabel('a') }}"
                data-b="{{ $m->sideLabel('b') }}"
                data-status="{{ $status }}"
                data-sets='@json($m->sets ?? [])'
                data-confirm-url="{{ route('results.confirm', [$tournament, $category, $m]) }}"
                data-edit-url="{{ route('results.edit', [$tournament, $category, $m]) }}"
                @endif>
                <div class="bmatch-side {{ $m->winner_pair_id === $m->pair_a_id && $m->pair_a_id ? '' : 'is-dim' }}"
                    @if($swappable) data-swap-slot data-swap-match="{{ $m->id }}" data-swap-side="a" @endif>{{ $m->sideLabel('a') }}</div>
                <div style="font-size:10px;color:var(--text-faint);">vs</div>
                <div class="bmatch-side {{ $m->winner_pair_id === $m->pair_b_id && $m->pair_b_id ? '' : 'is-dim' }}"
                    @if($swappable) data-swap-slot data-swap-match="{{ $m->id }}" data-swap-side="b" @endif>{{ $m->sideLabel('b') }}</div>
                @if($status === 'played' && $m->sets)
                <div class="sched-match__scores">
                    @foreach($m->sets as $s)
                    <span class="sched-score-badge">{{ $s[0] }}-{{ $s[1] }}</span>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection