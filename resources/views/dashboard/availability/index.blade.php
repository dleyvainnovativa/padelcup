@extends('layouts.app')

@section('title', 'Disponibilidad · '.$tournament->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Disponibilidad'],
    ]" />

<div class="page-head">
    <div>
        <h1>Disponibilidad de jugadores</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-calendar-days me-1"></i> Ir al calendario</a>
</div>

@include('dashboard.partials.flash')

<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--bg-subtle);color:var(--text-muted);">
    <i class="fa-solid fa-circle-info me-1"></i>
    Indica desde qué hora puede jugar cada jugador en un día (ej. «disponible desde las 19:00 el viernes»).
    Un día sin hora no tiene restricción. Solo necesitas configurar a quienes tengan horarios especiales.
    El calendario automático respetará estas reglas.
</div>

<div class="tc-card mb-3">
    <div class="tc-card__body">
        <input type="text" id="availSearch" placeholder="Buscar jugador…" autocomplete="off"
            class="form-control" style="border-radius:var(--radius);max-width:320px;">
    </div>
</div>

<div data-avail-root
    data-store-url="{{ route('availability.player.store', $tournament) }}"
    data-csrf="{{ csrf_token() }}">
    @forelse($people as $person)
    @php
    $rules = $availability[$person['key']] ?? [];
    $ruleCount = count($rules);
    @endphp
    <div class="avail-person" data-name="{{ \Illuminate\Support\Str::lower($person['name']) }}">
        <details>
            <summary class="avail-person__head">
                <span class="avail-person__name">{{ $person['name'] }}</span>
                <span class="avail-person__cats">{{ implode(', ', $person['categories']) }}</span>
                @if($ruleCount)
                <span class="avail-badge">{{ $ruleCount }} {{ $ruleCount === 1 ? 'regla' : 'reglas' }}</span>
                @else
                <span class="avail-badge avail-badge--empty">Sin reglas</span>
                @endif
            </summary>
            <div class="avail-days">
                @foreach($playDays as $d)
                @php $val = $rules[$d['ymd']] ?? ''; @endphp
                <label class="avail-day">
                    <span class="avail-day__label">{{ $d['label'] }}</span>
                    <span class="avail-day__from">desde</span>
                    <input type="time"
                        class="form-control form-control-sm avail-input"
                        value="{{ $val }}"
                        data-name="{{ $person['key'] }}"
                        data-day="{{ $d['ymd'] }}"
                        style="width:auto;border-radius:var(--radius);">
                    <span class="avail-day__status" data-status></span>
                </label>
                @endforeach
            </div>
        </details>
    </div>
    @empty
    <div class="tc-card">
        <div class="tc-card__body" style="color:var(--text-muted);">
            No hay jugadores en este torneo todavía. Importa o agrega parejas primero.
        </div>
    </div>
    @endforelse
</div>

<script>
    (function() {
        var root = document.querySelector('[data-avail-root]');
        if (!root) return;
        var url = root.dataset.storeUrl;
        var csrf = root.dataset.csrf;

        var search = document.getElementById('availSearch');
        if (search) {
            search.addEventListener('input', function() {
                var q = search.value.trim().toLowerCase();
                document.querySelectorAll('.avail-person').forEach(function(el) {
                    el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
                });
            });
        }

        root.addEventListener('change', function(e) {
            var input = e.target.closest('.avail-input');
            if (!input) return;
            var statusEl = input.parentElement.querySelector('[data-status]');
            if (statusEl) {
                statusEl.textContent = '…';
                statusEl.className = 'avail-day__status is-saving';
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    normalized_name: input.dataset.name,
                    day: input.dataset.day,
                    earliest_time: input.value || null,
                }),
            }).then(function(r) {
                if (!r.ok) throw new Error();
                if (statusEl) {
                    statusEl.textContent = '✓';
                    statusEl.className = 'avail-day__status is-ok';
                }
                updateBadge(input);
                if (statusEl) setTimeout(function() {
                    statusEl.textContent = '';
                }, 1200);
            }).catch(function() {
                if (statusEl) {
                    statusEl.textContent = '✗';
                    statusEl.className = 'avail-day__status is-err';
                }
            });
        });

        function updateBadge(input) {
            var person = input.closest('.avail-person');
            var count = [...person.querySelectorAll('.avail-input')].filter(function(i) {
                return i.value;
            }).length;
            var badge = person.querySelector('.avail-badge');
            if (!badge) return;
            if (count) {
                badge.textContent = count + (count === 1 ? ' regla' : ' reglas');
                badge.classList.remove('avail-badge--empty');
            } else {
                badge.textContent = 'Sin reglas';
                badge.classList.add('avail-badge--empty');
            }
        }
    })();
</script>
@endsection