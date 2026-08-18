<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RankingSystem;
use App\Services\Ranking\RankingLeaderboard;
use Illuminate\Http\Request;

/**
 * Phase 4 — admin-side leaderboard for a ranking system. Scoped to the manager
 * who owns the system.
 */
class RankingLeaderboardController extends Controller
{
    public function __construct(private RankingLeaderboard $leaderboard) {}

    /** GET ranking-systems/{rankingSystem}/leaderboard */
    public function show(Request $request, RankingSystem $rankingSystem)
    {
        abort_unless($rankingSystem->created_by === $request->user()->id, 403);

        $board = $this->leaderboard->forSystem($rankingSystem, withBreakdown: true);

        return view('dashboard.rankings.leaderboard', [
            'system' => $rankingSystem,
            'board'  => $board,
        ]);
    }
}
