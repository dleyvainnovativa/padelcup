<?php

namespace App\Http\Controllers;

use App\Models\RankingSystem;
use App\Services\Ranking\RankingLeaderboard;

/**
 * Phase 4 — public leaderboard for a ranking system. The AVP-facing payoff: a
 * shareable standings page across all tournaments that feed the system.
 *
 * A system is publicly viewable when it is active. (If you later want an explicit
 * is_public flag separate from is_active, add it to ranking_systems and check it
 * here instead.)
 */
class PublicRankingController extends Controller
{
    public function __construct(private RankingLeaderboard $leaderboard) {}

    /** GET r/{rankingSystem} */
    public function show(RankingSystem $rankingSystem)
    {
        abort_unless($rankingSystem->is_active, 404);

        $board = $this->leaderboard->forSystem($rankingSystem, withBreakdown: false);

        return view('public.rankings.show', [
            'system' => $rankingSystem,
            'board'  => $board,
        ]);
    }
}
