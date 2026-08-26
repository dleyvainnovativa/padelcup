<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\RankingSystem;
use App\Services\Ranking\RankingLeaderboard;
use Illuminate\Http\Request;

/**
 * Admin leaderboard — filter hierarchy: tournament → category.
 *
 * ?tournament=<id>  → scope to one tournament (then ?cat filters its categories)
 * ?tournament=all   → combined across tournaments; categories merge by key
 * ?cat=<key>|all    → category within the current tournament scope
 * ?view=summary     → cross-tournament merged summary (ignores tournament filter)
 */
class RankingLeaderboardController extends Controller
{
    public function __construct(private RankingLeaderboard $leaderboard) {}

    public function show(Request $request, RankingSystem $rankingSystem)
    {
        abort_unless($rankingSystem->created_by === $request->user()->id, 403);

        // Summary is always the cross-tournament, category-merged view.
        if ($request->query('view') === 'summary') {
            return view('dashboard.rankings.summary', [
                'system'     => $rankingSystem,
                'categories' => $this->leaderboard->categories($rankingSystem),
                'combined'   => $this->leaderboard->forSystem($rankingSystem),
                'byCategory' => $this->leaderboard->byCategory($rankingSystem),
                'isPublic'   => false,
            ]);
        }

        $tournaments = $this->leaderboard->tournaments($rankingSystem);

        // Resolve tournament scope.
        $tParam = $request->query('tournament', 'all');
        $tId = ($tParam === 'all' || $tParam === '') ? null : (int) $tParam;
        // Guard: if a bad id is passed, fall back to all.
        if ($tId && ! $tournaments->firstWhere('id', $tId)) $tId = null;
        $tLabel = $tId ? ($tournaments->firstWhere('id', $tId)['name'] ?? null) : null;

        // Categories are scoped to the chosen tournament.
        $categories = $this->leaderboard->categories($rankingSystem, $tId);

        // Resolve category within that scope.
        $cat = $request->query('cat', 'all');
        [$board, $catLabel] = $this->resolveBoard($rankingSystem, $cat, $tId);

        return view('dashboard.rankings.leaderboard', [
            'system'       => $rankingSystem,
            'board'        => $board,
            'tournaments'  => $tournaments,
            'categories'   => $categories,
            'activeTour'   => $tId ? (string) $tId : 'all',
            'activeTourLabel' => $tLabel,
            'activeCat'    => $cat,
            'activeLabel'  => $catLabel,
            'isPublic'     => false,
        ]);
    }

    private function resolveBoard(RankingSystem $system, string $cat, ?int $tId): array
    {
        if ($cat === 'all' || $cat === '') {
            return [$this->leaderboard->forSystem($system, true, $tId), 'General'];
        }
        $entry = $this->leaderboard->byCategory($system, true, $tId)->get($cat);
        return $entry ? [$entry['board'], $entry['label']]
            : [$this->leaderboard->forSystem($system, true, $tId), 'General'];
    }
}
