{{--
    "Revisar conflictos" report. Flashed by ScheduleController::conflicts():
      conflicts      → per-player overlap / little rest (possible = Mexicano R2 candidacy)
      scheduleLoad   → players with 3+ matches in one day (sure + possible)
      scheduleOrder  → per-category play order: errors / warnings
--}}
@php
    $conflicts = session('conflicts', []);
    $load = session('scheduleLoad', []);
    $order = session('scheduleOrder', []);

    $orderErrors = collect($order)->sum(fn($c) => count($c['errors']));
    $orderWarnings = collect($order)->sum(fn($c) => count($c['warnings']));
    $catsWithErrors = collect($order)->filter(fn($c) => count($c['errors']) > 0)->count();
    $allClean = empty($conflicts) && empty($load) && $orderErrors === 0 && $orderWarnings === 0;
@endphp

@if($allClean)
<div class="tc-card mb-3" style="border-color:color-mix(in srgb, var(--success) 40%, transparent);">
    <div class="tc-card__body" style="color:var(--success-text);font-size:13px;">
        <i class="fa-solid fa-circle-check me-1"></i>
        Todo en orden: sin partidos encimados, nadie con 3 o más partidos en un día y cada categoría respeta el orden de rondas.
    </div>
</div>
@else

{{-- Summary strip --}}
<div class="audit-summary mb-3">
    <span class="audit-summary__item {{ $orderErrors ? 'is-bad' : ($orderWarnings ? 'is-warn' : 'is-ok') }}">
        <i class="fa-solid fa-list-ol"></i>
        Orden:
        @if($orderErrors)
            {{ $catsWithErrors }} {{ $catsWithErrors === 1 ? 'categoría con errores' : 'categorías con errores' }}
        @elseif($orderWarnings)
            {{ $orderWarnings }} {{ $orderWarnings === 1 ? 'aviso' : 'avisos' }}
        @else
            correcto
        @endif
    </span>
    <span class="audit-summary__item {{ count($load) ? 'is-warn' : 'is-ok' }}">
        <i class="fa-solid fa-calendar-day"></i>
        3+ partidos en un día: {{ count($load) }}
    </span>
    <span class="audit-summary__item {{ collect($conflicts)->where('severity', 'overlap')->where('possible', false)->count() ? 'is-bad' : (count($conflicts) ? 'is-warn' : 'is-ok') }}">
        <i class="fa-solid fa-user-clock"></i>
        Conflictos de jugadores: {{ count($conflicts) }}
    </span>
</div>

{{-- 1. Order per category --}}
@if(!empty($order))
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-list-ol me-1"></i> Orden por categoría</h3>
    </div>
    <div class="tc-card__body" style="display:flex;flex-direction:column;gap:10px;">
        @foreach($order as $cat)
        @php $ne = count($cat['errors']); $nw = count($cat['warnings']); @endphp
        <div class="audit-cat {{ $ne ? 'audit-cat--bad' : ($nw ? 'audit-cat--warn' : 'audit-cat--ok') }}">
            <div class="audit-cat__head">
                <span class="audit-cat__name">{{ $cat['category'] }}</span>
                @if(!$ne && !$nw)
                    <span class="pill pill--ok"><i class="fa-solid fa-check"></i> En orden</span>
                @endif
                @if($ne)<span class="pill pill--bad">{{ $ne }} {{ $ne === 1 ? 'error' : 'errores' }}</span>@endif
                @if($nw)<span class="pill pill--warn">{{ $nw }} {{ $nw === 1 ? 'aviso' : 'avisos' }}</span>@endif
            </div>

            @foreach($cat['errors'] as $item)
            <div class="conflict-row conflict-row--overlap">
                <div class="conflict-row__player" style="font-weight:500;">
                    <i class="fa-solid fa-triangle-exclamation"></i> {{ $item['message'] }}
                </div>
                @include('dashboard.schedule.partials.audit-matches', ['items' => $item['matches']])
            </div>
            @endforeach

            @if($nw)
            <details class="audit-details" @if(!$ne) open @endif>
                <summary>{{ $nw === 1 ? 'Ver 1 aviso' : "Ver {$nw} avisos" }}</summary>
                @foreach($cat['warnings'] as $item)
                <div class="conflict-row conflict-row--rest">
                    <div class="conflict-row__player" style="font-weight:500;">
                        <i class="fa-solid fa-clock"></i> {{ $item['message'] }}
                    </div>
                    @include('dashboard.schedule.partials.audit-matches', ['items' => $item['matches']])
                </div>
                @endforeach
            </details>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- 2. Players with 3+ matches in one day --}}
@if(!empty($load))
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-calendar-day me-1"></i> Jugadores con 3 o más partidos en un día ({{ count($load) }})</h3>
    </div>
    <div class="tc-card__body" style="display:flex;flex-direction:column;gap:8px;">
        @foreach($load as $row)
        <div class="conflict-row {{ $row['sure'] >= 3 ? 'conflict-row--overlap' : 'conflict-row--rest' }}">
            <div class="conflict-row__player">
                <i class="fa-solid fa-person-running"></i>
                {{ $row['player'] }}
                <span class="conflict-row__tag">{{ $row['day_label'] }}</span>
                <span class="audit-load">
                    {{ $row['total'] }} partidos
                    @if($row['possible'])
                        <span class="audit-load__detail">({{ $row['sure'] }} {{ $row['sure'] === 1 ? 'seguro' : 'seguros' }} + {{ $row['possible'] }} {{ $row['possible'] === 1 ? 'posible' : 'posibles' }})</span>
                    @endif
                </span>
            </div>
            @include('dashboard.schedule.partials.audit-matches', ['items' => $row['matches']])
        </div>
        @endforeach
        <p class="audit-note">
            <strong>R2 (uno de 2)</strong>: el jugador jugará solo uno de esos partidos de Ronda 2, depende de si gana o pierde en R1 — cuenta como 1.
            <strong>Posible</strong>: las opciones de R2 caen en días distintos, así que puede tocarle este día o el otro.
        </p>
    </div>
</div>
@endif

{{-- 3. Player conflicts (overlap / rest) --}}
@if(!empty($conflicts))
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-user-clock me-1"></i> Conflictos de jugadores ({{ count($conflicts) }})</h3>
    </div>
    <div class="tc-card__body" style="display:flex;flex-direction:column;gap:8px;">
        @foreach($conflicts as $c)
        <div class="conflict-row conflict-row--{{ $c['severity'] }} {{ !empty($c['possible']) ? 'conflict-row--possible' : '' }}">
            <div class="conflict-row__player">
                <i class="fa-solid {{ $c['severity'] === 'overlap' ? 'fa-triangle-exclamation' : 'fa-clock' }}"></i>
                {{ $c['player'] }}
                <span class="conflict-row__tag">{{ $c['severity'] === 'overlap' ? 'Se encima' : 'Poco descanso' }}</span>
                @if(!empty($c['possible']))
                    <span class="conflict-row__tag conflict-row__tag--possible" title="Depende del resultado de R1">Posible</span>
                @endif
            </div>
            @include('dashboard.schedule.partials.audit-matches', ['items' => $c['matches']])
        </div>
        @endforeach
        <p class="audit-note">
            Mueve o quita uno de los partidos en conflicto para resolverlo, luego vuelve a revisar.
            Los <strong>posibles</strong> dependen de un resultado de R1: solo ocurren si el jugador gana (o pierde) su primer partido.
        </p>
    </div>
</div>
@endif

@endif
