<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\Player\PlayerDashboardService;
use Illuminate\Http\Request;

class PlayerDashboardController extends Controller
{
    public function __construct(private PlayerDashboardService $dashboard) {}

    /** The player's personal dashboard. */
    public function index(Request $request)
    {
        $user = $request->user();

        // No linked players yet → send them to claim their profile.
        if (! $this->dashboard->hasProfile($user)) {
            return view('player.dashboard', [
                'hasProfile' => false,
                'upcoming' => collect(),
                'results' => collect(),
                'tournaments' => collect(),
                'standings' => $this->dashboard->standings($user),

            ]);
        }

        return view('player.dashboard', [
            'hasProfile' => true,
            'upcoming' => $this->dashboard->upcoming($user),
            'results' => $this->dashboard->results($user),
            'tournaments' => $this->dashboard->tournaments($user),
            'standings' => $this->dashboard->standings($user),
            'predictions' => \App\Models\MatchPrediction::where('user_id', $user->id)
                ->with(['match.pairA', 'match.pairB', 'tournament:id,name,slug'])
                ->latest()->limit(10)->get(),
        ]);
    }
}