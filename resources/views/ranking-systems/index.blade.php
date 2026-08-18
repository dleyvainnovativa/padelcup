@extends('layouts.app')

@section('title', 'Sistemas de ranking')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking'],
    ]" />

<div class="page-head">
    <div>
        <h1>Sistemas de ranking</h1>
        <div class="page-sub">Define rankings por asociación y sus puntos por logro.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('ranking-systems.create') }}" class="btn btn-accent">
            <i class="fa-solid fa-plus me-1"></i> Nuevo sistema
        </a>
    </div>
</div>

@include('dashboard.partials.flash')

@if($systems->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Aún no tienes sistemas de ranking. Crea uno para empezar a acumular puntos por torneo.
    </div>
</div>
@else
<div class="tc-table-wrap">
    <table class="tc-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Asociación</th>
                <th>Acumulación</th>
                <th>Torneos</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($systems as $s)
            <tr>
                <td style="font-weight:600;">
                    <a href="{{ route('ranking-systems.show', $s) }}">{{ $s->name }}</a>
                </td>
                <td>{{ $s->owner_label ?? '—' }}</td>
                <td style="font-size:12px;">
                    {{ $s->stacking === 'cumulative' ? 'Acumulativa' : 'Solo el mejor' }}
                </td>
                <td>{{ $s->tournaments_count }}</td>
                <td>
                    @if($s->is_active)
                    <span style="color:var(--success-text);font-size:12px;"><i class="fa-solid fa-circle-check me-1"></i>Activo</span>
                    @else
                    <span style="color:var(--text-faint);font-size:12px;">Inactivo</span>
                    @endif
                </td>
                <td class="text-end">
                    <div class="d-inline-flex gap-1">
                        <a href="{{ route('ranking-systems.edit', $s) }}" class="btn btn-soft btn-sm" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <form method="POST" action="{{ route('ranking-systems.duplicate', $s) }}">
                            @csrf
                            <button class="btn btn-soft btn-sm" title="Duplicar"><i class="fa-solid fa-copy"></i></button>
                        </form>
                        <form method="POST" action="{{ route('ranking-systems.destroy', $s) }}"
                            data-confirm="Se eliminará “{{ $s->name }}” y TODOS sus puntos acumulados. Esta acción no se puede deshacer."
                            data-confirm-title="Eliminar sistema" data-confirm-ok="Eliminar" data-confirm-variant="danger">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" title="Eliminar"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
