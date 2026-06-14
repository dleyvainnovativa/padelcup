<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tournament;
use App\Services\Payment\RefundService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    /** Payments across the manager's tournaments. */
    public function index(Request $request)
    {
        $tournamentIds = Tournament::where('manager_id', $request->user()->id)->pluck('id');

        $payments = Payment::whereHas('registration.category', function ($q) use ($tournamentIds) {
            $q->whereIn('tournament_id', $tournamentIds);
        })
            ->with(['player', 'registration.category'])
            ->latest()
            ->paginate(30);

        return view('dashboard.payments.index', compact('payments'));
    }

    /** Issue a refund (platform fee preserved); optionally withdraw the pair. */
    public function refund(Request $request, Payment $payment)
    {
        // Authorize: the payment must belong to one of the manager's tournaments.
        abort_unless(
            $payment->registration->category->tournament->manager_id === $request->user()->id
                || $request->user()->isAdmin(),
            403
        );

        $data = $request->validate([
            'amount_centavos' => ['nullable', 'integer', 'min:1', 'max:' . $payment->amount_centavos],
            'withdraw' => ['nullable', 'boolean'],
        ]);

        try {
            $this->refunds->refund($payment, $data['amount_centavos'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        // If the manager chose to withdraw the pair, drop it from the pool.
        if ($request->boolean('withdraw')) {
            $this->withdrawIfRefunded($payment->registration);
        }

        return back()->with('status', 'Reembolso procesado.');
    }

    /**
     * If a registration has no remaining paid charges, remove it from the pool.
     * Pre-lock → Cancelled (clean removal). Post-lock → Withdrawn (Phase 6
     * converts remaining matches to walkovers).
     */
    private function withdrawIfRefunded(\App\Models\Registration $registration): void
    {
        $stillPaid = $registration->payments()
            ->where('status', \App\Enums\PaymentStatus::Paid->value)
            ->exists();

        if ($stillPaid) {
            return; // partial refund — leave the pair in
        }

        $registration->update([
            'status' => $registration->category->tournament->isLocked()
                ? \App\Enums\RegistrationStatus::Withdrawn
                : \App\Enums\RegistrationStatus::Cancelled,
            'payment_status' => \App\Enums\PaymentStatus::Refunded,
        ]);
    }
}
