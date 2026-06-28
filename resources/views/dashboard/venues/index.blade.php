@extends('layouts.app')

@section('title', 'Sedes y canchas')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Sedes y canchas'],
    ]" />

<div class="page-head">
    <div>
        <h1>Sedes y canchas</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-calendar-days me-1"></i> Ir al calendario</a>
</div>

@include('dashboard.partials.flash')
@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--bg-subtle);color:var(--text-muted);">
    <i class="fa-solid fa-circle-info me-1"></i>
    Cada cancha se crea con la ventana de juego del torneo
    ({{ \Illuminate\Support\Str::of($tournament->play_start)->substr(0,5) }}–{{ \Illuminate\Support\Str::of($tournament->play_end)->substr(0,5) }},
    partidos de {{ $tournament->match_duration_minutes }} min). Puedes agregar ventanas
    personalizadas por cancha y día abajo (p. ej. una cancha libre solo de 18:00 a 22:00 el viernes).
    <a href="{{ route('tournaments.edit', $tournament) }}">Cambiar ajustes</a>.
    <form method="POST" action="{{ route('availability.resync', $tournament) }}" class="d-inline"
        data-confirm="Esto reemplaza TODAS las ventanas (incluidas las personalizadas) por la ventana del torneo. ¿Continuar?"
        data-confirm-title="Actualizar horarios" data-confirm-ok="Actualizar">
        @csrf
        <button class="btn btn-soft btn-sm ms-2">Restablecer al horario del torneo</button>
    </form>
</div>

<div class="tc-card mb-3">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('venues.store', $tournament) }}" class="d-flex gap-2 flex-wrap align-items-end">
            @csrf
            <div>
                <label class="form-label" style="font-size:12px;">Nueva sede</label>
                <input type="text" name="name" placeholder="Nombre de la sede" required class="form-control" style="border-radius:var(--radius);">
            </div>
            <div style="flex:1;min-width:180px;">
                <label class="form-label" style="font-size:12px;">Dirección (opcional)</label>
                <input type="text" name="address" class="form-control" style="border-radius:var(--radius);">
            </div>
            <button class="btn btn-accent">Agregar sede</button>
        </form>
    </div>
</div>

@forelse($tournament->venues as $venue)
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3>{{ $venue->name }}</h3>
        <span style="font-size:12px;color:var(--text-faint);">{{ $venue->address }}</span>
    </div>
    <div class="tc-card__body">
        @php
        $playDays = collect($tournament->playDays());
        $dayFmt = fn($d) => \Illuminate\Support\Str::ucfirst($d->locale('es')->isoFormat('ddd D MMM'));
        @endphp

        @forelse($venue->courts as $court)
        <div class="court-block">
            <div class="court-block__head">
                <form method="POST" action="{{ route('courts.update', [$tournament, $court]) }}"
                    class="court-rename" data-court-rename>
                    @csrf @method('PATCH')
                    <input type="text" name="name" value="{{ $court->name }}" maxlength="255"
                        class="court-rename__input" aria-label="Nombre de la cancha">
                    <button type="submit" class="court-rename__save" title="Guardar nombre"><i class="fa-solid fa-check"></i></button>
                </form>
                <form method="POST" action="{{ route('courts.destroy', [$tournament, $court]) }}"
                    data-confirm="¿Eliminar «{{ $court->name }}»? Se quitará del calendario."
                    data-confirm-title="Eliminar cancha" data-confirm-variant="danger" data-confirm-ok="Eliminar">
                    @csrf @method('DELETE')
                    <button class="btn btn-soft btn-sm" style="color:var(--danger-text);padding:0 8px;">× Cancha</button>
                </form>
            </div>

            {{-- Current windows grouped by day --}}
            @php
            $byDay = $court->availabilities
            ->sortBy('starts_at')
            ->groupBy(fn($w) => $w->starts_at->timezone('America/Mexico_City')->format('Y-m-d'));
            @endphp
            @if($byDay->isEmpty())
            <div style="font-size:12px;color:var(--text-faint);margin:6px 0;">Sin horarios. Usa la ventana del torneo o agrega ventanas personalizadas abajo.</div>
            @else
            <div class="court-wins">
                @foreach($byDay as $ymd => $wins)
                @php $d = \Carbon\Carbon::parse($ymd, 'America/Mexico_City'); @endphp
                <div class="court-win-day">
                    <span class="court-win-day__label">{{ \Illuminate\Support\Str::ucfirst($d->locale('es')->isoFormat('ddd D MMM')) }}</span>
                    <div class="court-win-tags">
                        @foreach($wins as $w)
                        <span class="court-win-tag">
                            {{ $w->starts_at->timezone('America/Mexico_City')->format('H:i') }}–{{ $w->ends_at->timezone('America/Mexico_City')->format('H:i') }}
                            <form method="POST" action="{{ route('availability.destroy', [$tournament, $w]) }}" class="d-inline">
                                @csrf @method('DELETE')
                                <button class="court-win-tag__x" title="Quitar">×</button>
                            </form>
                        </span>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Add custom window --}}
            <form method="POST" action="{{ route('availability.store', [$tournament, $court]) }}" class="court-win-add">
                @csrf
                <select name="day" required class="form-select form-select-sm" style="width:auto;border-radius:var(--radius);">
                    <option value="">Día…</option>
                    @foreach($playDays as $d)
                    <option value="{{ $d->format('Y-m-d') }}">{{ $dayFmt($d) }}</option>
                    @endforeach
                </select>
                <input type="time" name="start_time" required class="form-control form-control-sm" style="width:auto;border-radius:var(--radius);">
                <span style="color:var(--text-faint);">→</span>
                <input type="time" name="end_time" required class="form-control form-control-sm" style="width:auto;border-radius:var(--radius);">
                <button class="btn btn-soft btn-sm"><i class="fa-solid fa-plus me-1"></i> Agregar ventana</button>
            </form>
        </div>
        @empty
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:10px;">Esta sede no tiene canchas todavía.</div>
        @endforelse

        <div class="court-add-row">
            <form method="POST" action="{{ route('courts.store', [$tournament, $venue]) }}" class="d-flex gap-2 align-items-end">
                @csrf
                <div>
                    <label class="form-label" style="font-size:12px;">Nueva cancha</label>
                    <input type="text" name="name" placeholder="Cancha 1" required class="form-control form-control-sm" style="border-radius:var(--radius);">
                </div>
                <button class="btn btn-soft btn-sm">Agregar cancha</button>
            </form>

            <span class="court-add-sep">o</span>

            <form method="POST" action="{{ route('courts.generate', [$tournament, $venue]) }}" class="d-flex gap-2 align-items-end">
                @csrf
                <div>
                    <label class="form-label" style="font-size:12px;">Generar varias</label>
                    <input type="number" name="count" min="1" max="20" value="4" required class="form-control form-control-sm" style="width:80px;border-radius:var(--radius);">
                </div>
                <button class="btn btn-soft btn-sm"><i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generar canchas</button>
            </form>
        </div>
    </div>
</div>
@empty
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay sedes. Agrega una para empezar a definir canchas.
    </div>
</div>
@endforelse
<script>
    (function() {
        // Inline court rename: save via AJAX so the page doesn't reload. The save
        // button only "lights up" when the name changed; Enter submits too.
        document.querySelectorAll('[data-court-rename]').forEach(function(form) {
            var input = form.querySelector('.court-rename__input');
            var original = input.value;

            function dirty() {
                return input.value.trim() !== original && input.value.trim() !== '';
            }

            function reflect() {
                form.classList.toggle('is-dirty', dirty());
            }
            input.addEventListener('input', reflect);

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!dirty()) return;
                var btn = form.querySelector('.court-rename__save');
                btn.disabled = true;
                fetch(form.action, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        name: input.value.trim()
                    }),
                }).then(function(r) {
                    if (!r.ok) throw new Error();
                    return r.json();
                }).then(function(data) {
                    original = data.name || input.value.trim();
                    input.value = original;
                    form.classList.remove('is-dirty');
                    btn.disabled = false;
                    btn.classList.add('is-ok');
                    setTimeout(function() {
                        btn.classList.remove('is-ok');
                    }, 1000);
                }).catch(function() {
                    btn.disabled = false;
                    // Fallback: submit normally so the manager still gets the rename.
                    form.submit();
                });
            });
        });
    })();
</script>
@endsection