<a href="{{ route('public.circuits.show', $c) }}" class="pp-card">
    <div class="pp-card__cover">
        @if($c->coverImageUrl())
        <img src="{{ $c->coverImageUrl() }}" alt="{{ $c->name }}" loading="lazy">
        @else
        <span class="pp-card__cover-fallback"><i class="fa-solid fa-ranking-star"></i></span>
        @endif
    </div>
    <div class="pp-card__body">
        <div class="pp-card__name">{{ $c->name }}</div>

        <div class="pp-card__chips">
            <span class="pp-chip pp-chip--accent">
                {{ $c->tournaments_count }} {{ $c->tournaments_count === 1 ? 'torneo' : 'torneos' }}
            </span>
        </div>

        <div class="pp-card__meta">
            @if($c->owner_label)
            <span><i class="fa-solid fa-shield-halved"></i> {{ $c->owner_label }}</span>
            @endif
        </div>
    </div>
</a>
