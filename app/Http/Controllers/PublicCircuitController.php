<?php

namespace App\Http\Controllers;

use App\Models\RankingSystem;
use Illuminate\Http\Request;

/**
 * Public "Circuitos" = ranking systems presented as tournament series.
 * A circuit's tournaments are the ones linked via the ranking_system_tournament
 * pivot. Only active systems with at least one tournament are shown.
 */
class PublicCircuitController extends Controller
{
    /** GET /circuitos */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $circuits = RankingSystem::query()
            ->where('is_active', true)
            ->has('tournaments')                       // hide empty circuits
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->withCount('tournaments')
            ->orderBy('name')
            ->get();

        return view('public.circuits.index', [
            'circuits' => $circuits,
            'search'   => $search,
        ]);
    }

    /** GET /circuitos/{rankingSystem} */
    public function show(RankingSystem $rankingSystem)
    {
        abort_unless($rankingSystem->is_active, 404);

        // Linked tournaments, split into active/upcoming vs finished — mirrors
        // the public directory. Counts are eager-loaded for the cards.
        $tournaments = $rankingSystem->tournaments()
            ->withCount('categories')
            ->get();

        $today = now()->startOfDay();
        $active = $tournaments->filter(fn ($t) =>
            blank($t->ends_on) || $t->ends_on->gte($today)
        )->sortBy('starts_on')->values();
        $past = $tournaments->filter(fn ($t) =>
            filled($t->ends_on) && $t->ends_on->lt($today)
        )->sortByDesc('starts_on')->values();

        return view('public.circuits.show', [
            'system' => $rankingSystem,
            'active' => $active,
            'past'   => $past,
        ]);
    }
}
