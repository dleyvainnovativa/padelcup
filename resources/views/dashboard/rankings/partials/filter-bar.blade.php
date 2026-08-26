{{--
    Ranking filter bar: tournament (outer) → category (inner).
    Renders BOTH presentations; CSS shows chips on desktop, dropdowns on mobile.

    Expects:
      $system, $routeName
      $tournaments  (Collection [{id,name,players}])
      $categories   (Collection [{key,label,players}])  — already scoped to tournament
      $activeTour   ('all' | id string)
      $activeCat    ('all' | category_key)

    Same query params as before (?tournament, ?cat, ?view=summary). Selecting a
    tournament resets category to 'all' (scope changes); the selects navigate
    on change.
--}}
@php
    $tour = $activeTour ?? 'all';
    $cat  = $activeCat ?? 'all';
    $mkTour = fn($id) => route($routeName, [$system, 'tournament' => $id]);
    $mkCat  = fn($k)  => route($routeName, [$system, 'tournament' => $tour, 'cat' => $k]);
    $summaryUrl = route($routeName, [$system, 'view' => 'summary']);
    $hasTours = $tournaments->count() > 1;
@endphp

<div class="rk-filter">

    {{-- ===== CHIPS (desktop) ===== --}}
    <div class="rk-filter__chips">
        @if($hasTours)
        <div class="rk-filter__group">
            <span class="rk-filter__label">Torneo</span>
            <div class="rk-cats">
                <a href="{{ $mkTour('all') }}" class="rk-cat {{ $tour === 'all' ? 'is-active' : '' }}">Todos</a>
                @foreach($tournaments as $t)
                    <a href="{{ $mkTour($t['id']) }}" class="rk-cat {{ $tour === (string) $t['id'] ? 'is-active' : '' }}">{{ $t['name'] }}</a>
                @endforeach
            </div>
        </div>
        @endif

        @if($categories->isNotEmpty())
        <div class="rk-filter__group">
            <span class="rk-filter__label">Categoría</span>
            <div class="rk-cats">
                <a href="{{ $mkCat('all') }}" class="rk-cat {{ $cat === 'all' ? 'is-active' : '' }}">General</a>
                @foreach($categories as $c)
                    <a href="{{ $mkCat($c['key']) }}" class="rk-cat {{ $cat === $c['key'] ? 'is-active' : '' }}">
                        {{ $c['label'] }}<span class="rk-cat__count">{{ $c['players'] }}</span>
                    </a>
                @endforeach
                <a href="{{ $summaryUrl }}" class="rk-cat rk-cat--summary"><i class="fa-solid fa-layer-group"></i> Resumen</a>
            </div>
        </div>
        @endif
    </div>

    {{-- ===== DROPDOWNS (mobile) ===== --}}
    <div class="rk-filter__selects">
        @if($hasTours)
        <label class="rk-select">
            <span class="rk-select__label">Torneo</span>
            <select onchange="if(this.value)window.location.href=this.value;">
                <option value="{{ $mkTour('all') }}" {{ $tour === 'all' ? 'selected' : '' }}>Todos los torneos</option>
                @foreach($tournaments as $t)
                    <option value="{{ $mkTour($t['id']) }}" {{ $tour === (string) $t['id'] ? 'selected' : '' }}>{{ $t['name'] }}</option>
                @endforeach
            </select>
        </label>
        @endif

        @if($categories->isNotEmpty())
        <label class="rk-select">
            <span class="rk-select__label">Categoría</span>
            <select onchange="if(this.value)window.location.href=this.value;">
                <option value="{{ $mkCat('all') }}" {{ $cat === 'all' ? 'selected' : '' }}>General</option>
                @foreach($categories as $c)
                    <option value="{{ $mkCat($c['key']) }}" {{ $cat === $c['key'] ? 'selected' : '' }}>{{ $c['label'] }} ({{ $c['players'] }})</option>
                @endforeach
                <option value="{{ $summaryUrl }}">— Resumen por categorías —</option>
            </select>
        </label>
        @endif
    </div>

</div>
