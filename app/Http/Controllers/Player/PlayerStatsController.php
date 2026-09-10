<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Services\Player\PlayerDashboardService;
use Illuminate\Http\Request;

class PlayerStatsController extends Controller
{
    public function __construct(private PlayerDashboardService $dashboard) {}

    public function index(Request $request)
    {
        $user = $request->user();

        if (! $this->dashboard->hasProfile($user)) {
            return redirect()->route('player.dashboard');
        }

        return view('player.stats', [
            'stats' => $this->dashboard->stats($user),
        ]);
    }
}
