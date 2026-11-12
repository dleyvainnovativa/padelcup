<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Pair;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\Request;

/**
 * Topbar search for the manager dashboard. Scoped strictly to the signed-in
 * manager's OWN tournaments (via User::tournaments / manager_id) — a manager
 * never sees another manager's tournaments, categories or players.
 *
 * When the request carries the tournament the manager is currently viewing
 * (?t=<slug>), that tournament's matching categories + players are returned in
 * a separate `current` block so the dropdown can surface them first; those same
 * rows are then excluded from the global groups to avoid listing them twice.
 */
class DashboardSearchController extends Controller
{
    private const PER_GROUP = 6;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $out = ['current' => null, 'tournaments' => [], 'categories' => [], 'players' => [], 'q' => $q];

        if (mb_strlen($q) < 2) {
            return response()->json($out);
        }

        $manager = $request->user();
        $tids = $manager->tournaments()->pluck('tournaments.id');
        if ($tids->isEmpty()) {
            return response()->json($out);
        }

        $like = '%' . $q . '%';
        $needle = Player::normalize($q);

        // Tournament the manager is currently on (must be one of theirs).
        $current = $request->filled('t')
            ? $manager->tournaments()->where('slug', $request->query('t'))->first()
            : null;

        // --- Tournaments (the manager's own) ---
        // Pass the model to route() so it uses the slug (Tournament::getRouteKeyName).
        $out['tournaments'] = $manager->tournaments()
            ->where('name', 'like', $like)
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['tournaments.id', 'tournaments.name', 'tournaments.slug'])
            ->map(fn ($t) => [
                'name' => $t->name,
                'url' => route('tournaments.show', $t),
            ])->values();

        // Split the category/player scope: the current tournament first, the rest
        // (its matches excluded) in the global groups.
        if ($current) {
            $out['current'] = [
                'name' => $current->name,
                'categories' => $this->categoriesIn([$current->id], $like),
                'players' => $this->playersIn([$current->id], $like, $needle),
            ];
            $otherTids = $tids->reject(fn ($id) => (int) $id === (int) $current->id)->values()->all();
        } else {
            $otherTids = $tids->all();
        }

        $out['categories'] = $this->categoriesIn($otherTids, $like);
        $out['players'] = $this->playersIn($otherTids, $like, $needle);

        return response()->json($out);
    }

    /** Matching categories within the given tournament ids (slug-based URLs). */
    private function categoriesIn(array $tids, string $like): array
    {
        if (empty($tids)) {
            return [];
        }

        return Category::whereIn('tournament_id', $tids)
            ->where('name', 'like', $like)
            ->with('tournament:id,name,slug')
            ->orderBy('name')
            ->limit(self::PER_GROUP)
            ->get(['id', 'name', 'tournament_id'])
            ->map(fn ($c) => $c->tournament ? [
                'name' => $c->name,
                'tournament' => $c->tournament->name,
                'url' => route('categories.show', [$c->tournament, $c->id]),
            ] : null)
            ->filter()
            ->values()
            ->all();
    }

    /** Matching players within the given tournament ids, via their pairs. */
    private function playersIn(array $tids, string $like, string $needle): array
    {
        if (empty($tids)) {
            return [];
        }

        $pairs = Pair::whereHas('category', fn ($c) => $c->whereIn('tournament_id', $tids))
            ->where(function ($w) use ($like) {
                $w->whereHas('player1', fn ($p) => $p->where('name', 'like', $like))
                    ->orWhereHas('player2', fn ($p) => $p->where('name', 'like', $like));
            })
            ->with([
                'category:id,name,tournament_id',
                'category.tournament:id,name,slug',
                'player1:id,name',
                'player2:id,name',
            ])
            ->limit(40)
            ->get();

        $players = [];
        foreach ($pairs as $pair) {
            $cat = $pair->category;
            if (! $cat || ! $cat->tournament) {
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
                    'tournament' => $cat->tournament->name,
                    'url' => route('categories.show', [$cat->tournament, $cat->id]),
                ];
                if (count($players) >= self::PER_GROUP) {
                    break 2;
                }
            }
        }

        return array_values($players);
    }
}
