@extends('layouts.app')

@section('title', 'Previsualizar grupos')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => $category->name, 'url' => route('categories.show', [$tournament, $category])],
        ['label' => 'Previsualizar grupos'],
    ]" />
<div class="page-head">
    <div>
        <h1>Previsualizar grupos</h1>
        <div class="page-sub">{{ $category->name }} · {{ $pairs->count() }} parejas · {{ $preview['match_count'] }} partidos</div>
    </div>
</div>

@include('dashboard.partials.flash')

@if(!empty($preview['warnings']))
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--warning-soft);color:var(--warning-text);">
    @foreach($preview['warnings'] as $w)<div>{{ $w }}</div>@endforeach
</div>
@endif

<div style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
    Distribución propuesta: {{ implode(' / ', $preview['sizes']) }} parejas por grupo.
    Las parejas que comparten un jugador se separan automáticamente cuando es posible.
</div>

<div class="row g-3">
    @foreach($preview['groups'] as $i => $groupPairs)
    @if(count($groupPairs))
    <div class="col-12 col-md-6 col-lg-4">
        <div class="tc-card h-100">
            <div class="tc-card__head">
                <h3>Grupo {{ chr(65 + $i) }}</h3>
            </div>
            <div class="tc-card__body">
                <ol style="margin:0;padding-left:18px;font-size:13px;">
                    @foreach($groupPairs as $pair)
                    <li style="margin-bottom:4px;">{{ $pair->name() }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
    @endif
    @endforeach
</div>

<form method="POST" action="{{ route('draw.groups.generate', [$tournament, $category]) }}" class="mt-3">
    @csrf
    <button type="submit" class="btn btn-accent">Generar grupos y partidos</button>
    <a href="{{ route('categories.show', [$tournament, $category]) }}" class="btn btn-soft">Cancelar</a>
</form>
@endsection