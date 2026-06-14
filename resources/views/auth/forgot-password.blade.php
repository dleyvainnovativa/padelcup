{{-- resources/views/auth/forgot-password.blade.php --}}
@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
<h1 style="font-size:18px;font-weight:700;margin-bottom:4px;">Recuperar contraseña</h1>
<p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">
    Te enviaremos un enlace para restablecerla.
</p>

@if (session('status'))
<div class="alert alert-success py-2 px-3" style="font-size:13px;border-radius:var(--radius);">
    {{ session('status') }}
</div>
@endif

<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Correo</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
            class="form-control @error('email') is-invalid @enderror" style="border-radius:var(--radius);">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-accent w-100">Enviar enlace</button>
</form>

<p style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:18px;margin-bottom:0;">
    <a href="{{ route('login') }}">Volver a iniciar sesión</a>
</p>
@endsection