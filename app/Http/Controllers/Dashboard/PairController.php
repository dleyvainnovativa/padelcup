<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pair\StorePairRequest;
use App\Models\Category;
use App\Models\Pair;
use App\Models\Tournament;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\Request;

class PairController extends Controller
{
    public function __construct(private RegistrationService $registrations) {}

    public function store(StorePairRequest $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $this->registrations->createManagerPair(
            category: $category,
            player1: $request->player1Def(),
            player2: $request->player2Def(),
            manager: $request->user(),
            markPaid: (bool) $request->boolean('mark_paid'),
        );

        return back()->with('status', 'Pareja agregada.');
    }

    /** Toggle a pair's payment status (manager pay-later tracking). */
    public function setPayment(Request $request, Tournament $tournament, Category $category, Pair $pair)
    {
        $this->authorize('update', $category);
        abort_unless($pair->category_id === $category->id, 404);

        $status = PaymentStatus::from($request->input('payment_status'));
        $this->registrations->setPaymentStatus($pair->registration, $status);

        return back()->with('status', 'Pago actualizado.');
    }

    public function destroy(Tournament $tournament, Category $category, Pair $pair)
    {
        $this->authorize('update', $category);
        abort_unless($pair->category_id === $category->id, 404);

        // Before lock only (no confirmed results yet). Phase 6 enforces this fully.
        abort_if($tournament->isLocked(), 403, 'El torneo ya inició; usa retiro de pareja.');

        $this->registrations->removePair($pair);

        return back()->with('status', 'Pareja eliminada.');
    }
}
