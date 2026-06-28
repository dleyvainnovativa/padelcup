<?php

namespace App\Http\Controllers;

use App\Models\Pair;
use App\Models\Player;
use App\Models\Tournament;
use Illuminate\Http\Request;

class PublicSearchController extends Controller
{
    /**
     * Search players (and tournaments) across PUBLIC tournaments only.
     * Players are matched by normalized name and grouped, each linking to the
     * public player page within the tournament(s) they appear in.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $players = collect();
        $tournaments = collect();

        if (mb_strlen($q) >= 2) {
            $needle = Player::normalize($q);

            // Public (listed) tournament ids.
            $publicIds = Tournament::where('is_listed', true)->pluck('id');

            // Matching players within public tournaments, via their pairs.
            $pairs = Pair::whereHas('category', fn ($c) => $c->whereIn('tournament_id', $publicIds))
                ->with([
                    'category:id,name,tournament_id',
                    'category.tournament:id,name,slug,is_listed',
                    'player1:id,name',
                    'player2:id,name',
                ])
                ->get();

            $byName = []; // normalized => ['name', 'tournaments' => [slug => ['name','slug','player_id']]]
            foreach ($pairs as $pair) {
                $tournament = $pair->category?->tournament;
                if (! $tournament || ! $tournament->is_listed) continue;

                foreach ([$pair->player1, $pair->player2] as $p) {
                    if (! $p) continue;
                    $norm = Player::normalize($p->name);
                    if (! str_contains($norm, $needle)) continue;

                    $byName[$norm] ??= ['name' => $p->name, 'tournaments' => []];
                    $byName[$norm]['tournaments'][$tournament->slug] ??= [
                        'name' => $tournament->name,
                        'slug' => $tournament->slug,
                        'player_id' => $p->id,
                    ];
                }
            }

            $players = collect($byName)
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            // Also surface matching tournaments by name (handy combined search).
            $tournaments = Tournament::where('is_listed', true)
                ->where('name', 'like', '%' . $q . '%')
                ->orderByDesc('starts_on')
                ->limit(12)
                ->get(['id', 'name', 'slug', 'starts_on', 'ends_on']);
        }

        return view('public.search', [
            'q' => $q,
            'players' => $players,
            'tournaments' => $tournaments,
        ]);
    }
}
