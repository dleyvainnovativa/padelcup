@extends('layouts.guest')

@section('title', 'Completa tu inscripción')

@section('content')
<h1 style="font-size:18px;font-weight:700;margin-bottom:4px;">Completa tu inscripción</h1>
<p style="color:var(--text-muted);font-size:13px;margin-bottom:18px;">
    {{ $invitation->pair->player1->name }} te invitó a jugar
    <strong>{{ $invitation->registration->category->name }}</strong>
    en {{ $invitation->registration->category->tournament->name }}.
    Completa tus datos y paga tu parte ({{ $invitation->registration->category->priceFormatted() }}).
</p>

<form method="POST" action="{{ route('quick.store', $invitation) }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Tu nombre</label>
        <input type="text" name="name" value="{{ old('name') }}" required
            class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Correo</label>
        <input type="email" name="email" value="{{ old('email', $invitation->invitee_email) }}" required
            class="form-control @error('email') is-invalid @enderror" style="border-radius:var(--radius);">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Teléfono (opcional)</label>
        <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" style="border-radius:var(--radius);">
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" name="terms" id="terms" value="1" required class="form-check-input @error('terms') is-invalid @enderror">
        <label for="terms" class="form-check-label" style="font-size:12.5px;color:var(--text-muted);">
            Acepto los <a href="#" target="_blank">términos</a> y el <a href="#" target="_blank">aviso de privacidad</a>.
        </label>
        @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-accent w-100">Continuar al pago</button>
</form>
@endsection