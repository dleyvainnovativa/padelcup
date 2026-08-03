{{--
    BUNDLE 3 — Public winners summary view
    resources/views/public/winners.blade.php

    Shows the elimination winners for the SELECTED category (dropdown). Only
    finished categories render a full podium; undecided ones say "pendiente".

    LAYOUT: adjust @extends to whatever your other public pages use. Your admin
    pages use layouts.app; your public pages likely use a public layout. If your
    public views @extend('layouts.app'), change the line below to match. The
    breadcrumb/x-components are optional on public pages — remove if unused.

    Expects: $tournament, $summaries (all categories), $selected (podium), $ads
--}}
@extends('layouts.public')

@section('title', 'Campeones · ' . $tournament->name)

@section('content')
<div class="pub-winners">

    <div class="pub-winners__head">
        <div class="pub-winners__eyebrow">Resultados finales</div>
        <h1 class="pub-winners__title">Campeones</h1>
        <div class="pub-winners__sub">{{ $tournament->name }}</div>
    </div>

    {{-- Category selector (dropdown). Navigates with ?cat=ID. --}}
    <div class="pub-winners__picker">
        <label for="winnerCat" class="pub-winners__picker-label">Categoría</label>
        <div class="pub-winners__select-wrap">
            <select id="winnerCat" class="pub-winners__select"
                onchange="if(this.value)window.location.search='?cat='+this.value">
                @foreach($summaries as $s)
                <option value="{{ $s['category_id'] }}"
                    @selected($selected && $s['category_id'] === $selected['category_id'])>
                    {{ $s['category'] }}@if(!$s['decided']) — pendiente @endif
                </option>
                @endforeach
            </select>
            <i class="fa-solid fa-chevron-down pub-winners__select-caret"></i>
        </div>
    </div>

    @if(!$selected)
        <div class="pub-winners__empty">Aún no hay categorías.</div>
    @elseif(!$selected['decided'])
        {{-- Category chosen but final not played yet. --}}
        <div class="pub-winners__pending">
            <i class="fa-solid fa-hourglass-half"></i>
            <div>
                <strong>{{ $selected['category'] }}</strong>
                <span>La final de esta categoría todavía no se define. Vuelve cuando termine.</span>
            </div>
        </div>
    @else
        {{-- Podium. Champion always; runner-up when a real final was played;
             third only when the category produced one. --}}
        <div class="pub-podium">

            {{-- Champion --}}
            <div class="pub-podium__slot pub-podium__slot--first">
                <div class="pub-podium__medal">🥇</div>
                <div class="pub-podium__rank">Campeón</div>
                <div class="pub-podium__pair">{{ $selected['champion'] }}</div>
                @if($selected['final_score'])
                <div class="pub-podium__score">Final: {{ $selected['final_score'] }}</div>
                @endif
            </div>

            {{-- Runner-up (only if a real opponent existed in the final) --}}
            @if($selected['runner_up'])
            <div class="pub-podium__slot pub-podium__slot--second">
                <div class="pub-podium__medal">🥈</div>
                <div class="pub-podium__rank">Subcampeón</div>
                <div class="pub-podium__pair">{{ $selected['runner_up'] }}</div>
            </div>
            @endif

            {{-- Third (only if the category produced a 3rd place) --}}
            @if($selected['third'])
            <div class="pub-podium__slot pub-podium__slot--third">
                <div class="pub-podium__medal">🥉</div>
                <div class="pub-podium__rank">Tercer lugar</div>
                <div class="pub-podium__pair">{{ $selected['third'] }}</div>
            </div>
            @endif
        </div>

        @unless($selected['runner_up'])
        <p class="pub-winners__note">
            Esta categoría se definió con un solo clasificado, por lo que solo hay campeón.
        </p>
        @endunless
    @endif

    {{-- Optional: a compact roll-call of ALL decided categories, so the page is
         useful even before you pick from the dropdown. Remove if you prefer only
         the selected category. --}}
    @php $decidedAll = $summaries->where('decided', true); @endphp
    @if($decidedAll->count() > 1)
    <div class="pub-winners__rollcall">
        <div class="pub-winners__rollcall-title">Todos los campeones</div>
        <div class="pub-winners__rollcall-list">
            @foreach($decidedAll as $s)
            <a href="?cat={{ $s['category_id'] }}"
               class="pub-winners__rollcall-row {{ $selected && $s['category_id']===$selected['category_id'] ? 'is-active' : '' }}">
                <span class="pub-winners__rollcall-cat">{{ $s['category'] }}</span>
                <span class="pub-winners__rollcall-champ">🏆 {{ $s['champion'] }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Ads: reuse your existing public ad partial if you have one. This mirrors
         how public.tournament receives $ads. Replace with your partial include. --}}
    @if(!empty($ads) && $ads->isNotEmpty())
    <div class="pub-winners__ads">
        @foreach($ads as $ad)
            {{-- @include('public.partials.ad', ['ad' => $ad]) --}}
        @endforeach
    </div>
    @endif

</div>
@endsection
