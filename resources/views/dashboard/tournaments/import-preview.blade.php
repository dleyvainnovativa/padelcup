@extends('layouts.app')

@section('title', 'Previsualizar importación · '.$tournament->name)

@section('content')
<div class="page-head">
    <div>
        <h1>Previsualizar importación</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <a href="{{ route('tournaments.import.form', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-arrow-left me-1"></i> Cambiar archivo</a>
</div>

@if(!empty($errors) && count($errors))
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:color-mix(in srgb, var(--warning,#f5a623) 12%, var(--surface));color:var(--text);border:1px solid color-mix(in srgb, var(--warning,#f5a623) 35%, transparent);">
    <strong>{{ count($errors) }} filas con problemas (se omitirán):</strong>
    @foreach(array_slice($errors, 0, 8) as $e)<div>{{ $e }}</div>@endforeach
    @if(count($errors) > 8)<div>… y {{ count($errors) - 8 }} más.</div>@endif
</div>
@endif

@php
$totalPairs = collect($preview)->sum('pairs');
$newCats = collect($preview)->where('exists', false)->count();
$existingCats = collect($preview)->where('exists', true)->count();
@endphp

<div class="row g-3 mb-2">
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-people-group" label="Parejas a importar" value="{{ $totalPairs }}" accent /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-layer-group" label="Categorías" value="{{ count($preview) }}" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-plus" label="Nuevas" value="{{ $newCats }}" /></div>
    <div class="col-6 col-lg-3"><x-stat-tile icon="fa-check" label="Existentes" value="{{ $existingCats }}" /></div>
</div>

<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3>Resumen por categoría</h3>
    </div>
    <table class="tc-table">
        <thead>
            <tr>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Parejas</th>
                <th>Jugadores</th>
            </tr>
        </thead>
        <tbody>
            @foreach($preview as $row)
            <tr>
                <td style="font-weight:600;">{{ $row['category'] }}</td>
                <td>
                    @if($row['exists'])
                    <x-pill variant="neutral" dot>Existente</x-pill>
                    @else
                    <x-pill variant="accent" dot>Nueva</x-pill>
                    @endif
                </td>
                <td>{{ $row['pairs'] }}</td>
                <td>{{ $row['players'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($newCats > 0)
<p style="font-size:12px;color:var(--text-faint);margin-bottom:14px;">
    Las {{ $newCats }} categorías nuevas se crearán con formato <strong>Grupos + Eliminación</strong>, grupos de 3, 1 clasifican. Podrás ajustar cada una después de importar.
</p>
@endif

<form method="POST" action="{{ route('tournaments.import.commit', $tournament) }}">
    @csrf
    <button type="submit" class="btn btn-accent"><i class="fa-solid fa-file-import me-1"></i> Confirmar e importar</button>
    <a href="{{ route('tournaments.import.form', $tournament) }}" class="btn btn-soft">Cancelar</a>
</form>
@endsection