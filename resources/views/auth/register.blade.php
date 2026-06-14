{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.guest')

@section('title', 'Crear cuenta')

@section('content')
<h1 style="font-size:18px;font-weight:700;margin-bottom:4px;">Crear cuenta</h1>
<p style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">
    Regístrate para inscribirte a torneos.
</p>

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

<form method="POST" action="{{ route('register') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Nombre completo</label>
        <input type="text" name="name" value="{{ old('name') }}" required autofocus
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
        <label class="form-label" style="font-size:13px;font-weight:500;">Contraseña</label>
        <input type="password" name="password" required
            class="form-control @error('password') is-invalid @enderror" style="border-radius:var(--radius);">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Confirmar contraseña</label>
        <input type="password" name="password_confirmation" required
            class="form-control" style="border-radius:var(--radius);">
    </div>

    {{-- Terms + privacy consent (covers data collection / minor consent per policy) --}}
    <div class="form-check mb-3">
        <input type="checkbox" name="terms" id="terms" value="1" required
            class="form-check-input @error('terms') is-invalid @enderror">
        <label for="terms" class="form-check-label" style="font-size:12.5px;color:var(--text-muted);">
            Acepto los <a href="#" target="_blank">términos</a> y el
            <a href="#" target="_blank">aviso de privacidad</a>. Si el participante es menor
            de edad, confirmo el consentimiento de su padre/madre o tutor.
        </label>
        @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <button type="submit" class="btn btn-accent w-100">Crear cuenta</button>
</form>

<p style="text-align:center;font-size:13px;color:var(--text-muted);margin-top:18px;margin-bottom:0;">
    ¿Ya tienes cuenta?
    <a href="{{ route('login') }}">Iniciar sesión</a>
</p>
@endsection