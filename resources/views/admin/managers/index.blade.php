@extends('layouts.app')

@section('title', 'Organizadores')

@section('content')
<div class="page-head">
    <div>
        <h1>Organizadores</h1>
        <div class="page-sub">Cuentas con permiso para crear y administrar torneos.</div>
    </div>
    <a href="{{ route('admin.managers.create') }}" class="btn btn-accent">
        <i class="fa-solid fa-plus me-1"></i> Nuevo organizador
    </a>
</div>

@if (session('status'))
<div class="alert alert-success py-2 px-3" style="font-size:13px;border-radius:var(--radius);">
    {{ session('status') }}
</div>
@endif

<div class="tc-card">
    <div class="tc-table-wrap">
        <table class="tc-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Alta</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($managers as $manager)
                <tr>
                    <td>{{ $manager->name }}</td>
                    <td class="font-mono">{{ $manager->email }}</td>
                    <td>{{ $manager->created_at->timezone('America/Mexico_City')->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="3" style="color:var(--text-muted);">Aún no hay organizadores.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $managers->links() }}</div>
@endsection