@extends('layouts.app')

@section('title', 'Grupos y posiciones')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => $category->name, 'url' => route('categories.show', [$tournament, $category])],
        ['label' => 'Grupos'],
    ]" />
<div class="page-head">
    <div>
        <h1>Grupos · {{ $category->name }}</h1>
        <div class="page-sub">{{ $tournament->name }} · {{ $category->format->label() }}</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @unless($tournament->isLocked())
        <a href="{{ route('draw.groups.preview', [$tournament, $category]) }}"
            class="btn btn-soft"
            data-confirm="Regenerar reemplaza el acomodo manual actual de TODAS las parejas. Para añadir parejas nuevas sin perder tu acomodo, usa «Sin asignar»."
            data-confirm-title="Regenerar grupos"
            data-confirm-variant="danger"
            data-confirm-ok="Regenerar todo">
            <i class="fa-solid fa-rotate me-1"></i> Regenerar
        </a>
        @endunless
        @if($category->format->hasBracket())
        <form method="POST" action="{{ route('draw.bracket.build', [$tournament, $category]) }}">
            @csrf
            <button class="btn btn-accent"><i class="fa-solid fa-sitemap me-1"></i> Generar llave</button>
        </form>
        @endif
    </div>
</div>

@include('dashboard.partials.flash')

@if($groups->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay grupos. <a href="{{ route('draw.groups.preview', [$tournament, $category]) }}">Generar grupos</a>.
    </div>
</div>
@else
@php $canEdit = ! $tournament->isLocked(); @endphp
<div x-data="{ tab: '{{ $canEdit ? 'arrange' : 'standings' }}' }">
    {{-- Tab bar --}}
    <div class="tc-tabs mb-3">
        @if($canEdit)
        <button type="button" class="tc-tab" :class="{ 'is-active': tab === 'arrange' }" @click="tab = 'arrange'">
            <i class="fa-solid fa-arrows-up-down-left-right me-1"></i> Acomodar
        </button>
        @endif
        <button type="button" class="tc-tab" :class="{ 'is-active': tab === 'standings' }" @click="tab = 'standings'">
            <i class="fa-solid fa-ranking-star me-1"></i> Posiciones
        </button>
    </div>

    @if($canEdit)
    {{-- Acomodar (drag/tap) --}}
    <div x-show="tab === 'arrange'" x-cloak>
        @if($unassigned->isNotEmpty())
        <div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
            <i class="fa-solid fa-circle-info me-1"></i>
            Hay {{ $unassigned->count() }} {{ $unassigned->count() === 1 ? 'pareja sin asignar' : 'parejas sin asignar' }} (inscripciones recientes). Colócalas en un grupo.
        </div>
        @endif

        <div style="font-size:12px;color:var(--text-faint);margin-bottom:10px;">
            <span class="d-none d-md-inline">Arrastra una pareja a otro grupo o a «Sin asignar».</span>
            <span class="d-md-none">Toca una pareja y luego el grupo destino.</span>
        </div>

        <div class="group-board" data-group-board
            data-move-url="{{ route('draw.groups.move', [$tournament, $category]) }}">

            {{-- Unassigned pool (always a drop target; id 0 = pool) --}}
            <div class="group-col group-col--pool mb-3" data-group data-group-id="0">
                <div class="group-col__head d-flex align-items-center justify-content-between">
                    <span><i class="fa-solid fa-inbox me-1"></i> Sin asignar</span>
                    <span class="pool-count" data-pool-count>{{ $unassigned->count() }}</span>
                </div>
                <div class="group-col__list" data-group-list>
                    @foreach($unassigned as $pair)
                    <div class="pair-chip" data-pair data-pair-id="{{ $pair->id }}">
                        <i class="fa-solid fa-grip-vertical pair-chip__grip"></i>
                        {{ $pair->name() }}
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="row g-3">
                @foreach($groups as $group)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="group-col" data-group data-group-id="{{ $group->id }}">
                        <div class="group-col__head">{{ $group->name }}</div>
                        <div class="group-col__list" data-group-list>
                            @foreach($group->pairs as $pair)
                            <div class="pair-chip" data-pair data-pair-id="{{ $pair->id }}">
                                <i class="fa-solid fa-grip-vertical pair-chip__grip"></i>
                                {{ $pair->name() }}
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Posiciones (standings) --}}
    <div x-show="tab === 'standings'" @if($canEdit) x-cloak @endif>
        <div class="row g-3">
            @foreach($groups as $group)
            <div class="col-12 col-lg-6">
                <div class="tc-card h-100">
                    <div class="tc-card__head">
                        <h3>{{ $group->name }}</h3>
                    </div>
                    <div class="tc-table-wrap">
                        <table class="tc-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Pareja</th>
                                    <th>PJ</th>
                                    <th>G</th>
                                    <th>P</th>
                                    <th>Pts</th>
                                    <th>Dif. sets</th>
                                    <th>Dif. games</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($standings[$group->id] as $pos => $row)
                                @php $pair = $group->pairs->firstWhere('id', $row['pair_id']); @endphp
                                <tr>
                                    <td style="color:var(--text-faint);">{{ $pos + 1 }}</td>
                                    <td>{{ $pair?->name() ?? '—' }}</td>
                                    <td>{{ $row['played'] }}</td>
                                    <td>{{ $row['won'] }}</td>
                                    <td>{{ $row['lost'] }}</td>
                                    <td style="font-weight:700;">{{ $row['points'] }}</td>
                                    <td class="font-mono">{{ $row['set_diff'] > 0 ? '+' : '' }}{{ $row['set_diff'] }}</td>
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
</div>
@endif
@endsection