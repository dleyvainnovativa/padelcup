<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Pair;
use App\Models\Player;
use Illuminate\Http\Request;

/**
 * Topbar search for the manager dashboard. Scoped strictly to the signed-in
 * manager's OWN tournaments (via User::tournaments / manager_id) — a manager
 * never sees another manager's tournaments, categories or players.
 *
 * Returns JSON grouped by type for the live dropdown.
 */
class DashboardSearchController extends Controller
{
    private const PER_GROUP = 6;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $out = ['tournaments' => [], 'categories' => [], 'players' => [], 'q' => $q];

        if (mb_strlen($q) < 2) {
            return response()->json($out);
        }

        $manager = $request->user();
        $tids = $manager->tournaments()->pluck('tournaments.id');
        if ($tids->isEmpty()) {
            return response()->json($out);
        }

        $like = '%' . $q . '%';

        // --- Tournaments (the manager's own) ---
        $out['tournaments'] = $manager->tournaments()
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['tournaments.id', 'tournaments.name'])
            ->map(fn ($t) => [
                'name' => $t->name,
                'url' => route('tournaments.show', $t->id),
            ])->values();

        // --- Categories within those tournaments ---
        $out['categories'] = Category::whereIn('tournament_id', $tids)
            ->where('name', 'like', $like)
            ->with('tournament:id,name')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'tournament_id'])
            ->map(fn ($c) => [
                'name' => $c->name,
                'tournament' => $c->tournament?->name,
                'url' => route('categories.show', [$c->tournament_id, $c->id]),
            ])->values();

        // --- Players appearing in those tournaments (via their pairs) ---
        $needle = Player::normalize($q);
        $pairs = Pair::whereHas('category', fn ($c) => $c->whereIn('tournament_id', $tids))
            ->where(function ($w) use ($like) {
                $w->whereHas('player1', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('player2', fn ($p) => $p->where('name', 'like', $like));
            })
            ->with([
                'category:id,name,tournament_id',
                'category.tournament:id,name',
                'player1:id,name',
                'player2:id,name',
            ])
            ->limit(40)
            ->get();

        $players = [];
        foreach ($pairs as $pair) {
            $cat = $pair->category;
            if (! $cat) {
                continue;
            }
            foreach ([$pair->player1, $pair->player2] as $p) {
                if (! $p || ! str_contains(Player::normalize($p->name), $needle)) {
                    continue;
                }
                $key = Player::normalize($p->name) . '|' . $cat->id;
                if (isset($players[$key])) {
                    continue;
                }
                $players[$key] = [
                    'name' => $p->name,
                    'category' => $cat->name,
                    'tournament' => $cat->tournament?->name,
                    'url' => route('categories.show', [$cat->tournament_id, $cat->id]),
                ];
                if (count($players) >= self::PER_GROUP) {
                    break 2;
                }
            }
        }
        $out['players'] = array_values($players);

        return response()->json($out);
    }
}
