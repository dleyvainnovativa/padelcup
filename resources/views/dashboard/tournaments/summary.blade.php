@extends('layouts.app')

@section('title', 'Resumen')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Resumen'],
    ]" />

<div class="page-head">
    <div>
        <h1>Resumen</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-calendar-days me-1"></i> Calendario</a>
        <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-trophy me-1"></i> Ver torneo</a>
    </div>
</div>

@if($categories->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Este torneo aún no tiene categorías.
    </div>
</div>
@else
<div x-data="{ cat: '{{ $categories->first()['id'] }}' }">
    {{-- Category selector --}}
    <div class="tc-card mb-3">
        <div class="tc-card__body d-flex align-items-center gap-2 flex-wrap">
            <label style="font-size:13px;font-weight:500;color:var(--text-muted);">Categoría</label>
            <select x-model="cat" class="form-select" style="width:auto;border-radius:var(--radius);min-width:200px;">
                @foreach($categories as $c)
                <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- One panel per category, toggled by the dropdown --}}
    @foreach($categories as $c)
    <div x-show="cat === '{{ $c['id'] }}'" x-cloak>
        {{-- Nav buttons --}}
        <div class="d-flex gap-2 flex-wrap mb-3">
            @if($c['hasGroups'])
            <a href="{{ route('draw.groups', [$tournament, $c['id']]) }}" class="btn btn-soft"><i class="fa-solid fa-layer-group me-1"></i> Grupos</a>
            @endif
            @if($c['hasBracket'])
            <a href="{{ route('draw.bracket', [$tournament, $c['id']]) }}" class="btn btn-soft"><i class="fa-solid fa-sitemap me-1"></i> Llave</a>
            @endif
            <a href="{{ route('results.index', [$tournament, $c['id']]) }}" class="btn btn-soft"><i class="fa-solid fa-flag-checkered me-1"></i> Resultados</a>
        </div>

        @if($c['groups']->isEmpty())
        <div class="tc-card">
            <div class="tc-card__body" style="color:var(--text-muted);">
                Aún no hay grupos generados para esta categoría.
            </div>
        </div>
        @else
        <div x-data="{ view: 'groups' }">
            {{-- Inner tabs --}}
            <div class="tc-tabs mb-3">
                <button type="button" class="tc-tab" :class="{ 'is-active': view === 'groups' }" @click="view = 'groups'">
                    <i class="fa-solid fa-layer-group me-1"></i> Grupos
                </button>
                <button type="button" class="tc-tab" :class="{ 'is-active': view === 'overall' }" @click="view = 'overall'">
                    <i class="fa-solid fa-ranking-star me-1"></i> General
                </button>
            </div>

            {{-- GRUPOS view --}}
            <div x-show="view === 'groups'" x-cloak>
                <div class="row g-3">
                    @foreach($c['groups'] as $group)
                    <div class="col-12 col-lg-6">
                        <div class="tc-card h-100">
                            <div class="tc-card__head">
                                <h3>{{ $group['name'] }}</h3>
                                @if($c['hasBracket'] && $c['advancePerGroup'])
                                <span style="font-size:11px;color:var(--text-faint);">
                                    <i class="fa-solid fa-circle-up me-1"></i>Avanzan {{ $c['advancePerGroup'] }}
                                </span>
                                @endif
                            </div>
                            <div class="tc-table-wrap">
                                <table class="tc-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Pareja</th>
                                            <th>PJ</th>
                                            <th>G</th>
                                            <th>Pts</th>
                                            <th>Dif</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($group['rows'] as $pos => $row)
                                        @php
                                        $rank = $pos + 1;
                                        $qualifies = in_array($row['pair_id'], $c['qualifierIds'])
                                        || ($c['hasBracket'] && $c['advancePerGroup'] && $rank <= $c['advancePerGroup']);
                                            @endphp
                                            <tr class="{{ $rank <= 3 ? 'lb-top'.$rank : '' }} {{ $qualifies ? 'lb-qualifies' : '' }}">
                                            <td>
                                                @if($rank === 1)<i class="fa-solid fa-trophy" style="color:#d4af37;"></i>
                                                @elseif($rank === 2)<i class="fa-solid fa-medal" style="color:#9ca3af;"></i>
                                                @elseif($rank === 3)<i class="fa-solid fa-medal" style="color:#b45309;"></i>
                                                @else <span style="color:var(--text-faint);">{{ $rank }}</span>
                                                @endif
                                            </td>
                                            <td style="font-weight:{{ $rank <= 3 ? '700' : '500' }};">
                                                {{ $row['pair_name'] }}
                                                @if($qualifies)
                                                <i class="fa-solid fa-circle-up" style="color:var(--success-text);font-size:10px;margin-left:4px;" title="Clasifica"></i>
                                                @endif
                                            </td>
                                            <td>{{ $row['played'] }}</td>
                                            <td>{{ $row['won'] }}</td>
                                            <td style="font-weight:700;">{{ $row['points'] }}</td>
                                            <td class="font-mono">{{ $row['game_diff'] > 0 ? '+' : '' }}{{ $row['game_diff'] }}</td>
                                            </tr>
                                            @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- GENERAL (combined) view --}}
            <div x-show="view === 'overall'" x-cloak>
                @if($c['podium'] && $c['podium']['first'])
                {{-- Real podium from the bracket --}}
                <div class="lb-podium mb-3">
                    <div class="lb-podium__card lb-podium__card--1">
                        <div class="lb-podium__medal"><i class="fa-solid fa-trophy" style="color:#d4af37;"></i> Campeón</div>
                        <div class="lb-podium__name">{{ $c['podium']['first'] }}</div>
                    </div>
                    <div class="lb-podium__card lb-podium__card--2">
                        <div class="lb-podium__medal"><i class="fa-solid fa-medal" style="color:#9ca3af;"></i> Subcampeón</div>
                        <div class="lb-podium__name">{{ $c['podium']['second'] }}</div>
                    </div>
                    <div class="lb-podium__card lb-podium__card--3">
                        <div class="lb-podium__medal"><i class="fa-solid fa-medal" style="color:#b45309;"></i> Tercer lugar</div>
                        <div class="lb-podium__name">
                            @forelse($c['podium']['third'] as $t){{ $t }}@if(!$loop->last)<br>@endif @empty—@endforelse
                        </div>
                    </div>
                </div>
                @endif

                <div class="tc-card">
                    <div class="tc-card__head">
                        <h3>Clasificación general</h3>
                    </div>
                    <div class="tc-table-wrap">
                        <table class="tc-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Pareja</th>
                                    <th>Grupo</th>
                                    <th>PJ</th>
                                    <th>G</th>
                                    <th>Pts</th>
                                    <th>Dif</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $hasPodium = $c['podium'] && $c['podium']['first']; @endphp
                                @foreach($c['combined'] as $i => $row)
                                @php
                                $rank = $i + 1;
                                $qualifies = in_array($row['pair_id'], $c['qualifierIds'])
                                || ($c['hasBracket'] && $c['advancePerGroup'] && $row['group_pos'] <= $c['advancePerGroup']);
                                    // Only highlight top-3 in the table when there's NO bracket podium.
                                    $topClass=(! $hasPodium && $rank <=3) ? 'lb-top' .$rank : '' ;
                                    @endphp
                                    <tr class="{{ $topClass }} {{ $qualifies ? 'lb-qualifies' : '' }}">
                                    <td>
                                        @if(! $hasPodium && $rank === 1)<i class="fa-solid fa-trophy" style="color:#d4af37;"></i>
                                        @elseif(! $hasPodium && $rank === 2)<i class="fa-solid fa-medal" style="color:#9ca3af;"></i>
                                        @elseif(! $hasPodium && $rank === 3)<i class="fa-solid fa-medal" style="color:#b45309;"></i>
                                        @else <span style="color:var(--text-faint);">{{ $rank }}</span>
                                        @endif
                                    </td>
                                    <td style="font-weight:{{ (! $hasPodium && $rank <= 3) ? '700' : '500' }};">
                                        {{ $row['pair_name'] }}
                                        @if($qualifies)
                                        <i class="fa-solid fa-circle-up" style="color:var(--success-text);font-size:10px;margin-left:4px;" title="Clasifica"></i>
                                        @endif
                                    </td>
                                    <td style="color:var(--text-muted);font-size:12px;">{{ $row['group_name'] }}</td>
                                    <td>{{ $row['played'] }}</td>
                                    <td>{{ $row['won'] }}</td>
                                    <td style="font-weight:700;">{{ $row['points'] }}</td>
                                    <td class="font-mono">{{ $row['game_diff'] > 0 ? '+' : '' }}{{ $row['game_diff'] }}</td>
                                    </tr>
                                    @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif
@endsection