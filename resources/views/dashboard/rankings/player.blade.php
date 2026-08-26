@extends('layouts.app')

@section('title', $p['name'] . ' · ' . $system->name)

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => $system->name, 'url' => route('ranking-systems.show', $system)],
        ['label' => 'Tabla', 'url' => route('ranking-systems.leaderboard', [$system, 'cat' => $scope])],
        ['label' => $p['name']],
    ]" />

<div class="page-head">
    <div>
        <h1>{{ $p['name'] }}</h1>
        <div class="page-sub">{{ $system->name }}@if($system->owner_label) · {{ $system->owner_label }}@endif</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('ranking-systems.leaderboard', [$system, 'cat' => $scope]) }}" class="btn btn-soft">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver a la tabla
        </a>
    </div>
</div>

@include('dashboard.partials.flash')

<div class="tc-card">
    <div class="tc-card__body">
        @include('dashboard.rankings.partials.player-detail')
    </div>
</div>
@endsection
