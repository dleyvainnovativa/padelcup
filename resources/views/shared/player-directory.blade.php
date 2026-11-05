{{--
    Shared player directory list.
    Expects:
      $rows        Collection of ['player'=>['id','name'],'partner'=>?string,
                    'is_singles'=>bool,'category'=>['id','name'],'search'=>string]
      $categories  Collection of {id,name}
      $tournament  Tournament (for profile links)
      $linkPlayers bool  — link each row to public.player profile
      $rowClass    (optional) extra class for styling contexts (pub vs dash)

    Client-side filtering via Alpine: global search (player OR partner) + a
    single active category chip. Both compose (AND).
--}}
@php $rowClass = $rowClass ?? ''; $raffle = $raffle ?? false; @endphp
<div class="pl-dir" x-data="{
        q: '',
        cat: '',
        rows: @js($rows->map(fn($r) => ['s' => $r['search'], 'c' => (string) $r['category']['id'], 'k' => \App\Models\Player::normalize($r['player']['name']), 'n' => $r['player']['name']])->values()),
        matches(r) {
            const needle = this.q.trim().toLowerCase();
            return (!needle || r.s.includes(needle)) && (!this.cat || this.cat === r.c);
        },
        get anyVisible() { return this.rows.some(r => this.matches(r)); },
        @if($raffle)
        noRepeat: true,
        winner: null,
        winnerKey: null,
        drawn: [],
        exhausted: false,
        pool() {
            // Currently-visible rows (respects the search + category filter).
            let pool = this.rows.filter(r => this.matches(r));
            if (this.noRepeat) {
                // One entry per PERSON (normalized name) + drop already-drawn,
                // so multi-category players aren't weighted and nobody repeats.
                const seen = {};
                pool = pool.filter(r => {
                    if (this.drawn.includes(r.k) || seen[r.k]) return false;
                    seen[r.k] = true;
                    return true;
                });
            }
            return pool;
        },
        get remaining() { return this.pool().length; },
        draw() {
            const pool = this.pool();
            if (!pool.length) { this.exhausted = true; this.winner = null; this.winnerKey = null; return; }
            this.exhausted = false;
            const pick = pool[Math.floor(Math.random() * pool.length)];
            this.winner = pick.n;
            this.winnerKey = pick.k;
            if (this.noRepeat) this.drawn.push(pick.k);
            this.$nextTick(() => {
                this.$root.querySelectorAll('.pl-dir__row--winner').forEach(el => el.classList.remove('pl-dir__row--winner'));
                const hits = this.$root.querySelectorAll('[data-pl-key=\'' + (window.CSS && CSS.escape ? CSS.escape(pick.k) : pick.k) + '\']');
                hits.forEach(el => el.classList.add('pl-dir__row--winner'));
                if (hits[0]) hits[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },
        resetDraw() {
            this.drawn = []; this.winner = null; this.winnerKey = null; this.exhausted = false;
            this.$nextTick(() => this.$root.querySelectorAll('.pl-dir__row--winner').forEach(el => el.classList.remove('pl-dir__row--winner')));
        },
        @endif
     }">

    {{-- Global search --}}
    <div class="pl-dir__search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" x-model="q" placeholder="Buscar jugador o compañero…" autocomplete="off">
        <button type="button" class="pl-dir__clear" x-show="q" @click="q=''" title="Limpiar"><i class="fa-solid fa-xmark"></i></button>
    </div>

    {{-- Category chips --}}
    @if($categories->count() > 1)
    <div class="pl-dir__cats">
        <button type="button" class="pl-dir__cat" :class="{ 'is-active': cat === '' }" @click="cat=''">Todas</button>
        @foreach($categories as $c)
        <button type="button" class="pl-dir__cat"
            :class="{ 'is-active': cat === @js((string) $c->id) }"
            @click="cat = @js((string) $c->id)">{{ $c->name }}</button>
        @endforeach
    </div>
    @endif

    @if($raffle)
    {{-- Sorteo: pick a random player from the currently-visible rows --}}
    <div class="pl-raffle">
        <div class="pl-raffle__controls">
            <button type="button" class="btn btn-accent" @click="draw()" :disabled="remaining === 0 && noRepeat">
                <i class="fa-solid fa-dice me-1"></i> Sortear jugador
            </button>
            <label class="pl-raffle__opt">
                <input type="checkbox" x-model="noRepeat" @change="resetDraw()">
                <span>Sin repetir</span>
            </label>
            <span class="pl-raffle__count" x-show="noRepeat" x-cloak>
                <span x-text="remaining"></span> disponibles<template x-if="drawn.length"> · <span x-text="drawn.length"></span> sorteados</template>
            </span>
            <button type="button" class="btn btn-soft btn-sm" x-show="noRepeat && drawn.length" x-cloak @click="resetDraw()">
                <i class="fa-solid fa-rotate-left me-1"></i> Reiniciar
            </button>
        </div>
        <div class="pl-raffle__result" x-show="winner" x-cloak>
            <i class="fa-solid fa-trophy"></i> <span class="pl-raffle__winner" x-text="winner"></span>
        </div>
        <div class="pl-raffle__result pl-raffle__result--empty" x-show="exhausted" x-cloak>
            Ya se sortearon todos los jugadores visibles. Pulsa «Reiniciar» para empezar de nuevo.
        </div>
    </div>
    @endif

    {{-- Rows --}}
    <div class="pl-dir__list">
        @forelse($rows as $row)
        <div class="pl-dir__row {{ $rowClass }}"
            data-pl-search="{{ $row['search'] }}"
            data-pl-cat="{{ $row['category']['id'] }}"
            @if($raffle) data-pl-key="{{ \App\Models\Player::normalize($row['player']['name']) }}" @endif
            x-show="(!q || @js($row['search']).includes(q.trim().toLowerCase()))
                     && (!cat || cat === @js((string) $row['category']['id']))"
            x-cloak>
            @if($linkPlayers)
            <a href="{{ route('public.player', [$tournament, $row['player']['id']]) }}" class="pl-dir__main">
            @else
            <div class="pl-dir__main">
            @endif
                <span class="pl-dir__name">{{ $row['player']['name'] }}</span>
                <span class="pl-dir__sub">
                    @if($row['is_singles'])
                        <i class="fa-solid fa-user"></i> Individual
                    @elseif($row['partner'])
                        <i class="fa-solid fa-user-group"></i> con {{ $row['partner'] }}
                    @else
                        <i class="fa-solid fa-user-group"></i> <span class="pl-dir__muted">sin compañero</span>
                    @endif
                </span>
            @if($linkPlayers)
            </a>
            @else
            </div>
            @endif
            <span class="pl-dir__cat-tag">{{ $row['category']['name'] }}</span>
        </div>
        @empty
        <div class="pl-dir__empty">Aún no hay jugadores inscritos.</div>
        @endforelse

        {{-- No-match state (rows exist but all hidden by filters) --}}
        @if($rows->isNotEmpty())
        <div class="pl-dir__empty" x-show="!anyVisible" x-cloak>Sin coincidencias.</div>
        @endif
    </div>
</div>
