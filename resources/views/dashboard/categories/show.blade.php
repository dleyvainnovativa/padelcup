@extends('layouts.app')

@section('title', $category->name)

@php
$occupied = $category->occupiedSlots();
$cap = $category->max_pairs;
$pct = $cap ? min(100, round($occupied / $cap * 100)) : 0;
$belowMin = $occupied < $category->min_pairs;
    @endphp

    @section('content')
    <x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => $category->name],
    ]" />
    <div class="page-head">
        <div>
            <h1 style="display:flex;align-items:center;gap:10px;">
                <span class="{{ $category->tintClass() }}"><span class="cat-tag" style="width:14px;height:14px;border-radius:4px;"></span></span>
                {{ $category->name }}
            </h1>
            <div class="page-sub">
                {{ $tournament->name }} · {{ $category->format->label() }} · {{ $category->priceFormatted() }}
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($category->format->hasGroups())
            <a href="{{ route('draw.groups', [$tournament, $category]) }}" class="btn btn-soft"><i class="fa-solid fa-layer-group me-1"></i> Grupos</a>
            @endif
            @if($category->format->hasBracket())
            <a href="{{ route('draw.bracket', [$tournament, $category]) }}" class="btn btn-soft"><i class="fa-solid fa-sitemap me-1"></i> Llave</a>
            @endif
            <a href="{{ route('results.index', [$tournament, $category]) }}" class="btn btn-soft"><i class="fa-solid fa-flag-checkered me-1"></i> Resultados</a>
            <a href="{{ route('pairs.import.form', [$tournament, $category]) }}" class="btn btn-soft"><i class="fa-solid fa-file-csv me-1"></i> Importar CSV</a>
            <a href="{{ route('categories.edit', [$tournament, $category]) }}" class="btn btn-soft"><i class="fa-solid fa-pen me-1"></i> Editar</a>
        </div>
    </div>

    @include('dashboard.partials.flash')

    @error('draw')
    <div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">{{ $message }}</div>
    @enderror

    @if($category->format->hasGroups() && $category->groups()->count() === 0)
    <div class="tc-card mb-3" style="border-color:var(--accent);">
        <div class="tc-card__body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div style="font-size:13px;">Las parejas están listas. Genera los grupos y los partidos.</div>
            <a href="{{ route('draw.groups.preview', [$tournament, $category]) }}" class="btn btn-accent btn-sm">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generar grupos
            </a>
        </div>
    </div>
    @endif

    {{-- Public self-registration link to share with players --}}
    <div class="tc-card mb-3">
        <div class="tc-card__body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div style="font-size:13px;font-weight:600;">Enlace de autoinscripción</div>
                <code style="font-size:12px;color:var(--text-muted);word-break:break-all;">{{ route('registration.create', $category) }}</code>
            </div>
            <a href="{{ route('registration.create', $category) }}" target="_blank" class="btn btn-soft btn-sm">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir
            </a>
        </div>
    </div>

    @error('category')
    <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);border:1px solid transparent;">
        {{ $message }}
    </div>
    @enderror

    {{-- Capacity --}}
    <div class="tc-card mb-3">
        <div class="tc-card__body">
            <div class="d-flex justify-content-between align-items-center" style="font-size:13px;">
                <span style="font-weight:600;">Ocupación</span>
                <span style="color:var(--text-muted);">{{ $occupied }}{{ $cap ? ' / '.$cap : '' }} parejas</span>
            </div>
            @if($cap)
            <div class="progress mt-2" style="height:6px;background:var(--bg-subtle);border-radius:100px;">
                <div class="progress-bar" style="width:{{ $pct }}%;background:var(--accent);border-radius:100px;"></div>
            </div>
            @endif
            @if($belowMin)
            <div style="font-size:12px;color:var(--warning-text);margin-top:8px;">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                Por debajo del mínimo ({{ $category->min_pairs }}). Puedes continuar, pero considera más parejas.
            </div>
            @endif
        </div>
    </div>

    {{-- Add pair (manager path) --}}
    @unless($category->isFull())
    <div class="tc-card mb-3">
        <div class="tc-card__head">
            <h3>Agregar pareja</h3>
        </div>
        <div class="tc-card__body">
            <form method="POST" action="{{ route('pairs.store', [$tournament, $category]) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div style="font-size:12px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Jugador 1</div>
                        <input type="text" name="player1_name" placeholder="Nombre*" required
                            class="form-control mb-2 @error('player1_name') is-invalid @enderror" style="border-radius:var(--radius);">
                        @error('player1_name')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <input type="email" name="player1_email" placeholder="Correo (opcional)" class="form-control mb-2" style="border-radius:var(--radius);">
                        <input type="text" name="player1_phone" placeholder="Teléfono (opcional)" class="form-control" style="border-radius:var(--radius);">
                    </div>
                    <div class="col-12 col-md-6">
                        <div style="font-size:12px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Jugador 2</div>
                        <input type="text" name="player2_name" placeholder="Nombre*" required
                            class="form-control mb-2 @error('player2_name') is-invalid @enderror" style="border-radius:var(--radius);">
                        @error('player2_name')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <input type="email" name="player2_email" placeholder="Correo (opcional)" class="form-control mb-2" style="border-radius:var(--radius);">
                        <input type="text" name="player2_phone" placeholder="Teléfono (opcional)" class="form-control" style="border-radius:var(--radius);">
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between mt-3">
                    <div class="form-check">
                        <input type="checkbox" name="mark_paid" id="mp" value="1" class="form-check-input">
                        <label for="mp" class="form-check-label" style="font-size:13px;">Marcar como pagada</label>
                    </div>
                    <button type="submit" class="btn btn-accent"><i class="fa-solid fa-plus me-1"></i> Agregar pareja</button>
                </div>
            </form>
        </div>
    </div>
    @else
    <div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
        <i class="fa-solid fa-circle-info me-1"></i> Categoría llena. Aumenta el cupo para agregar más parejas.
    </div>
    @endunless

    {{-- Pairs list --}}
    <div class="tc-card">
        <div class="tc-card__head">
            <h3>Parejas inscritas</h3>
        </div>
        <div class="tc-table-wrap">
            <table class="tc-table">
                <thead>
                    <tr>
                        <th>Pareja</th>
                        <th>Origen</th>
                        <th>Pago</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($category->pairs as $pair)
                    @php $reg = $pair->registration; @endphp
                    <tr>
                        <td>{{ $pair->name() }}</td>
                        <td><x-pill variant="neutral">{{ $reg?->source->label() ?? '—' }}</x-pill></td>
                        <td>
                            <x-pill :variant="$reg?->payment_status->pillVariant() ?? 'neutral'" dot>
                                {{ $reg?->payment_status->label() ?? '—' }}
                            </x-pill>
                        </td>
                        <td style="text-align:right;">
                            <div class="d-inline-flex gap-2">
                                @if($reg && $reg->payment_status->value !== 'paid')
                                <form method="POST" action="{{ route('pairs.payment', [$tournament, $category, $pair]) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="payment_status" value="paid">
                                    <button class="btn btn-soft btn-sm" title="Marcar pagada"><i class="fa-solid fa-check"></i></button>
                                </form>
                                @endif
                                @unless($tournament->isLocked())
                                <form method="POST" action="{{ route('pairs.destroy', [$tournament, $category, $pair]) }}"
                                    data-confirm="¿Eliminar esta pareja?" data-confirm-title="Eliminar pareja" data-confirm-variant="danger" data-confirm-ok="Eliminar">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-soft btn-sm" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                                </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="color:var(--text-muted);">Sin parejas. Agrega una arriba o importa un CSV.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endsection