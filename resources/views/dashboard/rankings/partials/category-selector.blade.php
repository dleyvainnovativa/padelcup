{{--
    Category selector (inner filter). Preserves the active tournament scope so
    tournament → category hierarchy holds.

    Expects: $system, $categories, $activeCat, $activeTour, $routeName
--}}
@php
    $tour = $activeTour ?? 'all';
    // Preserve tournament on every category link.
    $mk = fn($params) => route($routeName, array_merge(
        [$system, 'tournament' => $tour], $params
    ));
@endphp

<div class="rk-cats">
    <a href="{{ $mk(['cat' => 'all']) }}"
       class="rk-cat {{ ($activeCat ?? 'all') === 'all' ? 'is-active' : '' }}">General</a>

    @foreach($categories as $c)
        <a href="{{ $mk(['cat' => $c['key']]) }}"
           class="rk-cat {{ ($activeCat ?? '') === $c['key'] ? 'is-active' : '' }}">
            {{ $c['label'] }}
            <span class="rk-cat__count">{{ $c['players'] }}</span>
        </a>
    @endforeach

    {{-- Summary is cross-tournament; it drops the tournament scope by design. --}}
    <a href="{{ route($routeName, [$system, 'view' => 'summary']) }}" class="rk-cat rk-cat--summary">
        <i class="fa-solid fa-layer-group"></i> Resumen
    </a>
</div>
