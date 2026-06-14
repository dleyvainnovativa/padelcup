@extends('layouts.app')

@section('title', 'Pago')

@push('head')
<script src="https://js.stripe.com/v3/"></script>
@endpush

@section('content')
<div class="page-head">
    <div>
        <h1>Pago de inscripción</h1>
        <div class="page-sub">{{ $registration->category->name }} · {{ $registration->category->tournament->name }}</div>
    </div>
</div>

@if($pending->isEmpty())
<div class="tc-card" style="max-width:560px;">
    <div class="tc-card__body">
        <x-pill variant="ok" dot>Sin pagos pendientes</x-pill>
        <p style="font-size:13px;color:var(--text-muted);margin-top:10px;">No hay cargos pendientes para esta inscripción.</p>
        <a href="{{ route('registration.confirmation', $registration) }}" class="btn btn-soft mt-2">Ver estado</a>
    </div>
</div>
@else
<div class="tc-card" style="max-width:560px;">
    <div class="tc-card__body">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span style="font-size:13px;color:var(--text-muted);">Cargo actual</span>
            <span class="font-mono" id="current-amount" style="font-size:18px;font-weight:700;"></span>
        </div>
        <div style="font-size:12px;color:var(--text-faint);margin-bottom:14px;" id="progress-label"></div>

        <div id="payment-element" class="mb-3"></div>
        <div id="pay-error" style="font-size:13px;color:var(--danger-text);margin-bottom:10px;"></div>

        <button id="pay-button" class="btn btn-accent w-100">
            <span id="pay-button-text">Pagar</span>
        </button>
    </div>
</div>

<script type="module">
    const stripe = Stripe(@json($stripeKey));

    // Each pending charge is its own PaymentIntent (per-player). Collect
    // them one at a time: mount the Payment Element for the current
    // intent's client secret, confirm, then advance to the next.
    const charges = @json($pending->map(fn($p) => [
        'secret' => $p->meta['client_secret'] ?? null,
        'amount' => $p->amount_centavos,
    ])->values());

    const confirmationUrl = @json(route('registration.confirmation', $registration));
    let index = 0;
    let elements, paymentElement;

    const amountEl = document.getElementById('current-amount');
    const progressEl = document.getElementById('progress-label');
    const btn = document.getElementById('pay-button');
    const errBox = document.getElementById('pay-error');

    function fmt(centavos) {
        return '$' + (centavos / 100).toLocaleString('es-MX', {
            minimumFractionDigits: 2
        }) + ' MXN';
    }

    function mountCurrent() {
        const charge = charges[index];
        amountEl.textContent = fmt(charge.amount);
        progressEl.textContent = charges.length > 1 ?
            `Jugador ${index + 1} de ${charges.length}` :
            '';

        if (paymentElement) paymentElement.unmount();
        elements = stripe.elements({
            clientSecret: charge.secret
        });
        paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');
    }

    mountCurrent();

    btn.addEventListener('click', async () => {
        btn.classList.add('btn-loading');
        errBox.textContent = '';

        const isLast = index === charges.length - 1;

        const {
            error,
            paymentIntent
        } = await stripe.confirmPayment({
            elements,
            redirect: 'if_required',
            confirmParams: {
                return_url: confirmationUrl
            },
        });

        if (error) {
            errBox.textContent = error.message ?? 'No se pudo procesar el pago.';
            btn.classList.remove('btn-loading');
            return;
        }

        // This intent succeeded (no redirect needed). Advance or finish.
        if (isLast) {
            window.location.href = confirmationUrl;
        } else {
            index++;
            btn.classList.remove('btn-loading');
            mountCurrent();
        }
    });
</script>
@endif
@endsection