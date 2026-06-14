@extends('layouts.app')

@section('title', 'Cobros (Stripe)')

@section('content')
<div class="page-head">
    <div>
        <h1>Cobros con Stripe</h1>
        <div class="page-sub">Conecta tu cuenta para recibir los pagos de las inscripciones.</div>
    </div>
</div>

@include('dashboard.partials.flash')

<div class="tc-card" style="max-width:620px;">
    <div class="tc-card__body">
        @if($manager->stripe_charges_enabled)
        <div class="d-flex align-items-center gap-2 mb-2">
            <x-pill variant="ok" dot>Conectado</x-pill>
            <span style="font-size:13px;color:var(--text-muted);">Ya puedes recibir pagos.</span>
        </div>
        <ul style="font-size:13px;color:var(--text-muted);margin:12px 0 0;padding-left:18px;">
            <li>Cobros habilitados: {{ $manager->stripe_charges_enabled ? 'Sí' : 'No' }}</li>
            <li>Pagos (payouts) habilitados: {{ $manager->stripe_payouts_enabled ? 'Sí' : 'No' }}</li>
        </ul>
        <div style="font-size:12px;color:var(--text-faint);margin-top:14px;">
            Los jugadores pagan el precio de la categoría. Tú recibes ese monto menos la
            comisión de Stripe; la plataforma cobra ${{ number_format(($manager->tournaments()->value('platform_fee_centavos') ?? 5000)/100, 2) }} por jugador.
        </div>
        @elseif($manager->stripe_account_id)
        <div class="d-flex align-items-center gap-2 mb-3">
            <x-pill variant="warn" dot>Incompleto</x-pill>
            <span style="font-size:13px;color:var(--text-muted);">Falta terminar la configuración en Stripe.</span>
        </div>
        <a href="{{ route('connect.start') }}" class="btn btn-accent">Continuar configuración</a>
        @else
        <p style="font-size:14px;color:var(--text-muted);">
            Aún no has conectado una cuenta de Stripe. Es gratis y toma unos minutos.
            Los pagos de los jugadores llegarán directamente a tu cuenta.
        </p>
        <a href="{{ route('connect.start') }}" class="btn btn-accent">
            <i class="fa-brands fa-stripe-s me-1"></i> Conectar con Stripe
        </a>
        @endif
    </div>
</div>
@endsection