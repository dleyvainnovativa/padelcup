{{-- One match card in the category calendar. $m = GameMatch, $showTime = bool.
     Player names link to their public page; played matches get a share button. --}}
@php
    $status = $m->scheduleStatus();
    $played = $m->state->value === 'confirmed';

    // Build linked side labels: each real player links to their public page.
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
            'category' => $category->name,
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
        @if($showTime && $m->starts_at)
            {{ $m->starts_at->timezone('America/Mexico_City')->format('H:i') }}
        @else
            <span class="pub-muted">—</span>
        @endif
        @if($m->court)<span class="pub-match__court"><i class="fa-solid fa-location-dot"></i> {{ $m->court->name }}</span>@endif
    </div>
    <div class="pub-match__body">
        <div class="pub-match__ctx">{{ $m->contextLabel() }}</div>
        <div class="pub-match__pairs">
            <span class="{{ $m->winner_pair_id === $m->pair_a_id && $m->pair_a_id ? 'is-win' : '' }}">
                {!! $aLink ?? $m->sideLabel('a') !!}
                @php $ghostA = $m->ghostFor('a', $ghostQualifiers ?? []); @endphp
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
                @php $ghostB = $m->ghostFor('b', $ghostQualifiers ?? []); @endphp
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
