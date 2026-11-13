@extends('layouts.public')

@section('title', 'Calendario · '.$tournament->name)

@section('content')
<div class="pub-wrap" data-auto-refresh="60">
    <div class="pub-crumb">
        <a href="{{ route('public.tournament', $tournament) }}"><i class="fa-solid fa-chevron-left"></i> {{ $tournament->name }}</a>
    </div>
    <div class="pub-title-row">
        <h1>Calendario</h1>
        <div class="pub-title-actions">
            <span class="pub-live"><span class="pub-status__dot pub-status__dot--live"></span> En vivo</span>
            <button type="button" class="pub-btn pub-btn--icon" data-share="{{ route('public.schedule', $tournament) }}" data-share-title="Calendario · {{ $tournament->name }}">
                <i class="fa-solid fa-share-nodes"></i> Compartir
            </button>
        </div>
    </div>

    @php
    // Compose a schedule URL preserving active filters (q/cat/day/phase/venue).
    // `day` uses the RAW param ($dayParam) so the explicit `all` choice survives
    // across non-day links (see #6); drop a filter by passing null.
    $schedUrl = function (array $overrides = []) use ($tournament, $search, $categoryFilter, $dayParam, $phaseFilter, $venueFilter) {
        $params = array_merge([
            'q' => $search ?: null,
            'cat' => $categoryFilter ?: null,
            'day' => $dayParam ?: null,
            'phase' => $phaseFilter ?: null,
            'venue' => $venueFilter ?: null,
        ], $overrides);
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        return route('public.schedule', ['tournament' => $tournament] + $params);
    };
    // Secondary filters (categoría / fase / sede) → the Filtros badge + summary.
    $activeFilters = collect([$categoryFilter, $phaseFilter, $venueFilter])->filter()->count();
    $activeCatName = $categoryFilter ? optional($allCategories->firstWhere('id', (int) $categoryFilter))->name : null;
    $activeVenueName = $venueFilter ? data_get($allVenues->firstWhere('id', (int) $venueFilter), 'name') : null;
    @endphp

    <div x-data="{ filtersSheet: false }">
        {{-- Toolbar: search + Filtros button --}}
        <div class="pub-cal-toolbar">
            <form method="GET" action="{{ route('public.schedule', $tournament) }}" class="pub-search pub-cal-toolbar__search">
                <div class="pub-search__field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" value="{{ $search }}" placeholder="Buscar mi partido…" autocomplete="off">
                </div>
                @if($categoryFilter)<input type="hidden" name="cat" value="{{ $categoryFilter }}">@endif
                @if($dayParam)<input type="hidden" name="day" value="{{ $dayParam }}">@endif
                @if($phaseFilter)<input type="hidden" name="phase" value="{{ $phaseFilter }}">@endif
                @if($venueFilter)<input type="hidden" name="venue" value="{{ $venueFilter }}">@endif
                <button type="submit" class="pub-btn pub-btn--primary">Buscar</button>
                @if($search !== '')
                <a href="{{ $schedUrl(['q' => null]) }}" class="pub-btn">Limpiar</a>
                @endif
            </form>

            <button type="button" class="pub-btn pub-filter-btn" @click="filtersSheet = true">
                <i class="fa-solid fa-sliders"></i>
                <span>Filtros</span>
                @if($activeFilters)<span class="pub-filter-btn__count">{{ $activeFilters }}</span>@endif
            </button>
        </div>

        {{-- Day chips stay inline (the most-used filter) --}}
        @if($allDays->count() > 1)
        <div class="pub-day-chips">
            <a href="{{ $schedUrl(['day' => 'all']) }}"
                class="pub-day-chip {{ !$dayFilter ? 'is-active' : '' }}">Todos</a>
            @foreach($allDays as $day)
            @php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
            <a href="{{ $schedUrl(['day' => $day]) }}"
                class="pub-day-chip {{ $dayFilter === $day ? 'is-active' : '' }}">{{ $d->translatedFormat('D d M') }}</a>
            @endforeach
        </div>
        @endif

        {{-- Active secondary filters summary (tap × to remove) --}}
        @if($activeFilters)
        <div class="pub-active-filters">
            @if($activeCatName)<a href="{{ $schedUrl(['cat' => null]) }}" class="pub-active-chip">{{ $activeCatName }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($phaseFilter)<a href="{{ $schedUrl(['phase' => null]) }}" class="pub-active-chip">{{ \App\Support\SchedulePhase::label($phaseFilter) }} <i class="fa-solid fa-xmark"></i></a>@endif
            @if($activeVenueName)<a href="{{ $schedUrl(['venue' => null]) }}" class="pub-active-chip">{{ $activeVenueName }} <i class="fa-solid fa-xmark"></i></a>@endif
        </div>
        @endif

        {{-- Filtros bottomsheet (categoría / fase / sede) --}}
        <div class="sched-sheet-overlay" :class="{ 'is-open': filtersSheet }" x-show="filtersSheet" x-cloak @click.self="filtersSheet = false">
            <div class="sched-sheet" role="dialog" aria-modal="true">
                <div class="sched-sheet__handle"></div>
                <div class="sched-sheet__head">
                    <div><div class="sched-sheet__title">Filtros</div></div>
                    <button type="button" class="sched-sheet__close" aria-label="Cerrar" @click="filtersSheet = false">&times;</button>
                </div>
                <form method="GET" action="{{ route('public.schedule', $tournament) }}" class="pub-filter-form">
                    @if($search)<input type="hidden" name="q" value="{{ $search }}">@endif
                    @if($dayParam)<input type="hidden" name="day" value="{{ $dayParam }}">@endif

                    <label class="pub-filter-form__label">Categoría</label>
                    <select name="cat" class="pub-select pub-filter-form__control">
                        <option value="">Todas las categorías</option>
                        @foreach($allCategories as $c)
                        <option value="{{ $c->id }}" @selected((string)$categoryFilter===(string)$c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>

                    @if(count($allPhases) > 1)
                    <label class="pub-filter-form__label">Fase</label>
                    <select name="phase" class="pub-select pub-filter-form__control">
                        <option value="">Todas las fases</option>
                        @foreach($allPhases as $phaseKey)
                        <option value="{{ $phaseKey }}" @selected($phaseFilter===$phaseKey)>{{ \App\Support\SchedulePhase::label($phaseKey) }}</option>
                        @endforeach
                    </select>
                    @endif

                    @if($allVenues->count() > 1)
                    <label class="pub-filter-form__label">Sede</label>
                    <select name="venue" class="pub-select pub-filter-form__control">
                        <option value="">Todas las sedes</option>
                        @foreach($allVenues as $v)
                        <option value="{{ $v['id'] }}" @selected((int)$venueFilter===$v['id'])>{{ $v['name'] }}</option>
                        @endforeach
                    </select>
                    @endif

                    <div class="pub-filter-form__actions">
                        <a href="{{ $schedUrl(['cat' => null, 'phase' => null, 'venue' => null]) }}" class="pub-btn">Limpiar</a>
                        <button type="submit" class="pub-btn pub-btn--primary">Aplicar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($search !== '')
    <div class="pub-search-meta">
        {{ $total }} {{ $total === 1 ? 'partido' : 'partidos' }} para "{{ $search }}"
    </div>
    @if($matchedPlayers->isNotEmpty())
    <div class="pub-matched-players">
        <span class="pub-muted" style="font-size:12px;">Ver perfil:</span>
        @foreach($matchedPlayers as $p)
        <a href="{{ route('public.player', [$tournament, $p]) }}" class="pub-chip pub-chip--link">
            <i class="fa-solid fa-user"></i> {{ $p->name }}
        </a>
        @endforeach
    </div>
    @endif
    @endif

    @if($byDay->isEmpty())
    <div class="pub-empty">
        @if($search !== '')
        No se encontraron partidos para "{{ $search }}".
        @else
        Aún no hay partidos programados.
        @endif
    </div>
    @else
    @foreach($byDay as $day => $matches)
    @php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
    <div class="pub-day">
        <div class="pub-day__title">{{ $d->translatedFormat('l d \d\e F') }}</div>
        <div class="pub-day__matches">
            @foreach($matches as $m)
            @php
            $status = $m->scheduleStatus();
            $played = $m->state->value === 'confirmed';
            // Linked side labels: each real player links to their public page.
            $sideLink = function ($pair) use ($tournament) {
            if (! $pair) return null;
            $parts = [];
            foreach ([$pair->player1, $pair->player2] as $p) {
            if ($p) {
            $url = route('public.player', [$tournament, $p]);
            $parts[] = '<a href="'.$url.'" class="pub-match__player">'.e($p->name).'</a>';
            }
            }
            return $parts ? implode(' / ', $parts) : e($pair->name());
            };
            $aLink = $sideLink($m->pairA);
            $bLink = $sideLink($m->pairB);
            if ($played) {
            $shareData = [
            'tournament' => $tournament->name,
            'category' => $m->category?->name,
            'context' => $m->contextLabel(),
            'pairA' => $m->pairA?->name() ?? '—',
            'pairB' => $m->pairB?->name() ?? '—',
            'sets' => $m->sets ?? [],
            'winner' => $m->winner_pair_id === $m->pair_a_id ? 'a' : ($m->winner_pair_id === $m->pair_b_id ? 'b' : null),
            ];
            }
            @endphp
            <div class="pub-match pub-match--{{ $status }}">
                <div class="pub-match__time">
                    {{ $m->starts_at->timezone('America/Mexico_City')->format('H:i') }}
                    @php $courtLabel = $tournament->publicCourtLabel($m->court); @endphp
                    @if($courtLabel)<span class="pub-match__court"><i class="fa-solid fa-location-dot"></i> {{ $courtLabel }}</span>@endif
                </div>
                <div class="pub-match__body">
                    <div class="pub-match__ctx">{{ $m->contextLabel() }}</div>
                    <div class="pub-match__pairs">
                        <span class="{{ $m->winner_pair_id === $m->pair_a_id && $m->pair_a_id ? 'is-win' : '' }}">
                            {!! $aLink ?? $m->sideLabel('a') !!}
                            @php $ghostA = $m->ghostForIn('a', $ghostQualifiers ?? []); @endphp
                            @if($ghostA)<span class="pub-match__ghost" title="Clasificado (grupo terminado)">{{ $ghostA }}</span>@endif
                        </span>
                        @if($status === 'played' && $m->sets)
                        <span class="pub-match__sc pub-mono">
                            @foreach($m->sets as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last) @endif @endforeach
                        </span>
                        @else
                        <span class="pub-match__vs">vs</span>
                        @endif
                        <span class="{{ $m->winner_pair_id === $m->pair_b_id && $m->pair_b_id ? 'is-win' : '' }}">
                            {!! $bLink ?? $m->sideLabel('b') !!}
                            @php $ghostB = $m->ghostForIn('b', $ghostQualifiers ?? []); @endphp
                            @if($ghostB)<span class="pub-match__ghost" title="Clasificado (grupo terminado)">{{ $ghostB }}</span>@endif
                        </span>
                    </div>
                </div>
                @if($played)
                <button type="button" class="pub-share-btn pub-match__share" data-share-match='@json($shareData)' title="Compartir imagen">
                    <i class="fa-solid fa-image"></i>
                </button>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
    @endif
</div>
@endsection