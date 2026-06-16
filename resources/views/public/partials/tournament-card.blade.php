<a href="{{ route('public.tournament', $t) }}" class="pub-cat-card">
    <div class="pub-cat-card__name">{{ $t->name }}</div>
    <div class="pub-cat-card__meta">
        @if($t->starts_on)
            <span><i class="fa-regular fa-calendar"></i>
                {{ $t->starts_on->translatedFormat('d M Y') }}@if($t->ends_on && !$t->ends_on->equalTo($t->starts_on)) – {{ $t->ends_on->translatedFormat('d M Y') }}@endif
            </span>
        @endif
        <span class="pub-cat-card__fmt">{{ $t->categories_count }} {{ $t->categories_count === 1 ? 'categoría' : 'categorías' }}</span>
    </div>
    @if($live)<span class="pub-card-badge"><span class="pub-status__dot pub-status__dot--live"></span> En vivo</span>@endif
    <span class="pub-cat-card__go"><i class="fa-solid fa-arrow-right"></i></span>
</a>
