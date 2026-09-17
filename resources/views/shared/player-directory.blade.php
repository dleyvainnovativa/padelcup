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
@php $rowClass = $rowClass ?? ''; @endphp
<div class="pl-dir" x-data="{
        q: '',
        cat: '',
        rows: @js($rows->map(fn($r) => ['s' => $r['search'], 'c' => (string) $r['category']['id']])->values()),
        matches(r) {
            const needle = this.q.trim().toLowerCase();
            return (!needle || r.s.includes(needle)) && (!this.cat || this.cat === r.c);
        },
        get anyVisible() { return this.rows.some(r => this.matches(r)); }
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

    {{-- Rows --}}
    <div class="pl-dir__list">
        @forelse($rows as $row)
        <div class="pl-dir__row {{ $rowClass }}"
            data-pl-search="{{ $row['search'] }}"
            data-pl-cat="{{ $row['category']['id'] }}"
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
