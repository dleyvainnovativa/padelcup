{{-- Category-scoped calendar: day + player-search filters, per-match share
     button, player links, and a "Sin programar" section at the end.
     Filters submit via GET with tab=calendar so this tab stays active on reload. --}}

@php
// Helper to render one player's name as a link to their public page, or plain
// text if the player record isn't available.
$catBase = ['tournament' => $tournament, 'category' => $category];
@endphp

{{-- Buscar mi partido --}}
<form method="GET" action="{{ route('public.category', $catBase) }}" class="pub-search">
    <input type="hidden" name="tab" value="calendar">
    @if($calDay)<input type="hidden" name="day" value="{{ $calDay }}">@endif
    <div class="pub-search__field">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" name="q" value="{{ $calSearch }}" placeholder="Buscar mi partido (jugador o pareja)…" autocomplete="off">
    </div>
    <button type="submit" class="pub-btn pub-btn--primary">Buscar</button>
    @if($calSearch !== '')
    <a href="{{ route('public.category', $catBase + ['tab' => 'calendar', 'day' => $calDay ?: null]) }}" class="pub-btn">Limpiar</a>
    @endif
</form>

{{-- Day chips --}}
@if($calAllDays->count() > 1)
<div class="pub-day-chips pb-3">
    <a href="{{ route('public.category', $catBase + ['tab' => 'calendar', 'q' => $calSearch ?: null]) }}"
        class="pub-day-chip {{ !$calDay ? 'is-active' : '' }}">Todos</a>
    @foreach($calAllDays as $day)
    @php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
    <a href="{{ route('public.category', $catBase + ['tab' => 'calendar', 'q' => $calSearch ?: null, 'day' => $day]) }}"
        class="pub-day-chip {{ $calDay === $day ? 'is-active' : '' }}">{{ $d->translatedFormat('D d M') }}</a>
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