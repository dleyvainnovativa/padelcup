@extends('layouts.app')

@section('title', 'Editar sistema de ranking')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Sistemas de ranking', 'url' => route('ranking-systems.index')],
        ['label' => $system->name, 'url' => route('ranking-systems.show', $system)],
        ['label' => 'Editar'],
    ]" />

<div class="page-head">
    <div>
        <h1>Editar sistema</h1>
        <div class="page-sub">{{ $system->name }}</div>
    </div>
</div>

@include('dashboard.partials.flash')
@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<form method="POST" action="{{ route('ranking-systems.update', $system) }}">
    @csrf
    @method('PUT')
    @include('ranking-systems._form')

    <div class="d-flex gap-2">
        <button class="btn btn-accent"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar cambios</button>
        <a href="{{ route('ranking-systems.index') }}" class="btn btn-soft">Cancelar</a>
    </div>
</form>
@endsection
