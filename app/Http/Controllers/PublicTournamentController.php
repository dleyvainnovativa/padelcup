<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\Tournament\BracketService;
use App\Services\Tournament\StandingsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, read-only tournament pages (Phase 8). No auth. Only tournaments with
 * is_listed = true are visible; everything else 404s. Reuses the standings and
 * bracket services; presentation strips all interactive/admin controls.
 */
class PublicTournamentController extends Controller
{
    public function __construct(
        private StandingsService $standings,
        private BracketService $brackets,
    ) {}

    /** Public directory: all listed tournaments, active/upcoming then past. */
    public function directory(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $query = Tournament::where('is_listed', true);
        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $all = $query->withCount('categories')->orderByDesc('starts_on')->get();
        $today = today('America/Mexico_City');

        // Active/upcoming = ends on/after today (or no end date); past otherwise.
        [$active, $past] = $all->partition(function ($t) use ($today) {
            $end = $t->ends_on ?? $t->starts_on;
            return $end === null || $end->greaterThanOrEqualTo($today);
        });

        // Active sorted soonest-first; past most-recent-first.
        $active = $active->sortBy(fn($t) => $t->starts_on?->timestamp ?? PHP_INT_MAX)->values();
        $past = $past->values();



        return view('public.directory', [
            'active' => $active,
            'past' => $past,
            'search' => $search,
        ]);
    }
    public function landing(Request $request)
    {
        return view('public.landing', []);
    }

    /** Tournament overview: status + category cards. */
    public function show(Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        $tournament->loadCount('categories');
        $categories = $tournament->categories()->withCount('pairs')->orderBy('name')->get();
        $sponsors = \App\Models\Sponsor::forTournament($tournament);

        return view('public.tournament', [
            'tournament' => $tournament,
            'categories' => $categories,
            'sponsors' => $sponsors,
            'ads' => \App\Models\Ad::forTournament($tournament),
        ]);
    }

    /** Category page: calendar / standings (per-group + general) / bracket / results. */
    public function category(Request $request, Tournament $tournament, Category $category)
    {
        $this->ensurePublic($tournament);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $category->load(['groups.pairs.player1', 'groups.pairs.player2']);

        // --- Calendar (this category only) ------------------------------------
        // All of this category's matches that have real pairs, with day + player
        // search filters. Scheduled matches group by day; unscheduled ones fall
        // into a "Sin programar" bucket shown at the end.
        $calSearch = trim((string) $request->query('q', ''));
        $calDay = $request->query('day'); // Y-m-d

        $calMatches = $category->matches()
            ->with(['court', 'group', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->orderBy('starts_at')
            ->orderBy('round')->orderBy('slot')->orderBy('id')
            ->get();

        // Day options come from scheduled matches (before narrowing by day).
        $calAllDays = $calMatches
            ->filter(fn($m) => $m->starts_at)
            ->map(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'))
            ->unique()->values();

        // Player search: keep matches whose pair/player names contain the needle,
        // and collect the distinct matched players for quick profile links.
        $calMatchedPlayers = collect();
        if ($calSearch !== '') {
            $needle = mb_strtolower($calSearch);
            $calMatches = $calMatches->filter(function ($m) use ($needle) {
                foreach ([$m->pairA, $m->pairB] as $pair) {
                    if (! $pair) continue;
                    if (str_contains(mb_strtolower($pair->name()), $needle)) return true;
                    foreach ([$pair->player1, $pair->player2] as $p) {
                        if ($p && str_contains(mb_strtolower($p->name), $needle)) return true;
                    }
                }
                return false;
            })->values();

            foreach ($calMatches as $m) {
                foreach ([$m->pairA, $m->pairB] as $pair) {
                    if (! $pair) continue;
                    foreach ([$pair->player1, $pair->player2] as $p) {
                        if ($p && str_contains(mb_strtolower($p->name), $needle)) {
                            $calMatchedPlayers->put($p->id, $p);
                        }
                    }
                }
            }
            $calMatchedPlayers = $calMatchedPlayers->values();
        }

        // Split scheduled vs unscheduled; apply the day filter to scheduled ones.
        $calScheduled = $calMatches->filter(fn($m) => $m->starts_at);
        $calUnscheduled = $calMatches->filter(fn($m) => ! $m->starts_at)->values();

        if ($calDay) {
            $calScheduled = $calScheduled->filter(
                fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d') === $calDay
            );
        }

        $calByDay = $calScheduled
            ->groupBy(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'));

        $calTotal = $calScheduled->count() + $calUnscheduled->count();

        // Per-group standings.
        $groups = $category->groups->map(function ($group) {
            $rows = $this->standings->forGroup($group);
            return [
                'name' => $group->name,
                'rows' => $rows->map(function ($r) use ($group) {
                    $pair = $group->pairs->firstWhere('id', $r['pair_id']);
                    return array_merge($r, ['pair_name' => $pair?->name() ?? '—']);
                })->values(),
            ];
        });

        // Combined "general" ranking.
        $combined = collect();
        foreach ($groups as $g) {
            foreach ($g['rows'] as $pos => $r) {
                $combined->push(array_merge($r, ['group_name' => $g['name'], 'group_pos' => $pos + 1]));
            }
        }
        $combined = $combined->sort(
            fn($a, $b) =>
            [$a['group_pos'], -$a['points'], -$a['game_diff']] <=> [$b['group_pos'], -$b['points'], -$b['game_diff']]
        )->values();

        // Qualifier ids.
        $qualifierIds = [];
        if ($category->format->hasBracket() && $category->format->hasGroups()) {
            try {
                $qualifierIds = $this->brackets->qualifiers($category)['qualifiers'] ?? [];
            } catch (\Throwable $e) {
                $qualifierIds = [];
            }
        }

        // Bracket matches (read-only tree).
        $bracketMatches = collect();
        if ($category->format->hasBracket()) {
            $bracketMatches = $category->matches()
                ->whereNull('group_id')
                ->with(['pairA', 'pairB'])
                ->orderBy('round')->orderBy('slot')
                ->get()
                ->groupBy('round');
        }

        // Results (group matches, confirmed + pending).
        $groupResults = $category->matches()
            ->whereNotNull('group_id')
            ->with(['group', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->orderBy('round')->orderBy('slot')->orderBy('id')
            ->get()
            ->groupBy('group_id')
            ->sortBy(fn($matches) => $matches->first()->group?->name ?? '', SORT_NATURAL);

        // Elimination results (played bracket matches) for the results tab,
        // grouped by round so each phase has its own section.
        $bracketResults = collect();
        if ($category->format->hasBracket()) {
            $bracketResults = $category->matches()
                ->whereNull('group_id')
                ->where('state', 'confirmed')
                ->with(['court', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
                ->orderBy('round')->orderBy('slot')->orderBy('id')
                ->get()
                ->groupBy('round');
        }

        $ghostQualifiers = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->mapFor($category);

        $groupTags = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->groupTagsFor($category);

        return view('public.category', [
            'tournament' => $tournament,
            'category' => $category,
            'groups' => $groups,
            'combined' => $combined,
            'qualifierIds' => $qualifierIds,
            'bracketMatches' => $bracketMatches,
            'groupResults' => $groupResults,
            'bracketResults' => $bracketResults,
            'ads' => \App\Models\Ad::forTournament($tournament),
            // Calendar tab
            'calByDay' => $calByDay,
            'calUnscheduled' => $calUnscheduled,
            'calAllDays' => $calAllDays,
            'calSearch' => $calSearch,
            'calDay' => $calDay,
            'calMatchedPlayers' => $calMatchedPlayers,
            'calTotal' => $calTotal,
            'ghostQualifiers' => $ghostQualifiers,
            'groupTags' => $groupTags,
        ]);
    }

    /** Public read-only schedule, with optional "buscar mi partido" filter. */
    public function schedule(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        $search = trim((string) $request->query('q', ''));
        $categoryFilter = $request->query('cat'); // category id
        $dayFilter = $request->query('day');      // Y-m-d

        $matches = $tournament->categories()
            ->with(['matches' => function ($q) {
                $q->whereNotNull('starts_at')
                    ->with(['court', 'category', 'group', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
                    ->orderBy('starts_at');
            }])
            ->get()
            ->flatMap->matches
            ->sortBy('starts_at')
            ->values();

        // Filter options (before narrowing).
        $allCategories = $tournament->categories()->orderBy('name')->get(['id', 'name']);
        $allDays = $matches->map(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'))->unique()->values();

        // Apply category filter.
        if ($categoryFilter) {
            $matches = $matches->where('category_id', (int) $categoryFilter)->values();
        }
        // Apply day filter.
        if ($dayFilter) {
            $matches = $matches->filter(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d') === $dayFilter)->values();
        }

        // "Buscar mi partido": filter to matches involving a player/pair name.
        $matchedPlayers = collect();
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $matches = $matches->filter(function ($m) use ($needle) {
                $names = [];
                foreach ([$m->pairA, $m->pairB] as $pair) {
                    if (! $pair) continue;
                    $names[] = mb_strtolower($pair->name());
                    foreach ([$pair->player1, $pair->player2] as $p) {
                        if ($p) $names[] = mb_strtolower($p->name);
                    }
                }
                foreach ($names as $n) {
                    if (str_contains($n, $needle)) return true;
                }
                return false;
            })->values();

            // Collect the distinct players whose name matched (for quick links).
            foreach ($matches as $m) {
                foreach ([$m->pairA, $m->pairB] as $pair) {
                    if (! $pair) continue;
                    foreach ([$pair->player1, $pair->player2] as $p) {
                        if ($p && str_contains(mb_strtolower($p->name), $needle)) {
                            $matchedPlayers->put($p->id, $p);
                        }
                    }
                }
            }
            $matchedPlayers = $matchedPlayers->values();
        }

        // Group by day for display.
        $byDay = $matches->groupBy(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'));
        $ghostQualifiers = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->mapForTournament($tournament);
        return view('public.schedule', [
            'tournament' => $tournament,
            'byDay' => $byDay,
            'search' => $search,
            'total' => $matches->count(),
            'matchedPlayers' => $matchedPlayers,
            'allCategories' => $allCategories,
            'allDays' => $allDays,
            'categoryFilter' => $categoryFilter,
            'dayFilter' => $dayFilter,
            'ghostQualifiers' => $ghostQualifiers,
        ]);
    }

    /** Public player page: their matches, results, and stats in this tournament. */
    public function player(Tournament $tournament, \App\Models\Player $player)
    {
        $this->ensurePublic($tournament);

        // All pairs this player belongs to within THIS tournament.
        $pairIds = \App\Models\Pair::where(function ($q) use ($player) {
            $q->where('player1_id', $player->id)->orWhere('player2_id', $player->id);
        })
            ->whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->pluck('id');

        abort_if($pairIds->isEmpty(), Response::HTTP_NOT_FOUND);



        // Matches involving any of those pairs.
        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->where(fn($q) => $q->whereIn('pair_a_id', $pairIds)->orWhereIn('pair_b_id', $pairIds))
            ->with(['category', 'group', 'court', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->orderBy('starts_at')
            ->get();

        // Stats: played / won / lost, sets won-lost.
        $played = 0;
        $won = 0;
        $setsWon = 0;
        $setsLost = 0;
        foreach ($matches as $m) {
            if ($m->state->value !== 'confirmed') continue;
            $mine = in_array($m->pair_a_id, $pairIds->all()) ? 'a' : 'b';
            $myPairId = $mine === 'a' ? $m->pair_a_id : $m->pair_b_id;
            $played++;
            if ($m->winner_pair_id === $myPairId) $won++;
            foreach ($m->sets ?? [] as $s) {
                $setsWon += $mine === 'a' ? ($s[0] ?? 0) : ($s[1] ?? 0);
                $setsLost += $mine === 'a' ? ($s[1] ?? 0) : ($s[0] ?? 0);
            }
        }

        // Categories the player is registered in.
        $categories = \App\Models\Category::whereIn(
            'id',
            \App\Models\Pair::whereIn('id', $pairIds)->pluck('category_id')
        )->get();

        // Projected knockout matches: for each category the player is in, resolve the
        // ghost map; find bracket matches where an unbound side's seed label resolves
        // to one of the player's pairs. Shown as provisional until real binding.
        $ghost = app(\App\Services\Tournament\GhostQualifierResolver::class);
        $projectedMatches = [];

        // Names of this player's pairs (to match against resolved ghost names).
        $myPairNames = \App\Models\Pair::with(['player1', 'player2'])
            ->whereIn('id', $pairIds)
            ->get()
            ->mapWithKeys(fn($p) => [$p->id => $p->name()]);

        foreach ($categories as $category) {
            if (! $category->format->hasBracket()) continue;
            $map = $ghost->mapForCached($category); // [seedLabel => pairName]
            if (empty($map)) continue;

            // Which seed labels resolve to one of MY pair names?
            $myLabels = [];
            foreach ($map as $label => $pairName) {
                if ($myPairNames->contains($pairName)) $myLabels[] = $label;
            }
            if (empty($myLabels)) continue;

            // Bracket matches in this category with an unbound side carrying one of
            // those labels — but only where the pair isn't already bound (still a
            // real projection, not an actual scheduled match).
            // $bracket = \App\Models\GameMatch::where('category_id', $category->id)
            //     ->whereNull('group_id')
            //     ->whereNull('pair_a_id')->orWhereNull('pair_b_id')
            //     ->where('category_id', $category->id)   // keep scope after orWhere
            //     ->whereNull('group_id')
            //     ->with(['pairA', 'pairB'])
            //     ->get();

            $bracket = \App\Models\GameMatch::where('category_id', $category->id)
                ->whereNull('group_id')
                ->where(fn($q) => $q->whereNull('pair_a_id')->orWhereNull('pair_b_id'))
                ->with(['pairA', 'pairB'])
                ->get();

            foreach ($bracket as $m) {
                $aLabel = $m->seed_label_a;
                $bLabel = $m->seed_label_b;
                $mineOnA = $aLabel && in_array($aLabel, $myLabels, true) && ! $m->pair_a_id;
                $mineOnB = $bLabel && in_array($bLabel, $myLabels, true) && ! $m->pair_b_id;
                if (! $mineOnA && ! $mineOnB) continue;

                // Resolve both sides for display (ghost name if available, else label text).
                $aName = $m->ghostFor('a', $map) ?? $m->sideLabel('a');
                $bName = $m->ghostFor('b', $map) ?? $m->sideLabel('b');

                $projectedMatches[] = [
                    'category' => $category->name,
                    'round' => $m->bracketRoundName(),
                    'a' => $aName,
                    'b' => $bName,
                ];
            }
        }

        $subsIn = \App\Models\PlayerSubstitution::with(['oldPlayer', 'newPlayer', 'category'])
            ->where('tournament_id', $tournament->id)
            ->where('new_player_id', $player->id)   // this player came IN
            ->get();
        $subsOut = \App\Models\PlayerSubstitution::with(['oldPlayer', 'newPlayer', 'category'])
            ->where('tournament_id', $tournament->id)
            ->where('old_player_id', $player->id)    // this player was replaced
            ->get();

        // Upcoming (scheduled, not yet played) vs past.
        $now = now('America/Mexico_City');
        $upcoming = $matches->filter(fn($m) => $m->starts_at && $m->starts_at->gt($now) && $m->state->value !== 'confirmed')->values();

        return view('public.player', [
            'tournament' => $tournament,
            'player' => $player,
            'matches' => $matches,
            'categories' => $categories,
            'upcoming' => $upcoming,
            'pairIds' => $pairIds->all(),
            'projectedMatches' => $projectedMatches,
            'stats' => [
                'played' => $played,
                'won' => $won,
                'lost' => $played - $won,
                'setsWon' => $setsWon,
                'setsLost' => $setsLost,
            ],
            'subsIn' => $subsIn,
            'subsOut' => $subsOut,
        ]);
    }

    /** 404 unless the tournament is publicly listed. */
    private function ensurePublic(Tournament $tournament): void
    {
        abort_unless((bool) $tournament->is_listed, Response::HTTP_NOT_FOUND);
    }
}
