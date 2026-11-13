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
        $categories = $tournament->categories()->publicVisible()->withCount('pairs')->orderBy('name')->get();
        $sponsors = \App\Models\Sponsor::forTournament($tournament);

        // Mesa de control: tournament-wide running delay (minutes), if any.
        $scheduleDelay = app(\App\Services\Tournament\ControlTableService::class)->delay($tournament);

        return view('public.tournament', [
            'tournament' => $tournament,
            'categories' => $categories,
            'sponsors' => $sponsors,
            'ads' => \App\Models\Ad::forTournament($tournament),
            'scheduleDelay' => $scheduleDelay,
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
        $calGroup = $request->query('group'); // group name, e.g. "Grupo A"
        $calPhase = $request->query('phase'); // publicPhase key, e.g. "groups", "final"
        $calVenue = $request->query('venue'); // venue id (only relevant with 2+ venues)

        $calMatches = $category->matches()
            ->with([
                'court.venue',
                'group',
                'pairA.player1',
                'pairA.player2',
                'pairB.player1',
                'pairB.player2',
                'feederA.pairA.player1',
                'feederA.pairA.player2',
                'feederA.pairB.player1',
                'feederA.pairB.player2',
                'feederB.pairA.player1',
                'feederB.pairA.player2',
                'feederB.pairB.player1',
                'feederB.pairB.player2'
            ])
            ->orderBy('starts_at')
            ->orderBy('round')->orderBy('slot')->orderBy('id')
            ->get();

        // Chip options (computed from the full set, before any narrowing): the
        // group names present and the phases present in this category.
        $calGroupNames = $category->groups->pluck('name')->values();
        $calPhasesPresent = \App\Support\SchedulePhase::presentIn($calMatches);

        // Venues present in this category's matches (court → venue). Chips only
        // render when there are 2+, so a single-venue tournament is unaffected.
        $calVenuesPresent = $calMatches
            ->map(fn($m) => $m->court?->venue)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn($v) => ['id' => $v->id, 'name' => $v->name])
            ->values();

        // Day options come from scheduled matches (before narrowing by day).
        $calAllDays = $calMatches
            ->filter(fn($m) => $m->starts_at)
            ->map(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'))
            ->unique()->values();

        // #6: default to TODAY when no day is chosen and today has matches in this
        // category. "all" is the explicit "Todos" sentinel → show every day.
        // $calDayParam keeps the RAW value so URLs preserve the explicit choice.
        $calDayParam = $calDay;
        $calToday = \Carbon\Carbon::now('America/Mexico_City')->format('Y-m-d');
        if ($calDay === 'all') {
            $calDay = null;
        } elseif (($calDay === null || $calDay === '') && $calAllDays->contains($calToday)) {
            $calDay = $calToday;
        }

        // Player search: keep matches whose pair/player names contain the needle,
        // INCLUDING projected participants (feeder "Ganador/Perdedor (…)" and
        // ghost qualifiers), so a player finds round-2 slots they may reach.
        $calMatchedPlayers = collect();
        if ($calSearch !== '') {
            $needle = mb_strtolower($calSearch);

            // Ghost map for this category, wrapped by category_id for the helper.
            $ghostMap = [$category->id => app(\App\Services\Tournament\GhostQualifierResolver::class)->mapFor($category)];

            $calMatches = $calMatches->filter(function ($m) use ($needle, $ghostMap) {
                return str_contains($m->searchableNames($ghostMap), $needle);
            })->values();

            // Collect matched BOUND players for quick profile links (projected
            // players have no bound Player row to link yet).
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

        // Group + phase filters (compose with search and day).
        if ($calGroup) {
            $calMatches = $calMatches->filter(fn($m) => $m->group && $m->group->name === $calGroup)->values();
        }
        if ($calPhase) {
            $calMatches = $calMatches->filter(fn($m) => $m->publicPhase()['key'] === $calPhase)->values();
        }
        if ($calVenue) {
            $calMatches = $calMatches->filter(fn($m) => $m->court?->venue?->id === (int) $calVenue)->values();
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

        // Combined "general" ranking — a true overall table ordered by the
        // tournament's configured tiebreak criteria (same cross-group logic the
        // qualifier binder uses), not by group position. Head-to-head is skipped
        // across groups. pair_name/group_name are preserved through the sort.
        $combined = collect();
        foreach ($groups as $g) {
            foreach ($g['rows'] as $pos => $r) {
                $combined->push(array_merge($r, ['group_name' => $g['name'], 'group_pos' => $pos + 1]));
            }
        }
        $tbOrder = \App\Support\TiebreakCriteria::sanitize($tournament->tiebreak_order ?? null);
        $combined = collect($this->brackets->rankRowsCrossGroup($combined, $tbOrder))->values();

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
                ->with(['court.venue', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
                ->orderBy('round')->orderBy('slot')->orderBy('id')
                ->get()
                ->groupBy('round');
        }

        $ghostQualifiers = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->mapFor($category);

        $groupTags = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->groupTagsFor($category);

        // Cross-table ("cruces") data per group: for each ordered pair (row,col),
        // their head-to-head match — score from the ROW pair's perspective, plus
        // whether the row pair won. Also each pair's UPCOMING crosses + times.
        $crossTables = $category->groups->map(function ($group) use ($tournament) {
            $group->loadMissing('pairs');
            // Confirmed + scheduled matches within this group.
            $matches = \App\Models\GameMatch::where('group_id', $group->id)
                ->with(['court.venue'])
                ->get();

            // Order pairs as they appear in standings (so row/col numbers match the
            // standings table the coaches read).
            $ordered = $this->standings->forGroup($group)
                ->map(fn($r) => $r['pair_id'])->values();
            // Fallback: if standings empty, use the group's pairs order.
            if ($ordered->isEmpty()) $ordered = $group->pairs->pluck('id');

            $pairsById = $group->pairs->keyBy('id');
            $index = $ordered->flip(); // [pair_id => 0-based position]

            // Build the cell lookup: cells[rowPairId][colPairId] = [...]
            $cells = [];
            // Each pair's FULL list of crosses (played + upcoming), chronological.
            $schedule = []; // schedule[pairId] = [ ['cross'=>, 'when'=>, 'court'=>, 'played'=>bool], ... ]
            foreach ($ordered as $pid) {
                $schedule[$pid] = [];
            }

            foreach ($matches as $m) {
                $a = $m->pair_a_id;
                $b = $m->pair_b_id;
                if (! isset($index[$a]) || ! isset($index[$b])) continue;
                $played = $m->state->value === 'confirmed';

                // Row A vs Col B (score from A's perspective) and the mirror.
                foreach ([[$a, $b, 'a'], [$b, $a, 'b']] as [$row, $col, $side]) {
                    if ($played && $m->sets) {
                        // Score from the ROW pair's perspective.
                        $score = collect($m->sets)->map(function ($s) use ($side) {
                            return $side === 'a' ? "{$s[0]}/{$s[1]}" : "{$s[1]}/{$s[0]}";
                        })->implode(' ');
                        $won = $m->winner_pair_id === $row;
                        $cells[$row][$col] = ['played' => true, 'score' => $score, 'won' => $won];
                    } else {
                        // Not played: keep the scheduled time if any (shown faint).
                        $cells[$row][$col] = [
                            'played' => false,
                            'when' => $m->starts_at,
                            'court' => $tournament->showsPublicCourtName() ? $m->court?->name : null,
                        ];
                    }
                }

                // Every cross (played AND upcoming) goes on BOTH pairs' schedule,
                // with its time + court. Played ones are flagged so the view can
                // dim them and mark them done.
                $rn = $index[$a] + 1;
                $cn = $index[$b] + 1;
                $cross = "{$rn}-{$cn}";
                foreach ([$a, $b] as $pid) {
                    $schedule[$pid][] = [
                        'cross' => $cross,
                        'when' => $m->starts_at,
                        'court' => $tournament->showsPublicCourtName() ? $m->court?->name : null,
                        'played' => $played,
                    ];
                }
            }

            // Sort each pair's schedule by time (nulls last). Played/upcoming keep
            // their real chronological order — a played match that happened earlier
            // naturally sorts before an upcoming one later the same day.
            foreach ($schedule as $pid => &$list) {
                usort($list, fn($x, $y) => ($x['when']?->timestamp ?? PHP_INT_MAX) <=> ($y['when']?->timestamp ?? PHP_INT_MAX));
            }
            unset($list);

            return [
                'name' => $group->name,
                'order' => $ordered->all(),                          // [pair_id,...] in row/col order
                'names' => $ordered->mapWithKeys(fn($pid) => [$pid => $pairsById[$pid]?->name() ?? '—'])->all(),
                'cells' => $cells,                                   // [row][col] => cell
                'schedule' => $schedule,                             // [pair_id] => all crosses (played + upcoming)
                'size' => $ordered->count(),
            ];
        })->values();

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
            'calDayParam' => $calDayParam,
            'calSearch' => $calSearch,
            'calDay' => $calDay,
            'calGroup' => $calGroup,
            'calPhase' => $calPhase,
            'calGroupNames' => $calGroupNames,
            'calPhasesPresent' => $calPhasesPresent,
            'calVenue' => $calVenue,
            'calVenuesPresent' => $calVenuesPresent,
            'calMatchedPlayers' => $calMatchedPlayers,
            'calTotal' => $calTotal,
            'ghostQualifiers' => $ghostQualifiers,
            'groupTags' => $groupTags,
            'crossTables' => $crossTables,
        ]);
    }

    /** Public read-only schedule, with optional "buscar mi partido" filter. */
    public function schedule(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        $search = trim((string) $request->query('q', ''));
        $categoryFilter = $request->query('cat'); // category id
        $dayFilter = $request->query('day');      // Y-m-d
        $phaseFilter = $request->query('phase');  // publicPhase key
        $venueFilter = $request->query('venue');  // venue id (only relevant with 2+ venues)

        $matches = $tournament->categories()
            ->publicVisible()
            ->with(['matches' => function ($q) {
                $q->whereNotNull('starts_at')
                    ->with([
                        'court.venue',
                        'category',
                        'group',
                        'pairA.player1',
                        'pairA.player2',
                        'pairB.player1',
                        'pairB.player2',
                        // Feeder pairs power searchableNames() projection for R2 slots
                        // whose participants aren't bound yet (avoids N+1 on search).
                        'feederA.pairA.player1',
                        'feederA.pairA.player2',
                        'feederA.pairB.player1',
                        'feederA.pairB.player2',
                        'feederB.pairA.player1',
                        'feederB.pairA.player2',
                        'feederB.pairB.player1',
                        'feederB.pairB.player2',
                    ])
                    ->orderBy('starts_at');
            }])
            ->get()
            ->flatMap->matches
            ->sortBy('starts_at')
            ->values();

        // Filter options (before narrowing).
        $allCategories = $tournament->categories()->publicVisible()->orderBy('name')->get(['id', 'name']);
        $allDays = $matches->map(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'))->unique()->values();
        $allPhases = \App\Support\SchedulePhase::presentIn($matches);
        // Venues present (court → venue). Chips render only when there are 2+.
        $allVenues = $matches
            ->map(fn($m) => $m->court?->venue)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn($v) => ['id' => $v->id, 'name' => $v->name])
            ->values();

        // #6: default to TODAY when no day is chosen and today has matches, so a
        // visitor opening the calendar mid-tournament lands on the live day. The
        // "Todos" chip passes the explicit sentinel `day=all` to show every day.
        // $dayParam keeps the RAW request value so generated URLs preserve the
        // user's explicit choice (incl. the `all` sentinel); $dayFilter is the
        // resolved value used for filtering + chip active states.
        $dayParam = $dayFilter;
        $today = \Carbon\Carbon::now('America/Mexico_City')->format('Y-m-d');
        if ($dayFilter === 'all') {
            $dayFilter = null;
        } elseif (($dayFilter === null || $dayFilter === '') && $allDays->contains($today)) {
            $dayFilter = $today;
        }

        // Apply category filter.
        if ($categoryFilter) {
            $matches = $matches->where('category_id', (int) $categoryFilter)->values();
        }
        // Apply day filter.
        if ($dayFilter) {
            $matches = $matches->filter(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d') === $dayFilter)->values();
        }
        // Apply phase filter.
        if ($phaseFilter) {
            $matches = $matches->filter(fn($m) => $m->publicPhase()['key'] === $phaseFilter)->values();
        }
        // Apply venue filter.
        if ($venueFilter) {
            $matches = $matches->filter(fn($m) => $m->court?->venue?->id === (int) $venueFilter)->values();
        }

        // Ghost/qualifier map [category_id => [seedLabel => pairName]] — built
        // BEFORE the search filter so "Buscar mi partido" also matches PROJECTED
        // (R2 / feeder / ghost-qualifier) participants, not just bound pairs.
        $ghostQualifiers = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->mapForTournament($tournament);

        // "Buscar mi partido": filter to matches involving a player/pair name,
        // INCLUDING matches where the player is a projected qualifier (so a
        // player finds the round-2 slots they may reach).
        $matchedPlayers = collect();
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $matches = $matches->filter(function ($m) use ($needle, $ghostQualifiers) {
                // searchableNames() folds in bound players + feeder projections +
                // ghost-qualifier names, lowercased and '|'-joined.
                if (str_contains($m->searchableNames($ghostQualifiers), $needle)) {
                    return true;
                }
                // Also match the bound pair display name (e.g. a team alias).
                foreach ([$m->pairA, $m->pairB] as $pair) {
                    if ($pair && str_contains(mb_strtolower($pair->name()), $needle)) {
                        return true;
                    }
                }
                return false;
            })->values();

            // Collect the distinct BOUND players whose name matched (for quick
            // profile links; projected players have no bound Player row yet).
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
        return view('public.schedule', [
            'tournament' => $tournament,
            'byDay' => $byDay,
            'search' => $search,
            'total' => $matches->count(),
            'matchedPlayers' => $matchedPlayers,
            'allCategories' => $allCategories,
            'allDays' => $allDays,
            'allPhases' => $allPhases,
            'allVenues' => $allVenues,
            'categoryFilter' => $categoryFilter,
            'dayFilter' => $dayFilter,
            'dayParam' => $dayParam,
            'phaseFilter' => $phaseFilter,
            'venueFilter' => $venueFilter,
            'ghostQualifiers' => $ghostQualifiers,
        ]);
    }

    /** Public player page: their matches, results, and stats in this tournament. */
    public function player(Tournament $tournament, \App\Models\Player $player)
    {
        $this->ensurePublic($tournament);

        // The SAME human often has a separate Player row per category (dedupe only
        // happens on email/phone). Resolve the whole human by normalized_name so
        // the page can show every category they play in. Scope to the manager who
        // owns this record to avoid colliding two different people with the same
        // name across managers.
        $playerIds = \App\Models\Player::query()
            ->where('normalized_name', $player->normalized_name)
            ->when($player->created_by, fn($q) => $q->where('created_by', $player->created_by))
            ->pluck('id');
        if ($playerIds->isEmpty()) $playerIds = collect([$player->id]);

        // All pairs any of those records belong to, within THIS tournament.
        $pairIds = \App\Models\Pair::where(function ($q) use ($playerIds) {
            $q->whereIn('player1_id', $playerIds)->orWhereIn('player2_id', $playerIds);
        })
            ->whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->pluck('id');
        $possibleMatches = $this->possibleR2Matches($pairIds, $tournament);

        abort_if($pairIds->isEmpty(), Response::HTTP_NOT_FOUND);

        // Map each pair to its category, so the view can tab matches by category.
        $pairCategory = \App\Models\Pair::whereIn('id', $pairIds)
            ->pluck('category_id', 'id'); // [pair_id => category_id]



        // Matches involving any of those pairs.
        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->where(fn($q) => $q->whereIn('pair_a_id', $pairIds)->orWhereIn('pair_b_id', $pairIds))
            ->with(['category', 'group', 'court.venue', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
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
            'pairCategory' => $pairCategory,   // [pair_id => category_id]
            'possibleMatches' => $possibleMatches, // [pair_id => [GameMatch,...]] for projected R2 matches

        ]);
    }

    /** 404 unless the tournament is publicly listed. */
    /**
     * Full-screen "arrivals board" display for a TV / kiosk (public, no login).
     * Config is URL-param driven so a screen just bookmarks a URL; the manager
     * "Pantalla" builder generates it. JS polls displayData() to stay live.
     */
    public function display(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        $max     = max(1, min(20, (int) $request->query('max', 6)));
        $rotate  = max(4, min(120, (int) $request->query('rotate', 12)));
        $refresh = max(10, min(300, (int) $request->query('refresh', 30)));
        $venues  = $tournament->venues()->orderBy('name')->get(['id', 'name']);

        return view('public.display', compact('tournament', 'max', 'rotate', 'refresh', 'venues'));
    }

    /**
     * JSON feed for the display board: current ("en juego"), upcoming
     * ("próximos") and recently finished matches, honoring the venue filter and
     * the look-ahead / look-back windows. Prefers Mesa de control timestamps and
     * falls back to the scheduled time + (day-aware) duration.
     */
    public function displayData(Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        $tz = 'America/Mexico_City';
        $now = \Carbon\Carbon::now($tz);

        $venueIds = collect(explode(',', (string) $request->query('venues', '')))
            ->map(fn($v) => (int) trim($v))->filter()->values()->all();
        $aheadMin = max(15, min(720, (int) $request->query('ahead', 180)));
        $backMin  = max(0, min(360, (int) $request->query('back', 45)));
        // Pin a specific date (Y-m-d): show ONLY that day, the whole day in time
        // order (finished inline with results), ignoring the ahead/back windows.
        $dayFilter = $request->query('day'); // 'YYYY-MM-DD' or null for live rolling mode
        $dayFilter = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dayFilter) ? $dayFilter : null;

        $matches = $tournament->categories()
            ->publicVisible()
            ->with(['matches' => fn($q) => $q->whereNotNull('starts_at')
                ->with(['court.venue', 'category', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
                ->orderBy('starts_at')])
            ->get()->flatMap->matches;

        if ($venueIds) {
            $matches = $matches->filter(fn($m) => in_array((int) optional(optional($m->court)->venue)->id, $venueIds, true));
        }

        $items = [];
        foreach ($matches as $m) {
            $start = $m->starts_at->timezone($tz);

            // Date-pinned mode: keep only the chosen day; otherwise classify by window.
            if ($dayFilter && $start->format('Y-m-d') !== $dayFilter) {
                continue;
            }

            $end = $start->copy()->addMinutes($tournament->durationForDay($start));
            $status = $m->controlStatus();
            $isFinished = $status === 'finished' || ($m->ended_at === null && $status !== 'playing' && $now->gte($end));

            if ($isFinished) {
                $finishedAt = $m->ended_at ? $m->ended_at->timezone($tz) : $end;
                $bucket = 'finished';
                // In live mode, drop old finished matches (date mode keeps the whole day).
                if (! $dayFilter && $finishedAt->diffInMinutes($now, false) > $backMin) continue;
                $sort = $dayFilter ? $start->timestamp : -$finishedAt->timestamp;
            } elseif ($status === 'playing' || ($now->gte($start) && $now->lt($end))) {
                $bucket = 'current';
                $sort = $start->timestamp;
            } elseif ($now->lt($start)) {
                $bucket = 'upcoming';
                // Signed diff so a far-future match is excluded (not its absolute value).
                if (! $dayFilter && $start->diffInMinutes($now, false) > $aheadMin) continue;
                $sort = $start->timestamp;
            } else {
                continue; // past, unfinished, out of window
            }

            $winnerSide = null;
            if ($m->winner_pair_id) {
                $winnerSide = $m->winner_pair_id === $m->pair_a_id ? 'a' : 'b';
            }
            $score = $this->formatMatchSets($m->sets);
            $winnerName = $winnerSide === 'a'
                ? (optional($m->pairA)->name())
                : ($winnerSide === 'b' ? optional($m->pairB)->name() : null);

            // Court/venue per the tournament's public setting. Full → court + venue;
            // VenueOnly → venue as the cell text, no sub-label; Hidden → blank (the
            // column itself is hidden via a class on the board — see display.blade).
            $pcv = $tournament->publicCourtVisibility();
            $courtName = optional($m->court)->name;
            $venueName = optional(optional($m->court)->venue)->name;
            if ($pcv->isHidden()) {
                $dispCourt = '';
                $dispVenue = '';
            } elseif (! $pcv->showsCourtName()) { // VenueOnly
                $dispCourt = $venueName ?: '';
                $dispVenue = '';
            } else { // Full
                $dispCourt = $courtName;
                $dispVenue = $venueName;
            }

            $items[] = [
                'bucket'      => $bucket,
                'sort'        => $sort,
                'time'        => $start->format('H:i'),
                'court'       => $dispCourt,
                'venue'       => $dispVenue,
                'category'    => optional($m->category)->name,
                'phase'       => $m->publicPhase()['label'] ?? null,
                'pairA'       => optional($m->pairA)->name() ?: $m->sideLabel('a'),
                'pairB'       => optional($m->pairB)->name() ?: $m->sideLabel('b'),
                'status'      => $status,
                'statusLabel' => $m->controlStatusLabel(),
                'score'       => $score,          // "6-3 6-4" or null
                'winnerSide'  => $winnerSide,     // 'a' | 'b' | null (bold the winner)
                'winner'      => $winnerName,     // for the ticker
            ];
        }

        $col = collect($items);

        if ($dayFilter) {
            // Whole day, chronological; finished shown inline with results. No ticker.
            return response()->json([
                'now'        => $now->format('H:i'),
                'board'      => $col->sortBy('sort')->values(),
                'finished'   => [],
                'venueLabel' => $this->displayVenueLabel($tournament, $venueIds),
                'dayLabel'   => \Carbon\Carbon::parse($dayFilter, $tz)->translatedFormat('l d \d\e F'),
                'counts'     => ['board' => $col->count(), 'current' => $col->where('bucket', 'current')->count()],
            ]);
        }

        // Live rolling mode: en juego + próximos on the board, finished → ticker.
        $current  = $col->where('bucket', 'current')->sortBy('sort')->values();
        $upcoming = $col->where('bucket', 'upcoming')->sortBy('sort')->values();
        $finished = $col->where('bucket', 'finished')->sortBy('sort')->values();

        return response()->json([
            'now'        => $now->format('H:i'),
            'board'      => $current->concat($upcoming)->values(),
            'finished'   => $finished->values(),
            'venueLabel' => $this->displayVenueLabel($tournament, $venueIds),
            'dayLabel'   => null,
            'counts'     => ['current' => $current->count(), 'upcoming' => $upcoming->count(), 'finished' => $finished->count()],
        ]);
    }

    /** "6-3 6-4" from a match's sets JSON, or null when there's no result yet. */
    private function formatMatchSets($sets): ?string
    {
        if (empty($sets) || ! is_array($sets)) return null;
        $parts = [];
        foreach ($sets as $s) {
            $a = $s[0] ?? ($s['a'] ?? null);
            $b = $s[1] ?? ($s['b'] ?? null);
            if ($a === null && $b === null) continue;
            $parts[] = ((int) $a) . '-' . ((int) $b);
        }
        return $parts ? implode(' ', $parts) : null;
    }

    /** Header label for the active venue filter ("Todas las sedes" or names). */
    private function displayVenueLabel(Tournament $tournament, array $venueIds): string
    {
        if (empty($venueIds)) return 'Todas las sedes';
        $names = $tournament->venues()->whereIn('id', $venueIds)->orderBy('name')->pluck('name');
        return $names->isEmpty() ? 'Todas las sedes' : $names->implode(' · ');
    }

    private function ensurePublic(Tournament $tournament): void
    {
        abort_unless((bool) $tournament->is_listed, Response::HTTP_NOT_FOUND);
    }


    /**
     * Public winners summary. Dropdown selects a category; page shows that
     * category's elimination podium. Only finished categories are "decided".
     */
    public function winners(\Illuminate\Http\Request $request, Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        // Categories that CAN have a bracket, ordered for the dropdown.
        $categories = $tournament->categories()->orderBy('name')->get();

        // Build a podium per category. A category is "decided" only when its final
        // (max-round bracket match) is confirmed.
        $summaries = $categories->map(function (Category $category) {
            return $this->buildCategoryPodium($category);
        })->values();

        // Which category is selected in the dropdown? Default: first DECIDED one,
        // else the first category.
        $decided = $summaries->firstWhere('decided', true);
        $selectedId = (int) ($request->query('cat')
            ?: ($decided['category_id'] ?? ($summaries->first()['category_id'] ?? 0)));

        $selected = $summaries->firstWhere('category_id', $selectedId)
            ?? $summaries->first();

        return view('public.winners', [
            'tournament' => $tournament,
            'summaries'  => $summaries,   // for the dropdown (all categories)
            'selected'   => $selected,    // the podium to render
            'ads'        => \App\Models\Ad::forTournament($tournament),
        ]);
    }

    /**
     * Resolve one category's elimination podium.
     *
     * Returns:
     *   [
     *     'category_id' => int,
     *     'category'    => string,
     *     'decided'     => bool,        // final confirmed?
     *     'has_bracket' => bool,
     *     'champion'    => ?string,     // pair name
     *     'runner_up'   => ?string,     // pair name (null for a bye-final)
     *     'third'       => ?string,     // pair name (only if a 3rd-place match exists)
     *     'final_score' => ?string,     // e.g. "6-3 4-6 6-2"
     *   ]
     */
    private function buildCategoryPodium(Category $category): array
    {
        // Single source of truth — shared with the manager Ganadores page + export.
        return app(\App\Services\Tournament\PodiumService::class)->forCategory($category);
    }
    /**
     * Public player directory for a tournament: one row per player-per-category
     * (per registration), highlighting the player with their partner shown
     * subtly. Filterable by category, searchable by player OR partner name.
     */
    public function players(Tournament $tournament)
    {
        $this->ensurePublic($tournament);

        // Only listed categories on the public side.
        $categories = $tournament->categories()->orderBy('name')->get(['id', 'name']);

        $pairs = \App\Models\Pair::whereIn('category_id', $categories->pluck('id'))
            ->with([
                'category:id,name',
                'player1:id,name',
                'player2:id,name',
            ])
            ->get();

        $rows = $this->buildPlayerRows($pairs);

        return view('public.players', [
            'tournament' => $tournament,
            'categories' => $categories,
            'rows' => $rows,
            'linkPlayers' => true, // link each row to the public player profile
        ]);
    }

    /**
     * Flatten pairs into one row per player-per-category.
     * Each row: player (id,name), partner name (or null), category (id,name),
     * plus a lowercase search blob covering both names.
     *
     * Shared shape with the manager directory so the same partial renders both.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    private function buildPlayerRows(\Illuminate\Support\Collection $pairs)
    {
        $rows = collect();

        foreach ($pairs as $pair) {
            $cat = $pair->category;
            $p1 = $pair->player1;
            $p2 = $pair->player2;

            // Row for player 1 (partner = player 2, may be null for singles).
            if ($p1) {
                $rows->push($this->playerRow($p1, $p2, $cat, (bool) $pair->is_singles));
            }
            // Row for player 2 (partner = player 1).
            if ($p2) {
                $rows->push($this->playerRow($p2, $p1, $cat, (bool) $pair->is_singles));
            }
        }

        // Sort by player name, then category, for a stable directory.
        return $rows
            ->sortBy(fn($r) => \Illuminate\Support\Str::lower($r['player']['name']) . '|' . $r['category']['name'])
            ->values();
    }

    private function playerRow($player, $partner, $category, bool $isSingles): array
    {
        $partnerName = $partner?->name;
        $search = \Illuminate\Support\Str::lower(trim(
            ($player->name ?? '') . ' ' . ($partnerName ?? '')
        ));

        return [
            'player' => ['id' => $player->id, 'name' => $player->name],
            'partner' => $partnerName,          // null for singles
            'is_singles' => $isSingles,
            'category' => ['id' => $category->id, 'name' => $category->name],
            'search' => $search,
        ];
    }

    protected function possibleR2Matches($pairIds, \App\Models\Tournament $tournament): array
    {
        $pairIds = collect($pairIds)->map(fn($v) => (int) $v)->all();
        if (empty($pairIds)) return [];

        $tz = 'America/Mexico_City';

        // 1) The player's UNPLAYED "R1" matches that FEED a later match. Feeders
        //    exist in BOTH shapes: Mexicano groups (round 1 → round 2, same
        //    group_id) AND elimination brackets (group_id null). We do NOT
        //    restrict on group_id — the feeder chain is what matters, not the
        //    phase. A match is a feeder here iff some other match points at it via
        //    feeder_a_id / feeder_b_id; but we can start from the player's own
        //    undecided matches and look for parents that reference them.
        $r1 = \App\Models\GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->where(fn($q) => $q->whereIn('pair_a_id', $pairIds)->orWhereIn('pair_b_id', $pairIds))
            ->whereNull('winner_pair_id') // not decided yet
            ->with(['category:id,name', 'pairA', 'pairB'])
            ->get();

        if ($r1->isEmpty()) return [];

        // 2) Matches fed by any of those (still unbound on the fed side). Feeder-
        //    based, phase-agnostic — covers Mexicano R2 (same group) and bracket.
        $r1Ids = $r1->pluck('id')->all();

        $r2 = \App\Models\GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->where(fn($q) => $q->whereIn('feeder_a_id', $r1Ids)->orWhereIn('feeder_b_id', $r1Ids))
            ->with([
                'category:id,name',
                'group:id,name',
                'court.venue',
                'pairA.player1',
                'pairA.player2',
                'pairB.player1',
                'pairB.player2',
                'feederA.pairA',
                'feederA.pairB',
                'feederB.pairA',
                'feederB.pairB',
            ])
            ->get();

        $out = [];

        foreach ($r2 as $m) {
            // Which side is fed by one of the player's R1 matches (and still unbound)?
            $feedsFromMine = null; // 'a' | 'b'  → the side the PLAYER would occupy
            if (in_array((int) $m->feeder_a_id, $r1Ids, true) && ! $m->pair_a_id) {
                $feedsFromMine = 'a';
            } elseif (in_array((int) $m->feeder_b_id, $r1Ids, true) && ! $m->pair_b_id) {
                $feedsFromMine = 'b';
            }
            if ($feedsFromMine === null) continue;

            // The OTHER side is the opponent(s) the player would face — its label
            // already reads "Ganador (X / Y)" / a bound pair / a seed label.
            $otherSide = $feedsFromMine === 'a' ? 'b' : 'a';
            $vs = $m->sideLabel($otherSide);

            // How the player reaches this slot (winner/loser of their R1).
            $source = $feedsFromMine === 'a' ? $m->feeder_a_source : $m->feeder_b_source;
            $reach = $source === 'loser' ? 'si pierde su partido' : 'si gana su partido';

            $day = $m->starts_at?->timezone($tz);
            $whenLabel = $day
                ? \Illuminate\Support\Str::ucfirst($day->locale('es')->isoFormat('ddd D MMM')) . ' · ' . $day->format('H:i')
                : null;

            // Round label respects the phase: Mexicano group R2 → "Grupo X · R2";
            // elimination bracket → "Semifinal" etc. (contextLabel already does
            // exactly this split, so we reuse it.)
            $roundLabel = $m->group_id
                ? (($m->group?->name ? $m->group->name . ' · ' : '') . 'R' . $m->round)
                : $m->bracketRoundName();

            $out[] = [
                'match_id' => $m->id,
                'category' => $m->category?->name ?? '',
                'round'    => $roundLabel,
                'when'     => $whenLabel,                 // null when unscheduled
                'day'      => $day?->format('Y-m-d'),
                'time'     => $day?->format('H:i'),
                // Court-only context; show it only when the tournament's public
                // court setting is Full. This method already receives $tournament.
                'court'    => $tournament->showsPublicCourtName() ? $m->court?->name : null,
                'vs'       => $vs,                         // "Ganador (X / Y)" etc.
                'reach'    => $reach,                      // "si gana/pierde su partido"
                'source'   => $source ?: 'winner',
            ];
        }

        // Scheduled first (by time), then unscheduled; de-dup by match_id.
        $seen = [];
        $out = array_values(array_filter($out, function ($r) use (&$seen) {
            if (isset($seen[$r['match_id']])) return false;
            $seen[$r['match_id']] = true;
            return true;
        }));
        usort($out, function ($a, $b) {
            $aw = $a['when'] ? 0 : 1;
            $bw = $b['when'] ? 0 : 1;
            if ($aw !== $bw) return $aw <=> $bw;
            return ($a['day'] . $a['time']) <=> ($b['day'] . $b['time']);
        });

        return $out;
    }
    /** Tournament-wide recent schedule changes (JSON). */
    public function changes(Tournament $tournament)
    {
        $this->ensurePublic($tournament);
        return response()->json(['changes' => $this->recentScheduleChanges($tournament)]);
    }

    /** Recent schedule changes for one category (JSON). */
    public function categoryChanges(Tournament $tournament, Category $category)
    {
        $this->ensurePublic($tournament);
        abort_unless($category->tournament_id === $tournament->id, 404);
        return response()->json(['changes' => $this->recentScheduleChanges($tournament, $category)]);
    }

    /**
     * Build the public change feed: court + time changes in the last 48h, newest
     * first, rendered as human rows. Optionally scoped to one category.
     *
     * @return array<int, array{when:string, ago:string, category:string, match:string, summary:string}>
     */
    private function recentScheduleChanges(Tournament $tournament, ?Category $category = null): array
    {
        $tz = 'America/Mexico_City';
        $since = now()->subHours(48);

        $audits = \App\Models\MatchAudit::query()
            ->whereIn('action', ['schedule_change', 'court_switch'])
            ->where('created_at', '>=', $since)
            ->whereHas('match', function ($q) use ($tournament, $category) {
                $q->whereHas('category', function ($qq) use ($tournament, $category) {
                    $qq->where('tournament_id', $tournament->id);
                    if ($category) $qq->where('id', $category->id);
                });
            })
            ->with([
                'match:id,category_id,group_id,round,pair_a_id,pair_b_id,court_id,starts_at',
                'match.category:id,name',
                'match.group:id,name',
                'match.court:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(60)
            ->get();

        // Court id → public label map so before/after court ids render as names,
        // honoring the tournament's public court setting (no leak when hidden).
        $courtNames = $tournament->courts()
            ->with('venue')
            ->get()
            ->mapWithKeys(function ($court) use ($tournament) {
                $label = $tournament->publicCourtLabel($court);
                return [$court->id => $label !== '' ? $label : 'cancha'];
            });
        $out = [];
        foreach ($audits as $a) {
            $m = $a->match;
            if (! $m) continue;

            $before = $a->before ?? [];
            $after = $a->after ?? [];

            $summary = $this->changeSummary($a->action, $before, $after, $courtNames, $tz);
            if ($summary === null) continue; // nothing meaningful to show

            $created = $a->created_at->timezone($tz);
            $out[] = [
                'when' => $created->format('Y-m-d H:i'),
                'ago' => $created->locale('es')->diffForHumans(),
                'category' => $m->category?->name ?? '',
                'match' => method_exists($m, 'contextLabel') ? $m->contextLabel() : ($m->category?->name ?? ''),
                'summary' => $summary,
            ];
        }

        return $out;
    }

    /** Human "from → to" summary for one audit; null if nothing changed. */
    private function changeSummary(string $action, array $before, array $after, $courtNames, string $tz): ?string
    {
        $fmtTime = function ($val) use ($tz) {
            if (blank($val)) return null;
            try {
                return \Illuminate\Support\Str::ucfirst(
                    \Carbon\Carbon::parse($val, $tz)->locale('es')->isoFormat('ddd D MMM · HH:mm')
                );
            } catch (\Throwable $e) {
                return (string) $val;
            }
        };
        $courtName = fn($id) => $id ? ($courtNames[$id] ?? ('Cancha ' . $id)) : null;

        $bT = $fmtTime($before['starts_at'] ?? null);
        $aT = $fmtTime($after['starts_at'] ?? null);
        $bC = $courtName($before['court_id'] ?? null);
        $aC = $courtName($after['court_id'] ?? null);

        $timeChanged = ($before['starts_at'] ?? null) !== ($after['starts_at'] ?? null);
        $courtChanged = ($before['court_id'] ?? null) !== ($after['court_id'] ?? null);

        // Sent back to the tray.
        if (blank($after['starts_at'] ?? null) && ! blank($before['starts_at'] ?? null)) {
            return 'Se quitó del calendario (por reprogramar)';
        }

        // First-time scheduling (was in the tray → now placed): read cleanly.
        if (! $bT && $aT) {
            $where = $aC ? " · {$aC}" : '';
            return "Programado: {$aT}{$where}";
        }

        $parts = [];
        if ($timeChanged && $bT && $aT) {
            $parts[] = "Horario: {$bT} → {$aT}";
        }
        if ($courtChanged && ($bC || $aC)) {
            $parts[] = ($bC ?? '—') . ' → ' . ($aC ?? '—');
        }

        return empty($parts) ? null : implode(' · ', $parts);
    }
}
