@extends('layouts.app')

@section('title', 'Editar categoría')

@section('content')
<div class="page-head">
    <div>
        <h1>Editar categoría</h1>
        <div class="page-sub">{{ $category->name }} · {{ $tournament->name }}</div>
    </div>
</div>

<div class="tc-card" style="max-width:680px;">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('categories.update', [$tournament, $category]) }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Nombre</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required
                    class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Formato</label>
                    <select name="format" class="form-select" style="border-radius:var(--radius);">
                        <option value="round_robin" @selected($category->format->value==='round_robin')>Round-robin</option>
                        <option value="elimination" @selected($category->format->value==='elimination')>Eliminación</option>
                        <option value="hybrid" @selected($category->format->value==='hybrid')>Grupos → eliminación</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Grupo preferido</label>
                    <select name="preferred_group_size" class="form-select" style="border-radius:var(--radius);">
                        <option value="3" @selected($category->preferred_group_size===3)>3 parejas</option>
                        <option value="4" @selected($category->preferred_group_size===4)>4 parejas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Avanzan/grupo</label>
                    <input type="number" name="advance_per_group" value="{{ old('advance_per_group', $category->advance_per_group) }}" min="1" max="5" class="form-control" style="border-radius:var(--radius);">
                </div>
            </div>

            <div class="row g-3 mt-0" x-data="{ gf: '{{ old('group_format', $category->group_format instanceof \App\Enums\GroupFormat ? $category->group_format->value : ($category->group_format ?? 'round_robin')) }}' }">
                <div class="col-12 col-md-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Formato de grupos de 4</label>
                    @php
                    $gfVal = old('group_format', $category->group_format instanceof \App\Enums\GroupFormat
                    ? $category->group_format->value
                    : ($category->group_format ?? 'round_robin'));
                    @endphp
                    <select name="group_format" x-model="gf" class="form-select" style="border-radius:var(--radius);">
                        <option value="round_robin" @selected($gfVal==='round_robin' )>Round robin (todos contra todos)</option>
                        <option value="mexicano" @selected($gfVal==='mexicano' )>Mexicano (2 rondas)</option>
                    </select>
                    <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">
                        Solo aplica a grupos de 4 parejas. Los grupos de 3 y 5 siempre juegan round robin.
                    </div>
                </div>
                <div class="col-12 col-md-6" x-show="gf === 'mexicano'" x-cloak>
                    <label class="form-label" style="font-size:13px;font-weight:500;">Emparejamiento ronda 2</label>
                    @php
                    $pairingVal = old('mexicano_pairing', $category->mexicano_pairing instanceof \App\Enums\MexicanoPairing
                    ? $category->mexicano_pairing->value
                    : ($category->mexicano_pairing ?? 'cross'));
                    @endphp
                    <select name="mexicano_pairing" class="form-select" style="border-radius:var(--radius);">
                        <option value="cross" @selected($pairingVal==='cross' )>Cruzado (ganador vs perdedor)</option>
                        <option value="classic" @selected($pairingVal==='classic' )>Clásico (ganadores y perdedores)</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Clasificados extra</label>
                    <input type="number" name="extra_qualifiers" value="{{ old('extra_qualifiers', $category->extra_qualifiers) }}" min="0" max="16" class="form-control" style="border-radius:var(--radius);">
                    <div style="font-size:12px;color:var(--text-faint);margin-top:4px;">
                        Mejores no clasificados (a través de los grupos) para completar la llave.
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-4">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Cupo mín.</label>
                    <input type="number" name="min_pairs" value="{{ old('min_pairs', $category->min_pairs) }}" min="0" class="form-control" style="border-radius:var(--radius);">
                </div>
                <div class="col-4">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Cupo máx.</label>
                    <input type="number" name="max_pairs" value="{{ old('max_pairs', $category->max_pairs) }}" min="1"
                        class="form-control @error('max_pairs') is-invalid @enderror" style="border-radius:var(--radius);">
                    @error('max_pairs')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-4">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Precio (centavos)</label>
                    <input type="number" name="price_centavos" value="{{ old('price_centavos', $category->price_centavos) }}" min="0" class="form-control" style="border-radius:var(--radius);">
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Grupo de WhatsApp</label>
                <input type="url" name="whatsapp_group_url" value="{{ old('whatsapp_group_url', $category->whatsapp_group_url) }}"
                    class="form-control @error('whatsapp_group_url') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('whatsapp_group_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="has_third_place" id="htp" value="1" class="form-check-input" @checked($category->has_third_place)>
                <label for="htp" class="form-check-label" style="font-size:13px;">Incluir partido por 3er lugar</label>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-accent">Guardar</button>
                <a href="{{ route('categories.show', [$tournament, $category]) }}" class="btn btn-soft">Cancelar</a>
            </div>
        </form>

        <hr style="border-color:var(--border);margin:20px 0;">
        <form method="POST" action="{{ route('categories.destroy', [$tournament, $category]) }}"
            data-confirm="Se eliminará la categoría y todas sus parejas. Esta acción no se puede deshacer." data-confirm-title="Eliminar categoría" data-confirm-variant="danger" data-confirm-ok="Eliminar">
            @csrf @method('DELETE')
            <button class="btn btn-soft" style="color:var(--danger-text);"><i class="fa-solid fa-trash me-1"></i> Eliminar categoría</button>
        </form>
    </div>
</div>
@endsection