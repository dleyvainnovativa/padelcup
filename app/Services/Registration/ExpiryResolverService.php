<?php

namespace App\Services\Registration;

use App\Enums\ExpiryPolicy;
use App\Enums\InvitationStatus;
use App\Enums\PaymentStatus;
use App\Enums\RegistrationStatus;
use App\Models\Registration;
use App\Services\Payment\RefundService;
use Illuminate\Support\Facades\DB;

/**
 * Resolves expired self-registration holds according to the tournament's
 * expiry policy. The common cases resolve automatically; manual_review leaves
 * them for the manager's issues queue.
 *
 * An "expired" registration is pending_payment past its hold_expires_at —
 * typically a half-paid pair whose partner never completed.
 */
class ExpiryResolverService
{
    public function __construct(private RefundService $refunds) {}

    /** Resolve a single expired registration per the tournament policy. */
    public function resolve(Registration $registration): void
    {
        $policy = $registration->category->tournament->expiry_policy;

        DB::transaction(function () use ($registration, $policy) {
            // Expire the pending invitation if present.
            $registration->loadMissing('invitation', 'payments');
            if ($registration->invitation && $registration->invitation->isPending()) {
                $registration->invitation->update(['status' => InvitationStatus::Expired]);
            }

            match ($policy) {
                ExpiryPolicy::AutoRefund => $this->autoRefund($registration),
                ExpiryPolicy::HoldCredit => $this->holdForReview($registration), // credit ledger is future; park for now
                ExpiryPolicy::ManualReview => $this->holdForReview($registration),
            };
        });
    }

    /** Refund any paid half and cancel the registration. */
    private function autoRefund(Registration $registration): void
    {
        foreach ($registration->payments as $payment) {
            if ($payment->status === PaymentStatus::Paid) {
                try {
                    $this->refunds->refund($payment);
                } catch (\Throwable $e) {
                    // If a refund fails, fall back to manual review.
                    $this->holdForReview($registration);
                    return;
                }
            }
        }

        $registration->update([
            'status' => RegistrationStatus::Cancelled,
            'payment_status' => PaymentStatus::Refunded,
        ]);
    }

    /** Leave it flagged for the manager to resolve in the issues queue. */
    private function holdForReview(Registration $registration): void
    {
        // Status stays pending_payment but hold is expired → surfaces in the
        // issues query. We keep the money where it is until the manager acts.
    }
}
