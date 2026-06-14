@extends('layouts.app')

@section('title', 'Pagos')

@section('content')
<div class="page-head">
    <div>
        <h1>Pagos</h1>
        <div class="page-sub">Cobros por jugador en tus torneos.</div>
    </div>
    <a href="{{ route('connect.index') }}" class="btn btn-soft"><i class="fa-brands fa-stripe-s me-1"></i> Configuración de cobros</a>
</div>

@include('dashboard.partials.flash')

@error('refund')
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">{{ $message }}</div>
@enderror

<div class="tc-card">
    <div class="tc-table-wrap">
        <table class="tc-table">
            <thead>
                <tr>
                    <th>Jugador</th>
                    <th>Categoría</th>
                    <th>Monto</th>
                    <th>Comisión</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->player->name }}</td>
                    <td>{{ $payment->registration->category->name }}</td>
                    <td class="font-mono">{{ $payment->amountFormatted() }}</td>
                    <td class="font-mono" style="color:var(--text-muted);">${{ number_format($payment->platform_fee_centavos/100,2) }}</td>
                    <td><x-pill :variant="$payment->status->pillVariant()" dot>{{ $payment->status->label() }}</x-pill></td>
                    <td style="text-align:right;">
                        @if($payment->status->value === 'paid')
                        <form method="POST" action="{{ route('payments.refund', $payment) }}"
                            data-confirm="Se reembolsará {{ $payment->amountFormatted() }} al jugador. Tu comisión de plataforma se conserva."
                            data-confirm-title="Reembolsar"
                            data-confirm-variant="danger"
                            data-confirm-ok="Reembolsar">
                            @csrf
                            <label style="font-size:11px;color:var(--text-muted);display:inline-flex;align-items:center;gap:5px;margin-right:8px;">
                                <input type="checkbox" name="withdraw" value="1" checked class="form-check-input" style="margin:0;">
                                Retirar pareja
                            </label>
                            <button class="btn btn-soft btn-sm" style="color:var(--danger-text);">Reembolsar</button>
                        </form>
                        @elseif($payment->status->value === 'refunded')
                        <span style="font-size:12px;color:var(--text-faint);">Reembolsado</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="color:var(--text-muted);">Aún no hay pagos registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $payments->links() }}</div>
@endsection