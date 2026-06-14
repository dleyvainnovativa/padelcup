@extends('layouts.app')

@section('title', 'Resolver empate')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => $category->name, 'url' => route('categories.show', [$tournament, $category])],
        ['label' => 'Llave', 'url' => route('draw.bracket', [$tournament, $category])],
        ['label' => 'Resolver empate'],
    ]" />
<div class="page-head">
    <div>
        <h1>Resolver empate</h1>
        <div class="page-sub">{{ $category->name }} · clasificados adicionales</div>
    </div>
</div>

<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
    <i class="fa-solid fa-circle-info me-1"></i>
    Hay un empate que no se puede resolver automáticamente. Elige
    {{ $tie['slots'] }} {{ $tie['slots'] === 1 ? 'pareja' : 'parejas' }}
    para completar la llave.
</div>

<form method="POST" action="{{ route('draw.bracket.build', [$tournament, $category]) }}">
    @csrf
    <div class="tc-card">
        <div class="tc-table-wrap">
            <table class="tc-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>Pareja</th>
                        <th>Pts</th>
                        <th>Dif. sets</th>
                        <th>Dif. games</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tie['rows'] as $row)
                    @php $pair = \App\Models\Pair::with('player1','player2')->find($row['pair_id']); @endphp
                    <tr>
                        <td>
                            <input type="checkbox" name="resolved[]" value="{{ $row['pair_id'] }}" class="form-check-input">
                        </td>
                        <td>{{ $pair?->name() ?? '—' }}</td>
                        <td style="font-weight:700;">{{ $row['points'] }}</td>
                        <td class="font-mono">{{ $row['set_diff'] }}</td>
                        <td class="font-mono">{{ $row['game_diff'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-accent">Confirmar y generar llave</button>
        <a href="{{ route('draw.groups', [$tournament, $category]) }}" class="btn btn-soft">Cancelar</a>
    </div>
    <div style="font-size:12px;color:var(--text-faint);margin-top:8px;">
        Selecciona exactamente {{ $tie['slots'] }}. La decisión queda registrada.
    </div>
</form>
@endsection