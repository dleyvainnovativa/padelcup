<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

/**
 * Refunds. Per the agreed policy, the PLATFORM does not lose its fee on a
 * refund — we do NOT reverse the application fee (refund_application_fee=false).
 * The refund comes out of the connected account's balance.
 *
 * (If a manager wants to absorb their own loss differently, that's a future
 * per-manager setting; the default here protects the platform's cut.)
 */
class RefundService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret'));
    }

    /**
     * Refund a player payment (full by default; partial if $centavos given).
     * Does not reverse the platform application fee.
     */
    public function refund(Payment $payment, ?int $centavos = null): Payment
    {
        if (! $payment->stripe_payment_intent_id || $payment->status !== PaymentStatus::Paid) {
            throw new \RuntimeException('Solo se pueden reembolsar pagos completados.');
        }

        $amount = $centavos ?? ($payment->amount_centavos - $payment->refunded_centavos);
        if ($amount <= 0) {
            throw new \RuntimeException('No hay monto reembolsable.');
        }

        $refund = $this->stripe->refunds->create([
            'payment_intent' => $payment->stripe_payment_intent_id,
            'amount' => $amount,
            'refund_application_fee' => false, // platform keeps its cut
            'reverse_transfer' => true,        // pull back from connected account
        ]);

        return DB::transaction(function () use ($payment, $refund, $amount) {
            $newRefunded = $payment->refunded_centavos + $amount;
            $fullyRefunded = $newRefunded >= $payment->amount_centavos;

            $payment->update([
                'stripe_refund_id' => $refund->id,
                'refunded_centavos' => $newRefunded,
                'status' => $fullyRefunded ? PaymentStatus::Refunded : $payment->status,
                'refunded_at' => now(),
            ]);

            return $payment->refresh();
        });
    }
}
