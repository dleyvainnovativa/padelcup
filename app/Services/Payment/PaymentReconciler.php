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
 * roll-up payment_status in sync. A unit is "fully paid" only when every
 * player charge it expects has succeeded — 2 for doubles, 1 for singles.
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
     * units once all expected halves are paid.
     */
    public function rollUpRegistration(Registration $registration): void
    {
        $registration->loadMissing('pair', 'invitation');
        $pair = $registration->pair;

        $expected = $this->expectedFeeCount($registration);

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

                // Mark any pending invitation as accepted now all halves paid.
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

    /**
     * How many player fees confirm this registration.
     *
     *   Singles  → 1, unconditionally. A singles unit has one player and one
     *              fee; there is no partner slot and never a pending invite, so
     *              a single paid charge fully confirms it.
     *
     *   Doubles  → 2 whenever the pair is complete OR a partner invitation is
     *              still pending. The pending-invite case is why we can't just
     *              count players on the pair: player2_id is still null while the
     *              partner hasn't joined, but two fees are still owed. Without
     *              it, the registrant's single payment would wrongly confirm a
     *              half-empty pair.
     *
     * Making singles explicit (rather than relying on "not complete + no
     * invite → 1") means the money check can never be silently flipped by a
     * change to isComplete() or by a stray invitation row.
     */
    private function expectedFeeCount(Registration $registration): int
    {
        $pair = $registration->pair;

        if ($pair && $pair->is_singles) {
            return 1;
        }

        $hasPendingInvite = $registration->invitation
            && $registration->invitation->status === \App\Enums\InvitationStatus::Pending;

        return (($pair && $pair->isComplete()) || $hasPendingInvite) ? 2 : 1;
    }
}
