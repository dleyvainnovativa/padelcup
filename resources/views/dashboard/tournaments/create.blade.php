@extends('layouts.app')

@section('title', 'Nuevo torneo')

@section('content')
<div class="page-head">
    <div>
        <h1>Nuevo torneo</h1>
        <div class="page-sub">Datos básicos. Podrás agregar categorías, sedes y canchas después.</div>
    </div>
</div>

<div class="tc-card" style="max-width:680px;">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('tournaments.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Nombre del torneo</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Descripción</label>
                <textarea name="description" rows="2"
                    class="form-control @error('description') is-invalid @enderror"
                    style="border-radius:var(--radius);">{{ old('description') }}</textarea>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Inicio</label>
                    <input type="date" name="starts_on" value="{{ old('starts_on') }}"
                        class="form-control @error('starts_on') is-invalid @enderror" style="border-radius:var(--radius);">
                    @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Fin</label>
                    <input type="date" name="ends_on" value="{{ old('ends_on') }}"
                        class="form-control @error('ends_on') is-invalid @enderror" style="border-radius:var(--radius);">
                    @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Apertura de inscripción</label>
                    <input type="datetime-local" name="registration_opens_at" value="{{ old('registration_opens_at') }}"
                        class="form-control" style="border-radius:var(--radius);">
                </div>
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Cierre de inscripción</label>
                    <input type="datetime-local" name="registration_closes_at" value="{{ old('registration_closes_at') }}"
                        class="form-control @error('registration_closes_at') is-invalid @enderror" style="border-radius:var(--radius);">
                    @error('registration_closes_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div style="font-size:12px;color:var(--text-faint);margin-top:4px;">
                        Cierra la inscripción al menos una semana antes del inicio para que los pagos se reflejen a tiempo.
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Tu comisión por jugador (centavos MXN)</label>
                    <input type="number" name="platform_fee_centavos" value="{{ old('platform_fee_centavos', 5000) }}" min="0"
                        class="form-control" style="border-radius:var(--radius);">
                    <div style="font-size:12px;color:var(--text-faint);margin-top:4px;">5000 = $50.00 MXN</div>
                </div>
            </div>

            <div class="form-check mt-3">
                <input type="checkbox" name="is_listed" id="is_listed" value="1" class="form-check-input" @checked(old('is_listed'))>
                <label for="is_listed" class="form-check-label" style="font-size:13px;">
                    Mostrar en el directorio público de torneos
                    <span style="display:block;font-size:12px;color:var(--text-faint);">Cualquier persona podrá encontrar e inscribirse a este torneo.</span>
                </label>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-accent">Crear torneo</button>
                <a href="{{ route('tournaments.index') }}" class="btn btn-soft">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection