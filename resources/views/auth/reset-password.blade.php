{{-- resources/views/auth/reset-password.blade.php --}}
@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
<h1 style="font-size:18px;font-weight:700;margin-bottom:4px;">Nueva contraseña</h1>
<p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">
    Elige una contraseña nueva para tu cuenta.
</p>

<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">

    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Correo</label>
        <input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
            class="form-control @error('email') is-invalid @enderror" style="border-radius:var(--radius);">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Contraseña nueva</label>
        <input type="password" name="password" required
            class="form-control @error('password') is-invalid @enderror" style="border-radius:var(--radius);">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Confirmar contraseña</label>
        <input type="password" name="password_confirmation" required
            class="form-control" style="border-radius:var(--radius);">
    </div>

    <button type="submit" class="btn btn-accent w-100">Restablecer contraseña</button>
</form>
@endsection