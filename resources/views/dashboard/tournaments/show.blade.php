@extends('layouts.app')

@section('title', $tournament->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name],
    ]" />
<div x-data="{ showShare: false }">
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
            <a href="{{ route('sponsors.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-handshake me-1"></i> Patrocinadores</a>
            <a href="{{ route('tournaments.import.form', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-file-import me-1"></i> Importar</a>
            @if($tournament->is_listed)
            <button type="button" class="btn btn-soft" @click="showShare = !showShare"><i class="fa-solid fa-share-nodes me-1"></i> Compartir / QR</button>
            <a href="{{ route('public.tournament', $tournament) }}" target="_blank" class="btn btn-soft"><i class="fa-solid fa-globe me-1"></i> Página pública</a>
            @endif
            <a href="{{ route('venues.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-location-dot me-1"></i> Sedes</a>
            <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-calendar-days me-1"></i> Calendario</a>
            <a href="{{ route('tournaments.edit', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-pen me-1"></i> Editar</a>
            <a href="{{ route('categories.create', $tournament) }}" class="btn btn-accent"><i class="fa-solid fa-plus me-1"></i> Nueva categoría</a>
        </div>
    </div>

    @if($tournament->is_listed)
    <div x-show="showShare" x-cloak class="tc-card mb-3">
        <div class="tc-card__head">
            <h3><i class="fa-solid fa-share-nodes me-1"></i> Compartir torneo</h3>
        </div>
        <div class="tc-card__body" style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;">
            <div data-qr="{{ route('public.tournament', $tournament) }}" style="background:#fff;padding:10px;border-radius:var(--radius);"></div>
            <div style="flex:1;min-width:220px;">
                <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px;">
                    Comparte este enlace o muestra el código QR para que jugadores y espectadores sigan el torneo.
                </p>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <input type="text" readonly value="{{ route('public.tournament', $tournament) }}"
                        class="form-control form-control-sm" style="max-width:320px;border-radius:var(--radius);"
                        onclick="this.select()">
                    <button type="button" class="btn btn-soft btn-sm" data-share="{{ route('public.tournament', $tournament) }}" data-share-title="{{ $tournament->name }}">
                        <i class="fa-solid fa-share-nodes me-1"></i> Compartir
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
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