{{-- Category-scoped calendar: day + player-search filters, per-match share
     button, player links, and a "Sin programar" section at the end.
     Filters submit via GET with tab=calendar so this tab stays active on reload. --}}

@php
$catBase = ['tournament' => $tournament, 'category' => $category];

// Compose a calendar URL preserving the active filters (tab/q/day/group/phase)
// with the given overrides. Pass null to drop a filter (empties are stripped).
$calUrl = function (array $overrides = []) use ($tournament, $category, $calSearch, $calDay, $calGroup, $calPhase) {
    $params = array_merge([
        'tab' => 'calendar',
        'q' => $calSearch ?: null,
        'day' => $calDay ?: null,
        'group' => $calGroup ?: null,
        'phase' => $calPhase ?: null,
    ], $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return route('public.category', ['tournament' => $tournament, 'category' => $category] + $params);
};
@endphp

{{-- Buscar mi partido --}}
<form method="GET" action="{{ route('public.category', $catBase) }}" class="pub-search">
    <input type="hidden" name="tab" value="calendar">
    @if($calDay)<input type="hidden" name="day" value="{{ $calDay }}">@endif
    @if($calGroup)<input type="hidden" name="group" value="{{ $calGroup }}">@endif
    @if($calPhase)<input type="hidden" name="phase" value="{{ $calPhase }}">@endif
    <div class="pub-search__field">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="q" value="{{ $calSearch }}" placeholder="Buscar mi partido (jugador o pareja)…" autocomplete="off">
    </div>
    <button type="submit" class="pub-btn pub-btn--primary">Buscar</button>
    @if($calSearch !== '')
    <a href="{{ $calUrl(['q' => null]) }}" class="pub-btn">Limpiar</a>
    @endif
</form>

{{-- Group chips (group-stage matches) --}}
@if($calGroupNames->count() > 1)
<div class="pub-filter-chips">
    <span class="pub-filter-chips__label">Grupo:</span>
    <a href="{{ $calUrl(['group' => null]) }}" class="pub-filter-chip {{ !$calGroup ? 'is-active' : '' }}">Todos</a>
    @foreach($calGroupNames as $gName)
    <a href="{{ $calUrl(['group' => $gName]) }}" class="pub-filter-chip {{ $calGroup === $gName ? 'is-active' : '' }}">{{ $gName }}</a>
    @endforeach
</div>
@endif

{{-- Phase chips --}}
@if(count($calPhasesPresent) > 1)
<div class="pub-filter-chips">
    <span class="pub-filter-chips__label">Fase:</span>
    <a href="{{ $calUrl(['phase' => null]) }}" class="pub-filter-chip {{ !$calPhase ? 'is-active' : '' }}">Todas</a>
    @foreach($calPhasesPresent as $phaseKey)
    <a href="{{ $calUrl(['phase' => $phaseKey]) }}" class="pub-filter-chip {{ $calPhase === $phaseKey ? 'is-active' : '' }}">{{ \App\Support\SchedulePhase::label($phaseKey) }}</a>
    @endforeach
</div>
@endif

{{-- Day chips --}}
@if($calAllDays->count() > 1)
<div class="pub-day-chips pb-3">
    <a href="{{ $calUrl(['day' => null]) }}" class="pub-day-chip {{ !$calDay ? 'is-active' : '' }}">Todos</a>
    @foreach($calAllDays as $day)
    @php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
    <a href="{{ $calUrl(['day' => $day]) }}" class="pub-day-chip {{ $calDay === $day ? 'is-active' : '' }}">{{ $d->translatedFormat('D d M') }}</a>
    @endforeach
</div>
@endif

{{-- Search meta + matched player links --}}
@if($calSearch !== '')
<div class="pub-search-meta">
    {{ $calTotal }} {{ $calTotal === 1 ? 'partido' : 'partidos' }} para "{{ $calSearch }}"
</div>
@if($calMatchedPlayers->isNotEmpty())
<div class="pub-matched-players">
    <span class="pub-muted" style="font-size:12px;">Ver perfil:</span>
    @foreach($calMatchedPlayers as $p)
    <a href="{{ route('public.player', [$tournament, $p]) }}" class="pub-chip pub-chip--link">
        <i class="fa-solid fa-user"></i> {{ $p->name }}
    </a>
    @endforeach
</div>
@endif
@endif

@if($calByDay->isEmpty() && $calUnscheduled->isEmpty())
<div class="pub-empty">
    @if($calSearch !== '')
    No se encontraron partidos para "{{ $calSearch }}".
    @else
    Aún no hay partidos en esta categoría.
    @endif
</div>
@else
{{-- Scheduled matches, grouped by day --}}
@foreach($calByDay as $day => $matches)
@php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
<div class="pub-day">
    <div class="pub-day__title">{{ $d->translatedFormat('l d \d\e F') }}</div>
    <div class="pub-day__matches">
        @foreach($matches as $m)
        @include('public.partials.category-calendar-match', ['m' => $m, 'showTime' => true])
        @endforeach
    </div>
</div>
@endforeach

{{-- Unscheduled matches (no day filter applied to these) --}}
@if($calUnscheduled->isNotEmpty() && !$calDay)
<div class="pub-day">
    <div class="pub-day__title pub-day__title--muted"><i class="fa-regular fa-clock"></i> Sin programar</div>
    <div class="pub-day__matches">
        @foreach($calUnscheduled as $m)
        @include('public.partials.category-calendar-match', ['m' => $m, 'showTime' => false])
        @endforeach
    </div>
</div>
@endif
@endif