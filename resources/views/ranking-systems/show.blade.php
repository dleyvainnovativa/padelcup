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
        <a href="{{ route('ranking-systems.edit', $system) }}" class="btn btn-accent">
            <i class="fa-solid fa-pen me-1"></i> Editar
        </a>
        {{-- Leaderboard link lands in Phase 4 --}}
    </div>
</div>

@include('dashboard.partials.flash')

<div class="tc-card">
    <div class="tc-card__head"><h3><i class="fa-solid fa-list-ol me-1"></i> Puntos por logro</h3></div>
    <div class="tc-card__body">
        <div class="tc-table-wrap">
            <table class="tc-table">
                <thead><tr><th>Logro</th><th class="text-end">Puntos</th></tr></thead>
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
@endsection
