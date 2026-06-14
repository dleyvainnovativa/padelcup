@extends('layouts.app')

@section('title', 'Torneos')

@section('content')
<div class="page-head">
    <div>
        <h1>Torneos</h1>
        <div class="page-sub">Tus torneos como organizador.</div>
    </div>
    <a href="{{ route('tournaments.create') }}" class="btn btn-accent">
        <i class="fa-solid fa-plus me-1"></i> Nuevo torneo
    </a>
</div>

@includeWhen(session('status'), 'dashboard.partials.flash')

<div class="row g-3">
    @forelse ($tournaments as $t)
    <div class="col-12 col-md-6 col-lg-4">
        <a href="{{ route('tournaments.show', $t) }}" class="text-decoration-none">
            <div class="tc-card h-100">
                <div class="tc-card__body">
                    <div class="d-flex justify-content-between align-items-start">
                        <h3 style="font-size:15px;font-weight:700;margin:0;color:var(--text);">{{ $t->name }}</h3>
                        <x-pill :variant="$t->phase->value === 'setup' ? 'neutral' : ($t->phase->value === 'locked' ? 'accent' : 'ok')" dot>
                            {{ $t->phase->label() }}
                        </x-pill>
                    </div>
                    <div style="font-size:12.5px;color:var(--text-muted);margin-top:6px;">
                        @if($t->starts_on)
                        {{ $t->starts_on->timezone('America/Mexico_City')->translatedFormat('d M') }}
                        – {{ $t->ends_on?->timezone('America/Mexico_City')->translatedFormat('d M Y') }}
                        @else
                        Sin fechas
                        @endif
                    </div>
                    <div style="font-size:12px;color:var(--text-faint);margin-top:12px;">
                        <i class="fa-solid fa-layer-group me-1"></i> {{ $t->categories_count }} categorías
                    </div>
                </div>
            </div>
        </a>
    </div>
    @empty
    <div class="col-12">
        <div class="tc-card">
            <div class="tc-card__body" style="color:var(--text-muted);">
                Aún no tienes torneos. Crea el primero.
            </div>
        </div>
    </div>
    @endforelse
</div>

<div class="mt-3">{{ $tournaments->links() }}</div>
@endsection