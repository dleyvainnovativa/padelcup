@extends('layouts.public')

@section('title', $category->name.' · '.$tournament->name)

@section('content')
<div class="pub-wrap" data-auto-refresh="60" x-data="{ tab: '{{ $groups->isNotEmpty() ? 'standings' : 'bracket' }}' }">
    <div class="pub-crumb">
        <a href="{{ route('public.tournament', $tournament) }}"><i class="fa-solid fa-chevron-left"></i> {{ $tournament->name }}</a>
    </div>
    <div class="pub-title-row">
        <h1>{{ $category->name }}</h1>
        <div class="pub-title-actions">
            <span class="pub-live"><span class="pub-status__dot pub-status__dot--live"></span> En vivo</span>
            <button type="button" class="pub-btn pub-btn--icon" data-share="{{ route('public.category', [$tournament, $category]) }}" data-share-title="{{ $category->name }} · {{ $tournament->name }}">
                <i class="fa-solid fa-share-nodes"></i> Compartir
            </button>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="pub-tabs">
        @if($groups->isNotEmpty())
        <button class="pub-tab" :class="{ 'is-active': tab === 'standings' }" @click="tab = 'standings'">Posiciones</button>
        @endif
        @if($category->format->hasBracket())
        <button class="pub-tab" :class="{ 'is-active': tab === 'bracket' }" @click="tab = 'bracket'">Llave</button>
        @endif
        @if($groupResults->isNotEmpty())
        <button class="pub-tab" :class="{ 'is-active': tab === 'results' }" @click="tab = 'results'">Resultados</button>
        @endif
    </div>

    {{-- STANDINGS --}}
    @if($groups->isNotEmpty())
    <div x-show="tab === 'standings'" x-cloak>
        <div x-data="{ view: 'groups' }">
            <div class="pub-subtabs">
                <button class="pub-subtab" :class="{ 'is-active': view === 'groups' }" @click="view = 'groups'">Grupos</button>
                <button class="pub-subtab" :class="{ 'is-active': view === 'general' }" @click="view = 'general'">General</button>
            </div>

            {{-- Per group --}}
            <div x-show="view === 'groups'" x-cloak class="pub-groups">
                @foreach($groups as $group)
                <div class="pub-card">
                    <div class="pub-card__head">{{ $group['name'] }}</div>
                    <table class="pub-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pareja</th>
                                <th>PJ</th>
                                <th>Pts</th>
                                <th>Dif</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group['rows'] as $pos => $row)
                            @php $rank = $pos + 1; $q = in_array($row['pair_id'], $qualifierIds); @endphp
                            <tr class="{{ $rank <= 3 ? 'pub-top'.$rank : '' }}">
                                <td>{{ $rank }}</td>
                                <td>
                                    {{ $row['pair_name'] }}
                                    @if($q)<i class="fa-solid fa-circle-up pub-q" title="Clasifica"></i>@endif
                                </td>
                                <td>{{ $row['played'] }}</td>
                                <td><strong>{{ $row['points'] }}</strong></td>
                                <td class="pub-mono">{{ $row['game_diff'] > 0 ? '+' : '' }}{{ $row['game_diff'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            </div>

            {{-- General --}}
            <div x-show="view === 'general'" x-cloak>
                <div class="pub-card">
                    <table class="pub-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pareja</th>
                                <th>Grupo</th>
                                <th>PJ</th>
                                <th>Pts</th>
                                <th>Dif</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($combined as $i => $row)
                            @php $rank = $i + 1; $q = in_array($row['pair_id'], $qualifierIds); @endphp
                            <tr class="{{ $rank <= 3 ? 'pub-top'.$rank : '' }}">
                                <td>{{ $rank }}</td>
                                <td>{{ $row['pair_name'] }} @if($q)<i class="fa-solid fa-circle-up pub-q"></i>@endif</td>
                                <td class="pub-muted">{{ $row['group_name'] }}</td>
                                <td>{{ $row['played'] }}</td>
                                <td><strong>{{ $row['points'] }}</strong></td>
                                <td class="pub-mono">{{ $row['game_diff'] > 0 ? '+' : '' }}{{ $row['game_diff'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- BRACKET --}}
    @if($category->format->hasBracket())
    <div x-show="tab === 'bracket'" x-cloak>
        @if($bracketMatches->isEmpty())
        <div class="pub-empty">La llave aún no se ha generado.</div>
        @else
        <div class="pub-bracket">
            @foreach($bracketMatches as $round => $roundMatches)
            <div class="pub-bracket__col">
                <div class="pub-bracket__round">{{ $roundMatches->first()->bracketRoundName() }}</div>
                @foreach($roundMatches as $m)
                @php $played = $m->state->value === 'confirmed'; @endphp
                <div class="pub-bmatch {{ $played ? 'is-played' : '' }}">
                    <div class="pub-bmatch__side {{ $m->winner_pair_id === $m->pair_a_id && $m->pair_a_id ? 'is-win' : '' }}">
                        <span>{{ $m->pairA?->name() ?? '—' }}</span>
                        @if($played && $m->sets)<span class="pub-bmatch__sc">{{ collect($m->sets)->map(fn($s) => $s[0])->implode(' ') }}</span>@endif
                    </div>
                    <div class="pub-bmatch__side {{ $m->winner_pair_id === $m->pair_b_id && $m->pair_b_id ? 'is-win' : '' }}">
                        <span>{{ $m->pairB?->name() ?? '—' }}</span>
                        @if($played && $m->sets)<span class="pub-bmatch__sc">{{ collect($m->sets)->map(fn($s) => $s[1])->implode(' ') }}</span>@endif
                    </div>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- RESULTS --}}
    @if($groupResults->isNotEmpty())
    <div x-show="tab === 'results'" x-cloak class="pub-results">
        @foreach($groupResults as $groupId => $matches)
        <div class="pub-card">
            <div class="pub-card__head">{{ $matches->first()->group->name }}</div>
            <div class="pub-card__body">
                @foreach($matches as $m)
                @php $played = $m->state->value === 'confirmed'; @endphp
                <div class="pub-result-wrap">
                    <div class="pub-result-row">
                        <span class="pub-result-row__a {{ $played && $m->winner_pair_id === $m->pair_a_id ? 'is-win' : '' }}">{{ $m->pairA?->name() ?? '—' }}</span>
                        <span class="pub-result-row__sc pub-mono">
                            @if($played)@foreach($m->sets ?? [] as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last) @endif @endforeach
                            @else <span class="pub-muted">—</span>@endif
                        </span>
                        <span class="pub-result-row__b {{ $played && $m->winner_pair_id === $m->pair_b_id ? 'is-win' : '' }}">{{ $m->pairB?->name() ?? '—' }}</span>
                        @if($played)
                        @php
                        $shareData = [
                        'tournament' => $tournament->name,
                        'category' => $category->name,
                        'context' => $m->contextLabel(),
                        'pairA' => $m->pairA?->name() ?? '—',
                        'pairB' => $m->pairB?->name() ?? '—',
                        'sets' => $m->sets ?? [],
                        'winner' => $m->winner_pair_id === $m->pair_a_id ? 'a' : ($m->winner_pair_id === $m->pair_b_id ? 'b' : null),
                        ];
                        @endphp
                        <button type="button" class="pub-share-btn" data-share-match='@json($shareData)' title="Compartir imagen">
                            <i class="fa-solid fa-image"></i>
                        </button>
                        @endif
                    </div>
                    @if($m->starts_at || $m->court)
                    <div class="pub-result-meta">
                        @if($m->court)<span><i class="fa-solid fa-location-dot"></i> {{ $m->court->name }}</span>@endif
                        @if($m->starts_at)<span><i class="fa-regular fa-clock"></i> {{ $m->starts_at->timezone('America/Mexico_City')->translatedFormat('D d M · H:i') }}</span>@endif
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection