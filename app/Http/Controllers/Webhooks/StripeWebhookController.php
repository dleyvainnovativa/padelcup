<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\PaymentReconciler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

/**
 * Stripe webhook receiver. Registered OUTSIDE the CSRF middleware and verified
 * by signature instead. Handles both platform events (payments) and Connect
 * account events (onboarding status).
 *
 * Configure the endpoint in Stripe → Webhooks. Listen for at least:
 *   payment_intent.succeeded, payment_intent.payment_failed,
 *   charge.refunded, account.updated
 */
class StripeWebhookController extends Controller
{
    public function __construct(private PaymentReconciler $reconciler) {}

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature failed', ['msg' => $e->getMessage()]);
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            return response('Invalid payload', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutCompleted($event->data->object),
            'checkout.session.async_payment_succeeded' => $this->onCheckoutCompleted($event->data->object),
            'checkout.session.async_payment_failed' => $this->onCheckoutFailed($event->data->object),
            'payment_intent.succeeded' => $this->onIntentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->onIntentFailed($event->data->object),
            'charge.refunded' => $this->onChargeRefunded($event->data->object),
            'account.updated' => $this->onAccountUpdated($event->data->object),
            default => null, // ignore unhandled events
        };

        return response('ok', 200);
    }

    private function onCheckoutCompleted($session): void
    {
        // Card: paid immediately. OXXO: 'completed' fires when the voucher is
        // generated (payment_status not yet 'paid') — wait for async success.
        $status = $session->payment_status ?? ($session['payment_status'] ?? null);
        if ($status !== 'paid') {
            return;
        }

        $payments = $this->paymentsFromSession($session);
        $intentId = $session->payment_intent ?? ($session['payment_intent'] ?? null);

        foreach ($payments as $payment) {
            // Backfill the PaymentIntent id if it was null at session creation
            // (needed later for refunds).
            if ($intentId && blank($payment->stripe_payment_intent_id)) {
                $payment->update(['stripe_payment_intent_id' => $intentId]);
            }
            $this->reconciler->markPaid($payment, $intentId);
        }
    }

    private function onCheckoutFailed($session): void
    {
        foreach ($this->paymentsFromSession($session) as $payment) {
            $this->reconciler->markFailed($payment);
        }
    }

    /**
     * Resolve all Payment rows tied to a Checkout Session. Handles both the
     * single-player charge and the combined pair charge (payment_ids = "1,2").
     *
     * @return \Illuminate\Support\Collection<int, Payment>
     */
    private function paymentsFromSession($session): \Illuminate\Support\Collection
    {
        $meta = $session->metadata ?? null;
        $idsCsv = $meta->payment_ids ?? ($session['metadata']['payment_ids'] ?? null);

        // Combined pair charge: "id1,id2"
        if ($idsCsv) {
            $ids = array_filter(array_map('trim', explode(',', $idsCsv)));
            $found = Payment::whereIn('id', $ids)->get();
            if ($found->isNotEmpty()) {
                return $found;
            }
        }

        // Single charge: payment_id in metadata
        $single = $meta->payment_id ?? ($session['metadata']['payment_id'] ?? null);
        if ($single && ($p = Payment::find($single))) {
            return collect([$p]);
        }

        // Fallback: match by the stored checkout session id.
        $sessionId = $session->id ?? ($session['id'] ?? null);
        if ($sessionId) {
            $bySession = Payment::where('meta->checkout_session_id', $sessionId)->get();
            if ($bySession->isNotEmpty()) {
                return $bySession;
            }
        }

        // Last resort: by payment intent id.
        $pi = $session->payment_intent ?? ($session['payment_intent'] ?? null);
        if ($pi) {
            return Payment::where('stripe_payment_intent_id', $pi)->get();
        }

        return collect();
    }

    private function onIntentSucceeded($intent): void
    {
        $payment = $this->findPayment($intent);
        if (! $payment) return;

        $chargeId = $intent->latest_charge ?? null;
        $this->reconciler->markPaid($payment, $chargeId);
    }

    private function onIntentFailed($intent): void
    {
        $payment = $this->findPayment($intent);
        if (! $payment) return;

        $this->reconciler->markFailed($payment);
    }

    private function onChargeRefunded($charge): void
    {
        $payment = Payment::where('stripe_charge_id', $charge->id)->first();
        // Refund state is set authoritatively by RefundService; this is a
        // safety net for refunds issued directly in the Stripe dashboard.
        if ($payment && $charge->refunded) {
            $payment->update([
                'refunded_centavos' => $charge->amount_refunded,
                'status' => \App\Enums\PaymentStatus::Refunded,
                'refunded_at' => now(),
            ]);
            $this->reconciler->rollUpRegistration($payment->registration);
        }
    }

    private function onAccountUpdated($account): void
    {
        $manager = User::where('stripe_account_id', $account->id)->first();
        if (! $manager) return;

        $manager->forceFill([
            'stripe_charges_enabled' => (bool) $account->charges_enabled,
            'stripe_payouts_enabled' => (bool) $account->payouts_enabled,
            'stripe_onboarded_at' => $account->charges_enabled ? ($manager->stripe_onboarded_at ?? now()) : null,
        ])->save();
    }

    private function findPayment($intent): ?Payment
    {
        // Prefer our metadata; fall back to the intent id.
        $id = $intent->metadata->payment_id ?? null;
        if ($id && ($p = Payment::find($id))) {
            return $p;
        }
        return Payment::where('stripe_payment_intent_id', $intent->id)->first();
    }
}
