<?php

namespace App\Http\Controllers;

use App\Models\RankingSystem;
use App\Services\Ranking\RankingLeaderboard;
use Illuminate\Http\Request;

/**
 * Public leaderboard — tournament → category, plus cross-tournament summary.
 */
class PublicRankingController extends Controller
{
    public function __construct(private RankingLeaderboard $leaderboard) {}

    public function show(Request $request, RankingSystem $rankingSystem)
    {
        abort_unless($rankingSystem->is_active, 404);

        if ($request->query('view') === 'summary') {
            return view('public.rankings.summary', [
                'system'     => $rankingSystem,
                'categories' => $this->leaderboard->categories($rankingSystem),
                'combined'   => $this->leaderboard->forSystem($rankingSystem),
                'byCategory' => $this->leaderboard->byCategory($rankingSystem),
            ]);
        }

        $tournaments = $this->leaderboard->tournaments($rankingSystem);

        $tParam = $request->query('tournament', 'all');
        $tId = ($tParam === 'all' || $tParam === '') ? null : (int) $tParam;
        if ($tId && ! $tournaments->firstWhere('id', $tId)) $tId = null;
        $tLabel = $tId ? ($tournaments->firstWhere('id', $tId)['name'] ?? null) : null;

        $categories = $this->leaderboard->categories($rankingSystem, $tId);

        $cat = $request->query('cat', 'all');
        [$board, $catLabel] = $this->resolveBoard($rankingSystem, $cat, $tId);

        return view('public.rankings.show', [
            'system'          => $rankingSystem,
            'board'           => $board,
            'tournaments'     => $tournaments,
            'categories'      => $categories,
            'activeTour'      => $tId ? (string) $tId : 'all',
            'activeTourLabel' => $tLabel,
            'activeCat'       => $cat,
            'activeLabel'     => $catLabel,
        ]);
    }

    private function resolveBoard(RankingSystem $system, string $cat, ?int $tId): array
    {
        if ($cat === 'all' || $cat === '') {
            return [$this->leaderboard->forSystem($system, false, $tId), 'General'];
        }
        $entry = $this->leaderboard->byCategory($system, false, $tId)->get($cat);
        return $entry ? [$entry['board'], $entry['label']]
            : [$this->leaderboard->forSystem($system, false, $tId), 'General'];
    }
}
