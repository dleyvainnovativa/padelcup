{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
<h1 style="font-size:18px;font-weight:700;margin-bottom:4px;">Iniciar sesión</h1>
<p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">
    Accede para administrar tus torneos.
</p>

{{-- Session status (e.g. after password reset) --}}
@if (session('status'))
<div class="alert alert-success py-2 px-3" style="font-size:13px;border-radius:var(--radius);">
    {{ session('status') }}
</div>
@endif

{{-- Social login --}}
<div class="d-grid gap-2 mb-3">
    <a href="{{ route('oauth.redirect', 'google') }}" class="btn btn-soft d-flex align-items-center justify-content-center gap-2">
        <i class="fa-brands fa-google"></i> Continuar con Google
    </a>
    <a href="{{ route('oauth.redirect', 'apple') }}" class="btn btn-soft d-flex align-items-center justify-content-center gap-2">
        <i class="fa-brands fa-apple"></i> Continuar con Apple
    </a>
</div>

<div class="d-flex align-items-center gap-2 my-3" style="color:var(--text-faint);font-size:12px;">
    <span style="flex:1;height:1px;background:var(--border);"></span> o con tu correo
    <span style="flex:1;height:1px;background:var(--border);"></span>
</div>

<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Correo</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus
            class="form-control @error('email') is-invalid @enderror"
            style="border-radius:var(--radius);">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <label class="form-label" style="font-size:13px;font-weight:500;">Contraseña</label>
            @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}" style="font-size:12px;">¿Olvidaste tu contraseña?</a>
            @endif
        </div>
        <input type="password" name="password" required
            class="form-control @error('password') is-invalid @enderror"
            style="border-radius:var(--radius);">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" name="remember" id="remember" class="form-check-input">
        <label for="remember" class="form-check-label" style="font-size:13px;">Recordarme</label>
    </div>

    <button type="submit" class="btn btn-accent w-100">Iniciar sesión</button>
</form>

<p style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:18px;margin-bottom:0;">
    ¿No tienes cuenta?
    <a href="{{ route('register') }}">Crear una</a>
</p>
@endsection