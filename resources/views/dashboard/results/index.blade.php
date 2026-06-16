@extends('layouts.app')

@section('title', 'Resultados')

@php
// Reusable match-row renderer via an anonymous closure isn't ideal in Blade;
// we inline a partial-like block per match below.
@endphp

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => $category->name, 'url' => route('categories.show', [$tournament, $category])],
        ['label' => 'Resultados'],
    ]" />

<div class="page-head">
    <div>
        <h1>Resultados · {{ $category->name }}</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @if($category->format->hasBracket())
        <a href="{{ route('draw.bracket', [$tournament, $category]) }}" class="btn btn-soft">
            <i class="fa-solid fa-sitemap me-1"></i> Llave
        </a>
        @unless($tournament->isLocked())
        <form method="POST" action="{{ route('draw.bracket.build', [$tournament, $category]) }}"
            data-confirm="Se generará la llave a partir de los clasificados actuales. Si ya existe una, se reemplazará. ¿Continuar?"
            data-confirm-title="Generar llave" data-confirm-ok="Generar">
            @csrf
            <button class="btn btn-soft"><i class="fa-solid fa-sitemap me-1"></i> Generar llave</button>
        </form>
        @endunless
        @endif
        <x-pill :variant="$tournament->isLocked() ? 'warn' : 'neutral'" dot>
            {{ $tournament->phase->label() }}
        </x-pill>
    </div>
</div>

@include('dashboard.partials.flash')

@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

@if($groupMatches->isEmpty() && $bracketMatches->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay partidos. Genera los grupos o la llave primero.
    </div>
</div>
@else
@php
$hasBoth = $groupMatches->isNotEmpty() && $bracketMatches->isNotEmpty();
$defaultTab = $groupMatches->isNotEmpty() ? 'groups' : 'bracket';
$tabKey = 'results_tab_'.$category->id;
@endphp
<div x-data="{
                tab: (sessionStorage.getItem('{{ $tabKey }}') || '{{ $defaultTab }}'),
                setTab(t) { this.tab = t; sessionStorage.setItem('{{ $tabKey }}', t); }
             }"
    x-init="if (!['groups','bracket'].includes(tab)) tab = '{{ $defaultTab }}'">
    @if($hasBoth)
    <div class="tc-tabs mb-3">
        <button type="button" class="tc-tab" :class="{ 'is-active': tab === 'groups' }" @click="setTab('groups')">
            <i class="fa-solid fa-layer-group me-1"></i> Grupos
        </button>
        <button type="button" class="tc-tab" :class="{ 'is-active': tab === 'bracket' }" @click="setTab('bracket')">
            <i class="fa-solid fa-sitemap me-1"></i> Eliminación
        </button>
    </div>
    @endif

    {{-- Group matches --}}
    <div @if($hasBoth) x-show="tab === 'groups'" x-cloak @endif>
        @foreach($groupMatches as $groupId => $matches)
        @php
        $group = $matches->first()->group;
        $isMexicano = $category->group_format === \App\Enums\GroupFormat::Mexicano
        && $group->pairs->count() === 4;
        @endphp
        <div class="tc-card mb-3">
            <div class="tc-card__head">
                <h3>{{ $group->name }}</h3>
                @if($isMexicano)
                <span class="pill pill--neutral" style="font-size:11px;">Mexicano · {{ $matches->count() }} partidos</span>
                @endif
            </div>
            <div class="tc-card__body" style="display:flex;flex-direction:column;gap:8px;">
                @if($isMexicano)
                @foreach($matches->groupBy('round') as $round => $roundMatches)
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-faint);margin-top:4px;">
                    Ronda {{ $round }}
                </div>
                @foreach($roundMatches as $match)
                @include('dashboard.results.partials.match-row', ['match' => $match])
                @endforeach
                @endforeach
                @else
                @foreach($matches as $match)
                @include('dashboard.results.partials.match-row', ['match' => $match])
                @endforeach
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Bracket matches --}}
    @if($bracketMatches->isNotEmpty())
    <div @if($hasBoth) x-show="tab === 'bracket'" x-cloak @endif>
        <div class="tc-card mb-3">
            <div class="tc-card__head">
                <h3>Llave</h3>
            </div>
            <div class="tc-card__body" style="display:flex;flex-direction:column;gap:8px;">
                @foreach($bracketMatches->groupBy('round') as $round => $roundMatches)
                <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-faint);margin-top:4px;">
                    {{ $roundMatches->first()->bracketRoundName() }}
                </div>
                @foreach($roundMatches as $match)
                @include('dashboard.results.partials.match-row', ['match' => $match])
                @endforeach
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
@endif
@endsection