<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Models\PlayerClaim;
use App\Services\Identity\PlayerClaimService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlayerClaimController extends Controller
{
    public function __construct(private PlayerClaimService $claims) {}

    /** The claim search + form. */
    public function create(Request $request)
    {
        $results = collect();
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $results = $this->claims->search($q);
        }

        return view('player.claim', [
            'q' => $q,
            'results' => $results,
        ]);
    }

    /** Submit a claim for selected player-id groups. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'player_ids' => ['required', 'array', 'min:1'],
            'player_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->claims->submit($request->user(), $data['player_ids'], $data['note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('player.claims')
            ->with('status', 'Tu solicitud fue enviada. Un administrador la revisará pronto.');
    }

    /** The player's own claim requests + status. */
    public function index(Request $request)
    {
        $claims = PlayerClaim::where('user_id', $request->user()->id)
            ->with('players')
            ->latest()
            ->get();

        return view('player.claims', compact('claims'));
    }
}
