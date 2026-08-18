@extends('layouts.app')

@section('title', 'Nuevo sistema de ranking')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => 'Nuevo'],
    ]" />

<div class="page-head">
    <div>
        <h1>Nuevo sistema de ranking</h1>
        <div class="page-sub">Los puntos vienen precargados con valores sugeridos; ajústalos.</div>
    </div>
</div>

@include('dashboard.partials.flash')
@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('ranking-systems.store') }}">
    @csrf
    @include('ranking-systems._form')

    <div class="d-flex gap-2">
        <button class="btn btn-accent"><i class="fa-solid fa-floppy-disk me-1"></i> Crear sistema</button>
        <a href="{{ route('ranking-systems.index') }}" class="btn btn-soft">Cancelar</a>
    </div>
</form>
@endsection
