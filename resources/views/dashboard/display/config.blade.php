@extends('layouts.app')

@section('title', 'Pantalla')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Pantalla'],
    ]" />

<div class="page-head">
    <div>
        <h1>Pantalla</h1>
        <div class="page-sub">Vista tipo aeropuerto para una TV — arma la URL y ábrela en la pantalla.</div>
    </div>
</div>

<div class="tc-card" x-data="{
        venues: [],
        day: '',
        max: 6, rotate: 12, refresh: 30, ahead: 180, back: 45,
        base: @js($displayBase),
        get url() {
            const p = new URLSearchParams();
            if (this.day) p.set('day', this.day);
            if (this.venues.length) p.set('venues', this.venues.join(','));
            if (+this.max !== 6) p.set('max', this.max);
            if (+this.rotate !== 12) p.set('rotate', this.rotate);
            if (+this.refresh !== 30) p.set('refresh', this.refresh);
            if (+this.ahead !== 180) p.set('ahead', this.ahead);
            if (+this.back !== 45) p.set('back', this.back);
            const q = p.toString();
            return this.base + (q ? ('?' + q) : '');
        },
        copy() { navigator.clipboard?.writeText(this.url); }
     }">
    <div class="tc-card__body dc">
        @if($venues->count())
        <div class="dc__field">
            <label class="dc__label">Sedes</label>
            <div class="dc__hint">Sin seleccionar = todas las sedes.</div>
            <div class="dc__venues">
                @foreach($venues as $v)
                <label class="dc__check">
                    <input type="checkbox" value="{{ $v->id }}" x-model.number="venues">
                    <span>{{ $v->name }}</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="dc__field">
            <label class="dc__label">Día</label>
            <select x-model="day" class="pub-select">
                <option value="">En vivo (en juego + próximos)</option>
                @foreach($days as $d)
                <option value="{{ $d['value'] }}">{{ $d['label'] }}</option>
                @endforeach
            </select>
            <div class="dc__hint">Fija un día para mostrar solo ese día completo (con resultados). Vacío = modo en vivo con rotación.</div>
        </div>

        <div class="dc__grid">
            <div class="dc__field">
                <label class="dc__label">Máx. partidos por página</label>
                <input type="number" min="1" max="20" x-model.number="max" class="pub-select">
                <div class="dc__hint">6 es cómodo para una TV.</div>
            </div>
            <div class="dc__field">
                <label class="dc__label">Rotación entre páginas (seg)</label>
                <input type="number" min="4" max="120" x-model.number="rotate" class="pub-select">
            </div>
            <div class="dc__field">
                <label class="dc__label">Actualizar datos (seg)</label>
                <input type="number" min="10" max="300" x-model.number="refresh" class="pub-select">
            </div>
            <div class="dc__field">
                <label class="dc__label">Mostrar próximos hasta (min)</label>
                <input type="number" min="15" max="720" x-model.number="ahead" class="pub-select">
            </div>
            <div class="dc__field">
                <label class="dc__label">Mantener terminados (min)</label>
                <input type="number" min="0" max="360" x-model.number="back" class="pub-select">
            </div>
        </div>

        <div class="dc__field">
            <label class="dc__label">Enlace de la pantalla</label>
            <div class="dc__url"><code x-text="url"></code></div>
        </div>

        <div class="dc__actions">
            <a :href="url" target="_blank" class="btn btn-accent"><i class="fa-solid fa-display me-1"></i> Abrir pantalla</a>
            <button type="button" class="btn btn-soft" @click="copy()"><i class="fa-solid fa-copy me-1"></i> Copiar enlace</button>
        </div>
    </div>
</div>
@endsection
