@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
<div class="page-head">
    <div>
        <h1>Nueva categoría</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
</div>

<div class="tc-card" style="max-width:680px;">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('categories.store', $tournament) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Nombre</label>
                <input type="text" name="name" value="{{ old('name') }}" required placeholder="5ta Femenil"
                    class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Formato</label>
                    <select name="format" class="form-select @error('format') is-invalid @enderror" style="border-radius:var(--radius);">
                        <option value="round_robin" @selected(old('format')==='round_robin' )>Round-robin</option>
                        <option value="elimination" @selected(old('format')==='elimination' )>Eliminación</option>
                        <option value="hybrid" @selected(old('format')==='hybrid' )>Grupos → eliminación</option>
                    </select>
                    @error('format')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Grupo preferido</label>
                    <select name="preferred_group_size" class="form-select" style="border-radius:var(--radius);">
                        <option value="3" @selected(old('preferred_group_size')==='3' )>3 parejas</option>
                        <option value="4" @selected(old('preferred_group_size', '4' )==='4' )>4 parejas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Avanzan/grupo</label>
                    <input type="number" name="advance_per_group" value="{{ old('advance_per_group', 2) }}" min="1" max="5"
                        class="form-control" style="border-radius:var(--radius);">
                </div>
            </div>

            <div class="row g-3 mt-0" x-data="{ gf: '{{ old('group_format', 'round_robin') }}' }">
                <div class="col-12 col-md-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Formato de grupos de 4</label>
                    <select name="group_format" x-model="gf" class="form-select" style="border-radius:var(--radius);">
                        <option value="round_robin">Round robin (todos contra todos)</option>
                        <option value="mexicano">Mexicano (2 rondas)</option>
                    </select>
                    <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">
                        Solo aplica a grupos de 4 parejas. Los grupos de 3 y 5 siempre juegan round robin.
                    </div>
                </div>
                <div class="col-12 col-md-6" x-show="gf === 'mexicano'" x-cloak>
                    <label class="form-label" style="font-size:13px;font-weight:500;">Emparejamiento ronda 2</label>
                    <select name="mexicano_pairing" class="form-select" style="border-radius:var(--radius);">
                        <option value="cross" @selected(old('mexicano_pairing','cross')==='cross' )>Cruzado (ganador vs perdedor)</option>
                        <option value="classic" @selected(old('mexicano_pairing')==='classic' )>Clásico (ganadores y perdedores)</option>
                    </select>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Clasificados extra</label>
                    <input type="number" name="extra_qualifiers" value="{{ old('extra_qualifiers', 0) }}" min="0" max="16"
                        class="form-control" style="border-radius:var(--radius);">
                    <div style="font-size:12px;color:var(--text-faint);margin-top:4px;">
                        Mejores no clasificados (a través de los grupos) para completar la llave. Ej: 5 grupos × 1 + 1 extra = 6.
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-4">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Cupo mín.</label>
                    <input type="number" name="min_pairs" value="{{ old('min_pairs', 4) }}" min="0"
                        class="form-control" style="border-radius:var(--radius);">
                </div>
                <div class="col-4">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Cupo máx.</label>
                    <input type="number" name="max_pairs" value="{{ old('max_pairs') }}" min="1"
                        class="form-control @error('max_pairs') is-invalid @enderror" style="border-radius:var(--radius);">
                    @error('max_pairs')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-4">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Precio (centavos)</label>
                    <input type="number" name="price_centavos" value="{{ old('price_centavos', 120000) }}" min="0"
                        class="form-control" style="border-radius:var(--radius);">
                </div>
            </div>

            <div class="mb-3 mt-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Grupo de WhatsApp (opcional)</label>
                <input type="url" name="whatsapp_group_url" value="{{ old('whatsapp_group_url') }}"
                    placeholder="https://chat.whatsapp.com/…"
                    class="form-control @error('whatsapp_group_url') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('whatsapp_group_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="has_third_place" id="htp" value="1" class="form-check-input" @checked(old('has_third_place'))>
                <label for="htp" class="form-check-label" style="font-size:13px;">Incluir partido por 3er lugar (eliminación)</label>
            </div>

            <div class="d-flex gap-2 mt-2">
                <button type="submit" class="btn btn-accent">Crear categoría</button>
                <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-soft">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection