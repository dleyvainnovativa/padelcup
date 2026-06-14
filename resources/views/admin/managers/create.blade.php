@extends('layouts.app')

@section('title', 'Nuevo organizador')

@section('content')
<div class="page-head">
    <div>
        <h1>Nuevo organizador</h1>
        <div class="page-sub">Crea una cuenta de organizador validada manualmente.</div>
    </div>
</div>

<div class="tc-card" style="max-width:520px;">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('admin.managers.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Nombre completo</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                    class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Correo</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="form-control @error('email') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" style="font-size:13px;font-weight:500;">Contraseña temporal</label>
                <input type="text" name="password" value="{{ old('password') }}" required
                    class="form-control @error('password') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div style="font-size:12px;color:var(--text-faint);margin-top:4px;">
                    El organizador podrá cambiarla después.
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-accent">Crear organizador</button>
                <a href="{{ route('admin.managers.index') }}" class="btn btn-soft">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection