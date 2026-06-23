@extends('layouts.app')

@section('title', 'Editar torneo')

@section('content')
<div class="page-head">
    <div>
        <h1>Editar torneo</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
</div>

<div class="tc-card" style="max-width:680px;">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('tournaments.update', $tournament) }}"
            id="tournament-edit-form"
            enctype="multipart/form-data"
            data-scheduled-count="{{ $scheduledCount }}"
            data-orig-start="{{ \Illuminate\Support\Str::of($tournament->play_start)->substr(0,5) }}"
            data-orig-end="{{ \Illuminate\Support\Str::of($tournament->play_end)->substr(0,5) }}"
            data-orig-duration="{{ $tournament->match_duration_minutes }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Nombre</label>
                <input type="text" name="name" value="{{ old('name', $tournament->name) }}" required
                    class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Descripción</label>
                <textarea name="description" rows="2" class="form-control" style="border-radius:var(--radius);">{{ old('description', $tournament->description) }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Imagen del torneo</label>
                @if($tournament->coverImageUrl())
                <div class="mb-2">
                    <img src="{{ $tournament->coverImageUrl() }}" alt="Imagen actual"
                        style="max-height:120px;border-radius:var(--radius);border:1px solid var(--border);">
                </div>
                @endif
                <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"
                    class="form-control @error('cover_image') is-invalid @enderror" style="border-radius:var(--radius);">
                <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">Se muestra en la página pública. JPG, PNG o WEBP, máx 4 MB.</div>
                @error('cover_image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Inicio</label>
                    <input type="date" name="starts_on" value="{{ old('starts_on', $tournament->starts_on?->toDateString()) }}" class="form-control" style="border-radius:var(--radius);">
                </div>
                <div class="col-6">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Fin</label>
                    <input type="date" name="ends_on" value="{{ old('ends_on', $tournament->ends_on?->toDateString()) }}"
                        class="form-control @error('ends_on') is-invalid @enderror" style="border-radius:var(--radius);">
                    @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Hora inicio (juego)</label>
                    <input type="time" name="play_start" value="{{ old('play_start', \Illuminate\Support\Str::of($tournament->play_start)->substr(0,5)) }}" class="form-control" style="border-radius:var(--radius);">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Hora fin (juego)</label>
                    <input type="time" name="play_end" value="{{ old('play_end', \Illuminate\Support\Str::of($tournament->play_end)->substr(0,5)) }}" class="form-control" style="border-radius:var(--radius);">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Duración partido (min)</label>
                    <input type="number" name="match_duration_minutes" min="30" max="240" step="5" value="{{ old('match_duration_minutes', $tournament->match_duration_minutes) }}" class="form-control" style="border-radius:var(--radius);">
                </div>
            </div>
            <div style="font-size:12px;color:var(--text-faint);margin-top:6px;">
                El calendario usa estos valores para crear los espacios (slots) y el horario disponible de todas las canchas.
            </div>
            <div class="form-check mt-3">
                <input type="checkbox" name="is_listed" id="is_listed" value="1" class="form-check-input" @checked(old('is_listed', $tournament->is_listed))>
                <label for="is_listed" class="form-check-label" style="font-size:13px;">
                    Mostrar en el directorio público de torneos
                    <span style="display:block;font-size:12px;color:var(--text-faint);">Cualquier persona podrá encontrar e inscribirse a este torneo.</span>
                </label>
            </div>

            <label style="display:flex;align-items:center;gap:8px;font-size:13px;">
                <input type="checkbox" name="hide_global_ads" value="1"
                    @checked(old('hide_global_ads', $tournament->hide_global_ads ?? false))>
                Ocultar anuncios globales en este torneo
            </label>

            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-accent">Guardar</button>
                    <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-soft">Cancelar</a>
                </div>
            </div>
        </form>

        <hr style="border-color:var(--border);margin:20px 0;">
        <form method="POST" action="{{ route('tournaments.destroy', $tournament) }}"
            data-confirm="Se eliminará el torneo y todo su contenido (categorías, parejas, partidos). Esta acción no se puede deshacer." data-confirm-title="Eliminar torneo" data-confirm-variant="danger" data-confirm-ok="Eliminar">
            @csrf @method('DELETE')
            <button class="btn btn-soft" style="color:var(--danger-text);"><i class="fa-solid fa-trash me-1"></i> Eliminar torneo</button>
        </form>
    </div>
</div>

<script>
    (function() {
        const form = document.getElementById('tournament-edit-form');
        if (!form) return;
        const scheduledCount = parseInt(form.dataset.scheduledCount || '0', 10);
        if (scheduledCount === 0) return; // nothing scheduled → no warning needed

        let confirmed = false;

        form.addEventListener('submit', async function(e) {
            if (confirmed) return; // second pass after confirmation

            const start = form.querySelector('[name="play_start"]')?.value || '';
            const end = form.querySelector('[name="play_end"]')?.value || '';
            const dur = form.querySelector('[name="match_duration_minutes"]')?.value || '';

            const changed =
                start !== form.dataset.origStart ||
                end !== form.dataset.origEnd ||
                String(dur) !== String(form.dataset.origDuration);

            if (!changed) return; // scheduling fields untouched → submit normally

            e.preventDefault();

            const proceed = window.tcConfirm ?
                await window.tcConfirm({
                    title: 'Cambiar horario del torneo',
                    body: 'Cambiar la duración o la ventana de juego puede sacar algunos partidos del calendario. Los que queden fuera del nuevo horario se moverán a «Sin programar» (los demás se conservan). ¿Continuar?',
                    confirmText: 'Continuar',
                    variant: 'accent',
                }) :
                window.confirm('Cambiar el horario puede mover algunos partidos a «Sin programar». ¿Continuar?');

            if (proceed) {
                confirmed = true;
                form.submit();
            }
        });
    })();
</script>
@endsection