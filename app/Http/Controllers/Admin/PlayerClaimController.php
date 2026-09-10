<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlayerClaim;
use App\Services\Identity\PlayerClaimService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlayerClaimController extends Controller
{
    public function __construct(private PlayerClaimService $claims) {}

    /** Review queue — pending first, then recently reviewed. */
    public function index()
    {
        $pending = PlayerClaim::where('status', PlayerClaim::PENDING)
            ->with([
                'user',

                // Player as player 1
                'items.player.pairsAsPlayer1.category.tournament',
                'items.player.pairsAsPlayer1.player1',
                'items.player.pairsAsPlayer1.player2',

                // Player as player 2
                'items.player.pairsAsPlayer2.category.tournament',
                'items.player.pairsAsPlayer2.player1',
                'items.player.pairsAsPlayer2.player2',

                'items.player.creator',
            ])
            ->oldest()
            ->get();

        $recent = PlayerClaim::whereIn('status', [
            PlayerClaim::APPROVED,
            PlayerClaim::REJECTED,
        ])
            ->with([
                'user',
                'reviewer',
                'players',
            ])
            ->latest('reviewed_at')
            ->limit(25)
            ->get();

        return view('admin.claims.index', compact('pending', 'recent'));
    }

    public function approve(Request $request, PlayerClaim $claim)
    {
        try {
            $this->claims->approve($claim, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['claim' => $e->getMessage()]);
        }

        return back()->with('status', 'Solicitud aprobada y perfiles vinculados.');
    }

    public function reject(Request $request, PlayerClaim $claim)
    {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->claims->reject($claim, $request->user(), $data['review_note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Solicitud rechazada.');
    }
}
