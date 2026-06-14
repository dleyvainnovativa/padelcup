<?php

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Enums\RegistrationSource;
use App\Enums\RegistrationStatus;
use App\Models\Payment;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

/**
 * Turns Stripe payment outcomes into local state, and keeps the registration's
 * roll-up payment_status in sync. A pair is "fully paid" only when every
 * player charge on the registration has succeeded.
 */
class PaymentReconciler
{
    /** Mark a payment paid (from payment_intent.succeeded) and re-roll the registration. */
    public function markPaid(Payment $payment, ?string $chargeId = null): void
    {
        DB::transaction(function () use ($payment, $chargeId) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'stripe_charge_id' => $chargeId ?? $payment->stripe_charge_id,
                'paid_at' => now(),
            ]);

            $this->rollUpRegistration($payment->registration);
        });
    }

    /** Mark a payment failed (from payment_intent.payment_failed). */
    public function markFailed(Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Unpaid]);
        $this->rollUpRegistration($payment->registration);
    }

    /**
     * Recompute the registration's payment_status and confirm self-registered
     * pairs once both halves are paid.
     */
    public function rollUpRegistration(Registration $registration): void
    {
        $registration->loadMissing('pair', 'invitation');
        $pair = $registration->pair;

        // A pair needs BOTH halves paid. If a pending invitation exists, the
        // partner hasn't joined/paid yet — so expected is 2 even though
        // player2_id is still null. Without this, the registrant's single
        // payment would wrongly confirm a half-empty pair.
        $hasPendingInvite = $registration->invitation
            && $registration->invitation->status === \App\Enums\InvitationStatus::Pending;

        $expected = (($pair && $pair->isComplete()) || $hasPendingInvite) ? 2 : 1;

        $paidCount = $registration->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->distinct('player_id')
            ->count('player_id');

        $fullyPaid = $paidCount >= $expected;

        $registration->payment_status = $fullyPaid ? PaymentStatus::Paid : PaymentStatus::Pending;

        // Self-registration: only confirm (enter the pool) once fully paid.
        if ($registration->source === RegistrationSource::Self_) {
            if ($fullyPaid && $registration->status === RegistrationStatus::PendingPayment) {
                $registration->status = RegistrationStatus::Confirmed;
                $registration->hold_expires_at = null;

                // Mark any pending invitation as accepted now both halves paid.
                $registration->loadMissing('invitation');
                if ($registration->invitation && $registration->invitation->isPending()) {
                    $registration->invitation->update([
                        'status' => \App\Enums\InvitationStatus::Accepted,
                        'accepted_at' => now(),
                    ]);
                }
            }
        }

        $registration->save();
    }
}
