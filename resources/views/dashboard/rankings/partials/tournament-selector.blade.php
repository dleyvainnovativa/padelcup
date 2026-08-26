{{--
    Tournament selector (outer filter). Chips: Todos + one per tournament.
    Selecting a tournament resets the category to 'all' (scope changes).

    Expects:
      $system, $tournaments (Collection [{id,name,players}]),
      $activeTour ('all'|id string), $routeName
--}}
@if($tournaments->count() > 1)
@php $mk = fn($params) => route($routeName, array_merge([$system], $params)); @endphp
<div class="rk-tours">
    <span class="rk-tours__label">Torneo</span>
    <a href="{{ $mk(['tournament' => 'all']) }}"
       class="rk-cat {{ ($activeTour ?? 'all') === 'all' ? 'is-active' : '' }}">Todos</a>
    @foreach($tournaments as $t)
        <a href="{{ $mk(['tournament' => $t['id']]) }}"
           class="rk-cat {{ ($activeTour ?? 'all') === (string) $t['id'] ? 'is-active' : '' }}">
            {{ $t['name'] }}
        </a>
    @endforeach
</div>
@endif
