@extends('layouts.app')

@section('title', 'Inscribirme')

@section('content')
<div class="page-head">
    <div>
        <h1>Inscribirme a {{ $category->name }}</h1>
        <div class="page-sub">{{ $category->tournament->name }} · {{ $category->priceFormatted() }} por jugador</div>
    </div>
</div>

@include('dashboard.partials.flash')

@if ($errors->has('registration'))
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    {{ $errors->first('registration') }}
</div>
@endif

<div class="tc-card" style="max-width:640px;" x-data="{ flow: 'pay_both' }">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('registration.store', $category) }}">
            @csrf

            {{-- Flow choice --}}
            <div style="font-size:12px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">¿Cómo quieres inscribir a tu pareja?</div>
            <div class="d-flex flex-column gap-2 mb-3">
                <label class="d-flex align-items-start gap-2 p-2" style="border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                    <input type="radio" name="flow" value="pay_both" x-model="flow" class="form-check-input mt-1">
                    <span>
                        <span style="font-weight:600;font-size:13px;">Pagar por los dos</span>
                        <span style="display:block;font-size:12px;color:var(--text-muted);">Agrego a mi compañero/a y cubro ambas inscripciones.</span>
                    </span>
                </label>
                <label class="d-flex align-items-start gap-2 p-2" style="border:1px solid var(--border);border-radius:var(--radius);cursor:pointer;">
                    <input type="radio" name="flow" value="invite" x-model="flow" class="form-check-input mt-1">
                    <span>
                        <span style="font-weight:600;font-size:13px;">Pagar solo lo mío e invitar</span>
                        <span style="display:block;font-size:12px;color:var(--text-muted);">Pago mi parte y envío un enlace para que mi compañero/a complete y pague la suya.</span>
                    </span>
                </label>
            </div>

            {{-- Player 1 (me) --}}
            <div style="font-size:12px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Tus datos</div>
            <input type="text" name="player1_name" value="{{ old('player1_name', auth()->user()->name) }}" required placeholder="Tu nombre"
                class="form-control mb-2 @error('player1_name') is-invalid @enderror" style="border-radius:var(--radius);">
            @error('player1_name')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
            <input type="text" name="player1_phone" value="{{ old('player1_phone') }}" placeholder="Tu teléfono (opcional)" class="form-control mb-3" style="border-radius:var(--radius);">

            {{-- Player 2 (partner) — required only when paying for both --}}
            <div style="font-size:12px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">
                <span x-show="flow === 'pay_both'">Datos de tu compañero/a</span>
                <span x-show="flow === 'invite'">Invitación (opcional)</span>
            </div>
            <input type="text" name="player2_name" value="{{ old('player2_name') }}"
                placeholder="Nombre de tu compañero/a"
                x-bind:required="flow === 'pay_both'"
                x-show="flow === 'pay_both'"
                class="form-control mb-2 @error('player2_name') is-invalid @enderror" style="border-radius:var(--radius);">
            @error('player2_name')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
            <input type="email" name="player2_email" value="{{ old('player2_email') }}"
                placeholder="Correo de tu compañero/a (para enviar el enlace)"
                class="form-control mb-3" style="border-radius:var(--radius);">

            {{-- Terms --}}
            <div class="form-check mb-3">
                <input type="checkbox" name="terms" id="terms" value="1" required class="form-check-input @error('terms') is-invalid @enderror">
                <label for="terms" class="form-check-label" style="font-size:12.5px;color:var(--text-muted);">
                    Acepto los <a href="#" target="_blank">términos</a> y el <a href="#" target="_blank">aviso de privacidad</a>.
                </label>
                @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-accent w-100">
                <span x-show="flow === 'pay_both'">Continuar al pago (2 jugadores)</span>
                <span x-show="flow === 'invite'">Continuar al pago (mi parte)</span>
            </button>
        </form>
    </div>
</div>
@endsection