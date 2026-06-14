@php
$confirmed = $match->state->value === 'confirmed';
$ready = $match->pair_a_id && $match->pair_b_id;
[$aSets, $bSets] = $match->setsWon();
@endphp

<div class="match-row" x-data="{ open: false, special: false }">
    <div class="match-row__main">
        {{-- Pair A --}}
        <div class="match-row__pair {{ $confirmed && $match->winner_pair_id === $match->pair_a_id ? 'is-winner' : '' }}">
            {{ $match->pairA?->name() ?? '—' }}
        </div>

        {{-- Score / status --}}
        <div class="match-row__score">
            @if($confirmed)
            @if($match->result_type->value !== 'normal')
            <span style="font-size:11px;color:var(--text-faint);">{{ $match->result_type->label() }}</span>
            @endif
            <span class="font-mono">
                @foreach($match->sets ?? [] as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last), @endif @endforeach
            </span>
            @else
            <span style="font-size:11px;color:var(--text-faint);">{{ $match->state->label() }}</span>
            @endif
        </div>

        {{-- Pair B --}}
        <div class="match-row__pair {{ $confirmed && $match->winner_pair_id === $match->pair_b_id ? 'is-winner' : '' }}" style="text-align:right;">
            {{ $match->pairB?->name() ?? '—' }}
        </div>

        {{-- Action --}}
        <div class="match-row__action">
            @can('update', $category)
            @if($ready)
            <button type="button" class="btn btn-soft btn-sm" @click="open = !open">
                {{ $confirmed ? 'Editar' : 'Capturar' }}
            </button>
            @else
            <span style="font-size:11px;color:var(--text-faint);">Por definir</span>
            @endif
            @endcan
        </div>
    </div>

    {{-- Expandable entry form --}}
    @can('update', $category)
    @if($ready)
    <div x-show="open" x-cloak class="match-row__form">
        <form method="POST" action="{{ $confirmed ? route('results.edit', [$tournament, $category, $match]) : route('results.confirm', [$tournament, $category, $match]) }}">
            @csrf
            <div class="d-flex align-items-center gap-3 flex-wrap match-row__sets" x-show="!special">
                <span style="font-size:12px;color:var(--text-muted);min-width:90px;">Sets (A-B):</span>
                @for($i = 0; $i < 3; $i++)
                    <div class="d-flex align-items-center gap-1 match-row__set">
                    <span class="match-row__set-label">Set {{ $i + 1 }}</span>
                    <input type="number" name="sets[{{ $i }}][0]" min="0" max="7"
                        value="{{ $match->sets[$i][0] ?? '' }}"
                        class="form-control form-control-sm" style="width:52px;border-radius:var(--radius);text-align:center;">
                    <span style="color:var(--text-faint);">-</span>
                    <input type="number" name="sets[{{ $i }}][1]" min="0" max="7"
                        value="{{ $match->sets[$i][1] ?? '' }}"
                        class="form-control form-control-sm" style="width:52px;border-radius:var(--radius);text-align:center;">
                    @if($i === 2)<span style="font-size:10px;color:var(--text-faint);">(3er set)</span>@endif
            </div>
            @endfor
            <button type="submit" class="btn btn-accent btn-sm match-row__save">{{ $confirmed ? 'Guardar' : 'Confirmar' }}</button>
    </div>
    </form>

    {{-- Walkover / retirement / default --}}
    @unless($confirmed)
    <div class="mt-2">
        <button type="button" class="link-btn" @click="special = !special" style="font-size:12px;color:var(--text-muted);background:none;border:0;cursor:pointer;padding:0;">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> Walkover / retiro / default
        </button>
        <div x-show="special" x-cloak class="mt-2">
            <form method="POST" action="{{ route('results.special', [$tournament, $category, $match]) }}">
                @csrf
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select name="type" class="form-select form-select-sm" style="width:auto;border-radius:var(--radius);">
                        <option value="walkover">Walkover (no se presentó)</option>
                        <option value="retirement">Retiro (lesión)</option>
                        <option value="default">Default</option>
                    </select>
                    <span style="font-size:12px;color:var(--text-muted);">Gana:</span>
                    <select name="winner_pair_id" class="form-select form-select-sm" style="width:auto;border-radius:var(--radius);">
                        <option value="{{ $match->pair_a_id }}">{{ $match->pairA?->name() }}</option>
                        <option value="{{ $match->pair_b_id }}">{{ $match->pairB?->name() }}</option>
                    </select>
                    <input type="text" name="note" placeholder="Nota (opcional)" class="form-control form-control-sm" style="width:160px;border-radius:var(--radius);">
                    <button type="submit" class="btn btn-soft btn-sm">Registrar</button>
                </div>
            </form>
        </div>
    </div>
    @endunless
</div>
@endif
@endcan
</div>