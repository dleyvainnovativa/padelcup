@extends('layouts.app')

@section('title', $tournament->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name],
    ]" />
<div class="page-head">
    <div>
        <h1>{{ $tournament->name }}</h1>
        <div class="page-sub">
            @if($tournament->starts_on)
            {{ $tournament->starts_on->timezone('America/Mexico_City')->translatedFormat('d M') }}
            – {{ $tournament->ends_on?->timezone('America/Mexico_City')->translatedFormat('d M Y') }} ·
            @endif
            <x-pill :variant="$tournament->phase->value === 'setup' ? 'neutral' : ($tournament->phase->value === 'locked' ? 'accent' : 'ok')" dot>
                {{ $tournament->phase->label() }}
            </x-pill>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('tournaments.summary', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-chart-simple me-1"></i> Resumen</a>
        <a href="{{ route('venues.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-location-dot me-1"></i> Sedes</a>
        <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-calendar-days me-1"></i> Calendario</a>
        <a href="{{ route('tournaments.edit', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-pen me-1"></i> Editar</a>
        <a href="{{ route('categories.create', $tournament) }}" class="btn btn-accent"><i class="fa-solid fa-plus me-1"></i> Nueva categoría</a>
    </div>
</div>

@include('dashboard.partials.flash')

<div class="section-title">Categorías</div>
<div class="row g-3">
    @forelse ($tournament->categories as $category)
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('categories.show', [$tournament, $category]) }}" class="text-decoration-none">
            <div class="tc-card h-100 {{ $category->tintClass() }}">
                <div class="cat-bar"></div>
                <div class="tc-card__body">
                    <h3 style="font-size:14.5px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px;">
                        <span class="cat-tag"></span> {{ $category->name }}
                    </h3>
                    <div style="font-size:12px;color:var(--text-muted);margin:4px 0 12px;">
                        {{ $category->format->label() }}
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:12.5px;color:var(--text-muted);">
                        <span><i class="fa-solid fa-people-group me-1"></i> {{ $category->pairs_count }} parejas</span>
                        <span style="color:var(--text-faint);">
                            {{ $category->max_pairs ? 'cupo '.$category->max_pairs : 'sin cupo' }}
                        </span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="tc-card">
            <div class="tc-card__body" style="color:var(--text-muted);">
                Sin categorías todavía. Crea la primera (p. ej. «5ta Femenil»).
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection