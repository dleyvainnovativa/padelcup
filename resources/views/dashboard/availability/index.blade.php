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
    Indica el rango horario en que cada jugador puede jugar un día (ej. «de 18:00 a 22:00 el viernes»).
    Deja «hasta» vacío si solo hay hora de inicio. Un día sin hora no tiene restricción.
    Solo configura a quienes tengan horarios especiales; el calendario automático los respeta.
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
                @php
                $rule = $rules[$d['ymd']] ?? null;
                $isOff = is_array($rule) ? !empty($rule['off']) : false;
                $from = (is_array($rule) && empty($rule['off'])) ? ($rule['from'] ?? '') : (is_array($rule) ? '' : ($rule ?? ''));
                $until = (is_array($rule) && empty($rule['off'])) ? ($rule['until'] ?? '') : '';
                @endphp
                <div class="avail-day">
                    <span class="avail-day__label">{{ $d['label'] }}</span>

                    <span class="avail-day__off">
                        <input type="checkbox"
                            class="avail-input--off"
                            {{ $isOff ? 'checked' : '' }}
                            data-name="{{ $person['key'] }}"
                            data-day="{{ $d['ymd'] }}">
                        <span style="font-size:12px;color:var(--text-muted);">No juega</span>
                    </span>

                    <span class="avail-day__from">de</span>
                    <input type="time"
                        class="form-control form-control-sm avail-input avail-input--from"
                        value="{{ $from }}" {{ $isOff ? 'disabled' : '' }}
                        data-name="{{ $person['key'] }}"
                        data-day="{{ $d['ymd'] }}"
                        style="width:auto;border-radius:var(--radius);">
                    <span class="avail-day__from">a</span>
                    <input type="time"
                        class="form-control form-control-sm avail-input avail-input--until"
                        value="{{ $until }}" {{ $isOff ? 'disabled' : '' }}
                        data-name="{{ $person['key'] }}"
                        data-day="{{ $d['ymd'] }}"
                        style="width:auto;border-radius:var(--radius);">
                    <span class="avail-day__status" data-status></span>
                </div>
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

        // Save when either the "from" or "until" input of a day changes; we send both.
        root.addEventListener('change', function(e) {
            // Off-day checkbox.
            var offEl = e.target.closest('.avail-input--off');
            if (offEl) {
                var label = offEl.closest('.avail-day');
                var fromEl = label.querySelector('.avail-input--from');
                var untilEl = label.querySelector('.avail-input--until');
                var statusEl = label.querySelector('[data-status]');
                var off = offEl.checked;

                // Grey the time inputs when off; clear them so they don't linger.
                fromEl.disabled = off;
                untilEl.disabled = off;
                if (off) {
                    fromEl.value = '';
                    untilEl.value = '';
                }

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
                        normalized_name: offEl.dataset.name,
                        day: offEl.dataset.day,
                        unavailable: off ? 1 : 0,
                        earliest_time: null,
                        latest_time: null,
                    }),
                }).then(function(r) {
                    if (!r.ok) throw new Error();
                    if (statusEl) {
                        statusEl.textContent = '✓';
                        statusEl.className = 'avail-day__status is-ok';
                    }
                    updateBadge(offEl);
                    if (statusEl) setTimeout(function() {
                        statusEl.textContent = '';
                    }, 1200);
                }).catch(function() {
                    if (statusEl) {
                        statusEl.textContent = '✗';
                        statusEl.className = 'avail-day__status is-err';
                    }
                });
                return;
            }

            var input = e.target.closest('.avail-input');
            if (!input) return;
            var label = input.closest('.avail-day');
            var fromEl = label.querySelector('.avail-input--from');
            var untilEl = label.querySelector('.avail-input--until');
            var statusEl = label.querySelector('[data-status]');

            // "until" without "from" is invalid — ignore until a from is set.
            if (!fromEl.value && untilEl.value) {
                if (statusEl) {
                    statusEl.textContent = '⚠';
                    statusEl.className = 'avail-day__status is-err';
                }
                return;
            }

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
                    earliest_time: fromEl.value || null,
                    latest_time: untilEl.value || null,
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
            var withFrom = [...person.querySelectorAll('.avail-input--from')].filter(function(i) {
                return i.value;
            }).length;
            var offCount = [...person.querySelectorAll('.avail-input--off')].filter(function(i) {
                return i.checked;
            }).length;
            var count = withFrom + offCount;
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