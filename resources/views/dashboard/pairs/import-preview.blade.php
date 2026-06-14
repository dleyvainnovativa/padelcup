@extends('layouts.app')

@section('title', 'Previsualizar parejas')

@php
$exceedsCapacity = $remaining !== null && $rows->count() > $remaining;
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>Previsualizar parejas</h1>
        <div class="page-sub">{{ $category->name }} · revisa duplicados antes de confirmar</div>
    </div>
</div>

@if(count($errors))
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
    <strong>{{ count($errors) }} filas con problemas (se omitirán):</strong>
    <ul class="mb-0 mt-1">@foreach($errors as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

@if($exceedsCapacity)
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    La categoría tiene espacio para {{ $remaining }} parejas más, pero el archivo trae {{ $rows->count() }}.
    Las que excedan el cupo se omitirán. Aumenta el cupo si quieres incluirlas todas.
</div>
@endif

@if($rows->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        No hay parejas válidas para importar. <a href="{{ route('pairs.import.form', [$tournament, $category]) }}">Volver</a>.
    </div>
</div>
@else
<form method="POST" action="{{ route('pairs.import.commit', [$tournament, $category]) }}">
    @csrf
    <div class="tc-card">
        <div class="tc-card__head">
            <h3>{{ $rows->count() }} parejas</h3>
            <button type="submit" class="btn btn-accent btn-sm">Confirmar importación</button>
        </div>
        <div class="tc-table-wrap">
            <table class="tc-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Jugador 1</th>
                        <th>Jugador 2</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                    <tr>
                        <td style="color:var(--text-faint);">{{ $i + 1 }}</td>
                        @foreach(['player1', 'player2'] as $slot)
                        <td>
                            <div style="font-weight:500;">{{ $row[$slot]['name'] }}</div>
                            <div style="font-size:11px;color:var(--text-faint);">
                                {{ $row[$slot]['email'] ?? '—' }}{{ $row[$slot]['phone'] ? ' · '.$row[$slot]['phone'] : '' }}
                            </div>
                            <input type="hidden" name="rows[{{ $i }}][{{ $slot }}][name]" value="{{ $row[$slot]['name'] }}">
                            <input type="hidden" name="rows[{{ $i }}][{{ $slot }}][email]" value="{{ $row[$slot]['email'] }}">
                            <input type="hidden" name="rows[{{ $i }}][{{ $slot }}][phone]" value="{{ $row[$slot]['phone'] }}">
                            @if(count($row[$slot]['possible_duplicates']))
                            <select name="rows[{{ $i }}][{{ $slot }}][link_player_id]" class="form-select form-select-sm mt-1" style="border-radius:var(--radius);max-width:260px;">
                                @foreach($row[$slot]['possible_duplicates'] as $dupe)
                                <option value="{{ $dupe['id'] }}" @selected($loop->first)>Vincular: {{ $dupe['name'] }}{{ $dupe['email'] ? ' ('.$dupe['email'].')' : '' }}</option>
                                @endforeach
                                <option value="">Crear nuevo</option>
                            </select>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-accent">Confirmar importación</button>
        <a href="{{ route('pairs.import.form', [$tournament, $category]) }}" class="btn btn-soft">Cancelar</a>
    </div>
</form>
@endif
@endsection