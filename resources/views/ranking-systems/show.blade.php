@extends('layouts.app')

@section('title', $system->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => $system->name],
    ]" />

<div class="page-head">
    <div>
        <h1>{{ $system->name }}</h1>
        <div class="page-sub">
            @if($system->owner_label){{ $system->owner_label }} · @endif
            {{ $system->stacking === 'cumulative' ? 'Acumulativa' : 'Solo el mejor logro' }}
            · {{ $system->tournaments_count }} torneo(s)
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('ranking-systems.leaderboard', $system) }}" class="btn btn-accent">
            <i class="fa-solid fa-ranking-star me-1"></i> Ver tabla
        </a>
        <a href="{{ route('ranking-systems.edit', $system) }}" class="btn btn-soft">
            <i class="fa-solid fa-pen me-1"></i> Editar
        </a>
    </div>
</div>

@include('dashboard.partials.flash')

<div class="tc-card">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-list-ol me-1"></i> Puntos por logro</h3>
    </div>
    <div class="tc-card__body">
        <div class="tc-table-wrap">
            <table class="tc-table">
                <thead>
                    <tr>
                        <th>Logro</th>
                        <th class="text-end">Puntos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($achievements as $a)
                    <tr>
                        <td>{{ $a->label() }}</td>
                        <td class="text-end font-mono" style="font-weight:600;">{{ $schedule[$a->value] ?? 0 }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p style="font-size:11px;color:var(--text-faint);margin:10px 0 0;">
            Los puntos se otorgan a cada jugador de la pareja. Las rondas de eliminación solo cuentan si la categoría realmente las tuvo.
        </p>
    </div>
</div>

{{-- Per-tournament configuration: which categories count, and an optional
     alternate point table for special occasions (same leaderboard/history). --}}
<div class="tc-card mt-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-sliders me-1"></i> Configuración por torneo</h3>
    </div>
    <div class="tc-card__body">
        @if($linkedTournaments->isEmpty())
        <p class="text-muted mb-0">Aún no hay torneos vinculados a este ranking. Vincula un torneo desde su pantalla de edición.</p>
        @else
        <p class="rk-cfg__intro">
            Elige qué categorías de cada torneo cuentan para el ranking y, si hace falta,
            usa una tabla de puntos especial para un torneo (ej. una fecha especial).
            Todo suma al mismo ranking histórico. Tras cambiar algo, vuelve a finalizar ese torneo para aplicarlo.
        </p>

        @foreach($linkedTournaments as $t)
        <form method="POST" action="{{ route('ranking-systems.tournament-config', [$system, $t['slug']]) }}"
            class="rk-cfg" x-data="{ custom: {{ $t['custom_points'] ? 'true' : 'false' }} }">
            @csrf
            <div class="rk-cfg__head">
                <div class="rk-cfg__title">
                    {{ $t['name'] }}
                    @if($t['finalized'])<span class="win-badge win-badge--ok">Finalizado</span>@endif
                </div>
                <button type="submit" class="btn btn-soft btn-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar</button>
            </div>

            {{-- #4 category toggles --}}
            <div class="rk-cfg__label">Categorías que cuentan</div>
            @if($t['categories']->isEmpty())
            <div class="text-muted" style="font-size:13px;">Este torneo no tiene categorías.</div>
            @else
            <div class="rk-cfg__cats">
                @foreach($t['categories'] as $c)
                <label class="rk-cfg__cat">
                    <input type="checkbox" name="categories[]" value="{{ $c['id'] }}" @checked($c['included'])>
                    <span>{{ $c['name'] }}</span>
                </label>
                @endforeach
            </div>
            @endif

            {{-- #5 alternate points --}}
            <label class="rk-cfg__toggle">
                <input type="checkbox" name="custom_points" value="1" x-model="custom">
                <span>Usar una tabla de puntos especial para este torneo</span>
            </label>

            <div class="rk-cfg__points" x-show="custom" x-cloak>
                <div class="tc-table-wrap">
                    <table class="tc-table rk-cfg__points-table">
                        <thead>
                            <tr>
                                <th>Logro</th>
                                <th class="text-end">Puntos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($achievements as $a)
                            <tr>
                                <td>{{ $a->label() }}</td>
                                <td class="text-end">
                                    <input type="number" min="0" class="rk-cfg__pts-input"
                                        name="points[{{ $a->value }}]"
                                        value="{{ $t['custom_points'] ? ($t['points'][$a->value] ?? 0) : ($schedule[$a->value] ?? 0) }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="rk-cfg__hint">Estos puntos se usan solo para este torneo; el resto siguen con la tabla general. Suman al mismo ranking.</p>
            </div>
        </form>
        @endforeach
        @endif
    </div>
</div>
@endsection