<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Tournament;
use App\Services\Payment\RefundService;
use Illuminate\Http\Request;

/**
 * The manager's "issues" queue: self-registrations stuck in pending_payment
 * past their hold (partner never completed, half-paid orphans). Manager
 * resolves each: refund, cancel, or extend the hold.
 */
class IssueController extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(Request $request)
    {
        $tournamentIds = Tournament::where('manager_id', $request->user()->id)->pluck('id');

        $issues = Registration::query()
            ->whereIn('category_id', function ($q) use ($tournamentIds) {
                $q->select('id')->from('categories')->whereIn('tournament_id', $tournamentIds);
            })
            ->where('status', RegistrationStatus::PendingPayment->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<', now())
            ->with(['pair.player1', 'pair.player2', 'category', 'payments', 'invitation'])
            ->latest()
            ->paginate(20);

        return view('dashboard.issues.index', compact('issues'));
    }

    public function resolve(Request $request, Registration $registration)
    {
        abort_unless(
            $registration->category->tournament->manager_id === $request->user()->id
                || $request->user()->isAdmin(),
            403
        );

        $action = $request->validate([
            'action' => ['required', 'in:refund,cancel,extend'],
        ])['action'];

        match ($action) {
            'refund' => $this->doRefund($registration),
            'cancel' => $registration->update(['status' => RegistrationStatus::Cancelled]),
            'extend' => $registration->update(['hold_expires_at' => now()->addHours(48)]),
        };

        return back()->with('status', 'Caso resuelto.');
    }

    private function doRefund(Registration $registration): void
    {
        foreach ($registration->payments as $payment) {
            if ($payment->status->value === 'paid') {
                try {
                    $this->refunds->refund($payment);
                } catch (\Throwable $e) {
                    // leave in queue if refund fails
                }
            }
        }
        $registration->update(['status' => RegistrationStatus::Cancelled]);
    }
}
