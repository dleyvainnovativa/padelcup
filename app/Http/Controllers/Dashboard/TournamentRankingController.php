<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RankingSystem;
use App\Models\Tournament;
use App\Services\Ranking\RankingFinalizer;
use Illuminate\Http\Request;

/**
 * Phase 3 — finalize a tournament's ranking points for one system.
 *
 * Writes the ledger and stamps finalized_at. Idempotent: re-running recomputes.
 * A separate "revert" action clears the slice and un-stamps, in case an organizer
 * finalized too early.
 */
class TournamentRankingController extends Controller
{
    public function __construct(private RankingFinalizer $finalizer) {}

    /**
     * POST tournaments/{tournament}/rankings/{rankingSystem}/finalize
     */
    public function finalize(Request $request, Tournament $tournament, RankingSystem $rankingSystem)
    {
        $this->authorize('update', $tournament);
        // The system must belong to this manager too.
        abort_unless($rankingSystem->created_by === $request->user()->id, 403);

        $summary = $this->finalizer->finalize($tournament, $rankingSystem);

        $msg = "Ranking «{$rankingSystem->name}» finalizado: "
            . "{$summary['rows']} registros para {$summary['players']} jugadores "
            . "en {$summary['categories']} categoría(s).";

        return back()->with('status', $msg);
    }

    /**
     * POST tournaments/{tournament}/rankings/{rankingSystem}/revert
     * Clears this system's ledger slice for the tournament and un-stamps the
     * pivot, so it can be finalized again later.
     */
    public function revert(Request $request, Tournament $tournament, RankingSystem $rankingSystem)
    {
        $this->authorize('update', $tournament);
        abort_unless($rankingSystem->created_by === $request->user()->id, 403);

        \App\Models\RankingPoint::where('ranking_system_id', $rankingSystem->id)
            ->where('tournament_id', $tournament->id)
            ->delete();

        $tournament->rankingSystems()->updateExistingPivot($rankingSystem->id, [
            'finalized_at' => null,
        ]);

        return back()->with('status', "Ranking «{$rankingSystem->name}» revertido. Puedes volver a finalizarlo.");
    }
}
