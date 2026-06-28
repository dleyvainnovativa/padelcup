<a href="{{ route('public.tournament', $t) }}" class="pp-card">
    <div class="pp-card__cover">
        @if($t->coverImageUrl())
        <img src="{{ $t->coverImageUrl() }}" alt="{{ $t->name }}" loading="lazy">
        @else
        <span class="pp-card__cover-fallback"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
        @endif
        @if($live)
        <span class="pp-card__live"><span class="pp-card__live-dot"></span> En vivo</span>
        @endif
    </div>
    <div class="pp-card__body">
        <div class="pp-card__name">{{ $t->name }}</div>

        <div class="pp-card__chips">
            <span class="pp-chip pp-chip--accent">
                {{ $t->categories_count }} {{ $t->categories_count === 1 ? 'categoría' : 'categorías' }}
            </span>
            @isset($t->pairs_count)
            <span class="pp-chip">{{ $t->pairs_count }} parejas</span>
            @endisset
        </div>

        <div class="pp-card__meta">
            @if($t->starts_on)
            <span>
                <i class="fa-regular fa-calendar"></i>
                {{ $t->starts_on->translatedFormat('d M Y') }}@if($t->ends_on && !$t->ends_on->equalTo($t->starts_on)) – {{ $t->ends_on->translatedFormat('d M Y') }}@endif
            </span>
            @endif
            @if(!empty($t->location))
            <span><i class="fa-solid fa-location-dot"></i> {{ $t->location }}</span>
            @endif
        </div>
    </div>
</a>