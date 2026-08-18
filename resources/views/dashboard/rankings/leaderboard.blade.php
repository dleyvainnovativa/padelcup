@extends('layouts.app')

@section('title', 'Ranking · ' . $system->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => $system->name, 'url' => route('ranking-systems.show', $system)],
        ['label' => 'Tabla'],
    ]" />

<div class="page-head">
    <div>
        <h1>Tabla de posiciones</h1>
        <div class="page-sub">
            {{ $system->name }}@if($system->owner_label) · {{ $system->owner_label }}@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('public.rankings.show', $system) }}" class="btn btn-soft" target="_blank">
            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Ver pública
        </a>
    </div>
</div>

@include('dashboard.partials.flash')

@if($board->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no hay puntos en este ranking. Finaliza un torneo vinculado para poblar la tabla.
    </div>
</div>
@else
<div class="tc-card">
    <div class="tc-card__body" style="padding:0;">
        <div class="tc-table-wrap">
            <table class="tc-table rk-board">
                <thead>
                    <tr>
                        <th style="width:56px;">#</th>
                        <th>Jugador</th>
                        <th style="width:90px;" class="text-end">Torneos</th>
                        <th style="width:110px;" class="text-end">Puntos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($board as $row)
                    <tr>
                        <td class="rk-board__rank">
                            @if($row['rank'] <= 3)
                                <span class="rk-board__medal rk-board__medal--{{ $row['rank'] }}">{{ $row['rank'] }}</span>
                            @else
                                {{ $row['rank'] }}
                            @endif
                        </td>
                        <td style="font-weight:600;">{{ $row['name'] }}</td>
                        <td class="text-end">{{ $row['tournaments'] }}</td>
                        <td class="text-end font-mono" style="font-weight:700;">{{ number_format($row['points']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<p style="font-size:11px;color:var(--text-faint);margin-top:10px;">
    Un jugador que aparece en varias categorías se cuenta una sola vez (se agrupa por nombre normalizado).
</p>
@endif
@endsection
