<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\Tournament\SchedulingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function __construct(private SchedulingService $scheduler) {}

    /** Custom court-grid board for the tournament (one day at a time). */
    public function index(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $courts = $tournament->courts()->with('venue')->get();

        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->with([
                'category',
                'group.pairs:id',
                'pairA.player1',
                'pairA.player2',
                'pairB.player1',
                'pairB.player2',
                'feederA.group',
                'feederB.group',
                'pendingProposal.proposer:id,name',
            ])
            ->get();

        $scheduled = $matches->whereNotNull('starts_at');
        $unscheduled = $matches->whereNull('starts_at')
            // Schedulable when: both pairs known; OR fed by earlier matches
            // (Mexicano R2, later bracket rounds); OR a positional bracket match
            // with two real seed labels (e.g. "Grupo A - 1 vs Grupo B - 2") whose
            // pairs bind once groups finish. Genuine byes (a side = 'BYE') are
            // excluded — nobody plays them.
            ->filter(function ($m) {
                if ($m->pair_a_id && $m->pair_b_id) return true;
                if ($m->feeder_a_id || $m->feeder_b_id) return true;
                $a = $m->seed_label_a;
                $b = $m->seed_label_b;
                return $a && $b && $a !== 'BYE' && $b !== 'BYE';
            })
            ->values();

        // Phases that actually exist in this tournament + any saved windows.
        $presentPhases = \App\Support\SchedulePhase::presentIn($matches);
        $phaseWindows = $tournament->phaseWindows()->get()->groupBy('phase');
        $capacity = app(\App\Services\Tournament\CapacityService::class)->preview($tournament);
        $proposal = app(\App\Services\Tournament\CapacityService::class)->proposeWindows($tournament);
        $proposedWindows = $proposal['windows'];
        $proposalOverflow = $proposal['overflow'];
        // Categories in this tournament (for the highlight filter chips), with tint.
        $categories = $tournament->categories()->orderBy('name')->get(['id', 'name', 'tint']);

        // Cheatsheet: players registered in 2+ categories (collision risk).
        $multiCategoryPlayers = $this->multiCategoryPlayers($tournament);
        $ghostQualifiers = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->mapForTournament($tournament);
        return view('dashboard.schedule.index', [
            'tournament' => $tournament,
            'courts' => $courts,
            'scheduled' => $scheduled,
            'unscheduled' => $unscheduled,
            'days' => $tournament->playDays(),
            'slots' => $tournament->timeSlots(),
            'presentPhases' => $presentPhases,
            'phaseWindows' => $phaseWindows,
            'capacity' => $capacity,
            'proposedWindows' => $proposedWindows,
            'proposalOverflow' => $proposalOverflow,
            'categories' => $categories,
            'multiCategoryPlayers' => $multiCategoryPlayers,
            'preferredSchedulePlayers' => $this->preferredSchedulePlayers($tournament),
            'busyDayPlayers' => $this->busyDayPlayers($tournament, 3),
            'ghostQualifiers' => $ghostQualifiers,
            'playerMatchIndex' => $this->playerMatchIndex($tournament),
        ]);
    }

    /**
     * Cheatsheet: players with 3+ matches on a SINGLE day, counted across ALL
     * categories. Keyed by NORMALIZED NAME because the same human is usually a
     * separate Player row per category — counting by player_id would split them
     * and hide the real load (e.g. 2 matches in 5ta + 2 in 6ta = 4 in one day).
     *
     * Each row: name, categories, and per-day [day, count, times[]].
     */
    private function busyDayPlayers(Tournament $tournament, int $threshold = 3): \Illuminate\Support\Collection
    {
        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->with(['category:id,name', 'pairA.player1:id,name', 'pairA.player2:id,name', 'pairB.player1:id,name', 'pairB.player2:id,name'])
            ->orderBy('starts_at')
            ->get();

        // [normName => ['name'=>, 'categories'=>[], 'days'=>['Y-m-d'=>['HH:MM', ...]]]]
        $byName = [];
        foreach ($matches as $m) {
            $day = $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d');
            $time = $m->starts_at->timezone('America/Mexico_City')->format('H:i');
            $catName = $m->category?->name;

            foreach ([$m->pairA, $m->pairB] as $pair) {
                if (! $pair) continue;
                foreach ([$pair->player1, $pair->player2] as $p) {
                    if (! $p || blank($p->name)) continue;
                    $key = \App\Models\Player::normalize($p->name);
                    $byName[$key] ??= ['name' => $p->name, 'categories' => [], 'days' => []];
                    $byName[$key]['days'][$day][] = $time;
                    if ($catName && ! in_array($catName, $byName[$key]['categories'], true)) {
                        $byName[$key]['categories'][] = $catName;
                    }
                }
            }
        }

        // Keep only players who hit the threshold on at least one day, and expose
        // just those overloaded days.
        $dayLabel = fn(string $ymd) => \Illuminate\Support\Str::ucfirst(
            \Carbon\Carbon::parse($ymd, 'America/Mexico_City')->locale('es')->isoFormat('ddd D MMM')
        );

        $out = [];
        foreach ($byName as $row) {
            $heavy = [];
            foreach ($row['days'] as $ymd => $times) {
                if (count($times) < $threshold) continue;
                sort($times);
                $heavy[] = [
                    'day' => $ymd,
                    'label' => $dayLabel($ymd),
                    'count' => count($times),
                    'times' => $times,
                ];
            }
            if (empty($heavy)) continue;

            usort($heavy, fn($a, $b) => $a['day'] <=> $b['day']);
            $out[] = [
                'name' => $row['name'],
                'categories' => $row['categories'],
                'days' => $heavy,
                'max' => max(array_column($heavy, 'count')),
            ];
        }

        // Heaviest load first, then name.
        usort($out, fn($a, $b) => [$b['max'], strtolower($a['name'])] <=> [$a['max'], strtolower($b['name'])]);

        return collect($out);
    }

    /** Players with availability rules, for the calendar cheatsheet. Each row:
     *  name, rules (human strings like "Vie desde 19:00"), categories. */
    private function preferredSchedulePlayers(Tournament $tournament): \Illuminate\Support\Collection
    {
        $map = \App\Models\PlayerAvailability::windowsFor($tournament); // [normName => ['Y-m-d'=>['from'=>,'until'=>]]]
        if (empty($map)) return collect();

        // Build name + categories per normalized name (same approach as the
        // multi-category cheatsheet) so we can show context.
        $pairs = \App\Models\Pair::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->with(['category:id,name', 'player1:id,name', 'player2:id,name'])
            ->get();

        $info = []; // normName => ['name'=>display, 'categories'=>[]]
        foreach ($pairs as $pair) {
            $catName = $pair->category?->name;
            foreach ([$pair->player1, $pair->player2] as $p) {
                if (! $p) continue;
                $key = \App\Models\Player::normalize($p->name);
                if (! isset($map[$key])) continue; // only players with rules
                $info[$key] ??= ['name' => $p->name, 'categories' => []];
                if ($catName && ! in_array($catName, $info[$key]['categories'], true)) {
                    $info[$key]['categories'][] = $catName;
                }
            }
        }

        // Spanish weekday label for each rule day.
        $dayLabel = function (string $ymd) {
            return \Illuminate\Support\Str::ucfirst(
                \Carbon\Carbon::parse($ymd, 'America/Mexico_City')->locale('es')->isoFormat('ddd')
            );
        };

        $out = [];
        foreach ($map as $key => $days) {
            ksort($days); // chronological
            $rules = [];
            foreach ($days as $ymd => $win) {
                if (is_array($win) && ! empty($win['off'])) {
                    $rules[] = $dayLabel($ymd) . ': no disponible';
                    continue;
                }
                $from = is_array($win) ? ($win['from'] ?? null) : $win;
                $until = is_array($win) ? ($win['until'] ?? null) : null;
                if (! $from) continue;
                $rules[] = $until
                    ? $dayLabel($ymd) . " {$from}–{$until}"
                    : $dayLabel($ymd) . ' desde ' . $from;
            }
            $out[] = [
                'name' => $info[$key]['name'] ?? $key,
                'categories' => $info[$key]['categories'] ?? [],
                'rules' => $rules,
            ];
        }

        // Sort by name.
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return collect($out);
    }

    /** Players who appear in 2+ categories of this tournament, with their
     *  category names. Informative cheatsheet for avoiding scheduling clashes. */
    private function multiCategoryPlayers(Tournament $tournament): \Illuminate\Support\Collection
    {
        $pairs = \App\Models\Pair::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->with(['category:id,name', 'player1:id,name', 'player2:id,name'])
            ->get();

        // Group by NORMALIZED NAME (not player id): the same person is often a
        // separate Player record in each category, so id-grouping would miss
        // them. Name-grouping matches how registration is validated in-tournament.
        $byName = [];
        foreach ($pairs as $pair) {
            $catName = $pair->category?->name;
            foreach ([$pair->player1, $pair->player2] as $p) {
                if (! $p) continue;
                $key = \App\Models\Player::normalize($p->name);
                $byName[$key] ??= ['name' => $p->name, 'categories' => []];
                if ($catName && ! in_array($catName, $byName[$key]['categories'], true)) {
                    $byName[$key]['categories'][] = $catName;
                }
            }
        }

        return collect($byName)
            ->filter(fn($row) => count($row['categories']) >= 2)
            ->sortByDesc(fn($row) => count($row['categories']))
            ->values();
    }

    /** Run the greedy auto-scheduler over unscheduled matches. */
    public function auto(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $courts = $tournament->courts()->with('availabilities')->get();
        if ($courts->isEmpty()) {
            return back()->withErrors(['schedule' => 'Agrega canchas y horarios antes de programar.']);
        }

        $duration = (int) $request->input('duration', $tournament->match_duration_minutes ?: 75);
        $result = $this->scheduler->autoSchedule($tournament, $courts, $duration, $duration);

        $msg = "{$result['scheduled']} partidos programados.";
        if ($result['unplaced'] > 0) {
            $msg .= " {$result['unplaced']} no cupieron.";
            // Note which phases came up short (e.g. window too small).
            $short = [];
            foreach ($result['by_phase'] ?? [] as $phase => $counts) {
                if (($counts['unplaced'] ?? 0) > 0) {
                    $short[] = \App\Support\SchedulePhase::label($phase) . " ({$counts['unplaced']})";
                }
            }
            if ($short) $msg .= ' Revisa la ventana de: ' . implode(', ', $short) . '.';
        }

        return back()->with('status', $msg);
    }

    /** Manually place/move a match (drag-drop). Validates conflicts. */
    public function place(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = $request->validate([
            'match_id' => ['required', 'integer'],
            'court_id' => ['required', 'integer'],
            'starts_at' => ['required', 'date'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:240'],
            'force' => ['nullable', 'boolean'],
        ]);

        $match = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->findOrFail($data['match_id']);
        $court = Court::whereHas('venue', fn($q) => $q->where('tournament_id', $tournament->id))
            ->findOrFail($data['court_id']);

        $startsAt = Carbon::parse($data['starts_at'], 'America/Mexico_City');
        // Always use the tournament's match duration so placements align to the
        // grid slots (a mismatched duration would create off-grid times).
        $duration = $tournament->match_duration_minutes ?: ($data['duration'] ?? 60);

        $conflicts = $this->scheduler->conflictsFor($match, $court, $startsAt, $duration);

        // NEW — inline "no descanso" rest-gap warnings for this placement, merged
        // into the same list so they also block unless forced. (conflictsFor already
        // covers hard same-time clashes; restWarningsFor adds the too-close case.)
        $restWarnings = $this->scheduler->restWarningsFor($match, $startsAt, $duration);
        $conflicts = array_merge($conflicts, $restWarnings);

        // Conflicts (incl. rest-gap) block unless explicitly forced (manager override).
        if (! empty($conflicts) && ! $request->boolean('force')) {
            return response()->json(['ok' => false, 'conflicts' => $conflicts], 422);
        }

        $match->update([
            'court_id' => $court->id,
            'starts_at' => $startsAt,
            'duration_minutes' => $duration,
        ]);

        return response()->json(['ok' => true, 'warnings' => $conflicts]);
    }


    /** Unschedule a match (back to the unplaced tray). */
    public function unplace(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = $request->validate(['match_id' => ['required', 'integer']]);
        $match = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->findOrFail($data['match_id']);

        $match->update(['court_id' => null, 'starts_at' => null]);

        return response()->json(['ok' => true]);
    }

    /** Unschedule MANY matches at once (multi-select on the board). */
    public function unplaceMany(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = $request->validate([
            'match_ids' => ['required', 'array', 'min:1'],
            'match_ids.*' => ['integer'],
        ]);

        // Scope to this tournament so a stray id can't touch another's matches.
        $count = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereIn('id', $data['match_ids'])
            ->whereNotNull('starts_at')
            ->update(['court_id' => null, 'starts_at' => null]);

        return response()->json(['ok' => true, 'count' => $count]);
    }

    /** Unschedule ALL matches in the tournament (clear the whole calendar). */
    public function clearAll(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $count = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->update(['court_id' => null, 'starts_at' => null]);

        return back()->with('status', "{$count} partidos quitados del calendario.");
    }

    /** Detect players double-booked across scheduled matches (post-resolution). */
    public function conflicts(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $conflicts = $this->scheduler->detectConflicts($tournament);

        return back()->with('conflicts', $conflicts)->with('conflictsChecked', true);
    }

    /** Export the full schedule as a PDF (for WhatsApp / sharing). */
    public function exportPdf(Request $request, Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $order = $request->query('order') === 'category' ? 'category' : 'time';

        // Include unscheduled matches too, so unbound bracket slots (ghosts) show.
        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->with([
                'category',
                'group',
                'court',
                'group.pairs:id',                 // for any group-size lookups
                'pairA.player1',
                'pairA.player2',
                'pairB.player1',
                'pairB.player2',
                'feederA',
                'feederB',
            ])
            ->get();

        // Tournament-wide ghost map: [category_id => [seedLabel => pairName]].
        $ghostQualifiers = app(\App\Services\Tournament\GhostQualifierResolver::class)
            ->mapForTournament($tournament);

        // A bracket slot only earns a place in these PDFs if it's schedulable /
        // meaningful: it has a time, OR it's an unbound bracket match that can be
        // resolved (real pairs, feeders, or two real seed labels — not byes).
        $showable = $matches->filter(function ($m) {
            if ($m->starts_at) return true;
            // unscheduled: keep bracket matches that will bind (mirror index())
            if ($m->pair_a_id && $m->pair_b_id) return true;
            if ($m->feeder_a_id || $m->feeder_b_id) return true;
            $a = $m->seed_label_a;
            $b = $m->seed_label_b;
            return $a && $b && $a !== 'BYE' && $b !== 'BYE';
        });

        if ($order === 'category') {
            // Category → datetime → court.
            // Null starts_at always goes last.
            $grouped = $showable
                ->sortBy([
                    // Category
                    fn($a, $b) =>
                    strnatcasecmp(
                        $a->category->name,
                        $b->category->name
                    ),

                    // Date/time
                    fn($a, $b) => ($a->starts_at?->getTimestamp() ?? PHP_INT_MAX)
                        <=>
                        ($b->starts_at?->getTimestamp() ?? PHP_INT_MAX),

                    // Court when date/time is identical
                    fn($a, $b) =>
                    strnatcasecmp(
                        $a->court?->name ?? '~',
                        $b->court?->name ?? '~'
                    ),

                    // Final stable tie-breaker
                    fn($a, $b) =>
                    $a->id <=> $b->id,
                ])
                ->groupBy(fn($m) => $m->category->name);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
                'dashboard.schedule.pdf-category',
                [
                    'tournament' => $tournament,
                    'byCategory' => $grouped,
                    'ghostQualifiers' => $ghostQualifiers,
                    'generatedAt' => now('America/Mexico_City'),
                ]
            )->setPaper('a4', 'portrait');

            return $pdf->download(
                \Illuminate\Support\Str::slug($tournament->name)
                    . '-calendario-categoria.pdf'
            );
        }

        // Default: chronological. Scheduled matches grouped by day; unscheduled
        // matches collected under a "Sin programar" bucket at the end.
        $scheduled = $showable->filter(fn($m) => $m->starts_at)
            ->sortBy(fn($m) => $m->starts_at->timestamp);
        $byDay = $scheduled->groupBy(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'));

        $unscheduled = $showable->filter(fn($m) => ! $m->starts_at)
            ->sortBy(fn($m) => $m->category->name)
            ->values();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.schedule.pdf', [
            'tournament' => $tournament,
            'byDay' => $byDay,
            'unscheduled' => $unscheduled,
            'ghostQualifiers' => $ghostQualifiers,
            'generatedAt' => now('America/Mexico_City'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-calendario.pdf');
    }

    /**
     * Cross-table ("cruces") PDF: one matrix per group, ordered category → group.
     * Group-phase matches only (group_id set). Each cell shows the score when the
     * match is confirmed, else the scheduled time, else blank. Landscape A4.
     */
    public function exportCrucesPdf(Request $request, Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        // Group-phase matches only. Bracket matches (group_id null) are excluded.
        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('group_id')
            ->with([
                'category:id,name',
                'group:id,name,category_id',
                'court:id,name',
                'pairA.player1:id,name',
                'pairA.player2:id,name',
                'pairB.player1:id,name',
                'pairB.player2:id,name',
            ])
            ->get();

        // Build the per-group cross-tables, ordered category → group.
        // $blocks = [ ['category'=>..., 'group'=>..., 'pairs'=>[...],
        //             'grid'=>[[cell]], 'pills'=>[..], 'horario'=>[..] ], ... ]
        $blocks = [];

        $byCatGroup = $matches
            ->groupBy(fn($m) => $m->category->name)
            ->sortKeys();

        foreach ($byCatGroup as $catName => $catMatches) {
            $groups = $catMatches->groupBy(fn($m) => $m->group->name)->sortKeys();

            foreach ($groups as $groupName => $groupMatches) {
                // Distinct pairs in this group, in a stable order (by pair id).
                $pairs = collect();
                foreach ($groupMatches as $m) {
                    if ($m->pairA) $pairs->put($m->pairA->id, $m->pairA);
                    if ($m->pairB) $pairs->put($m->pairB->id, $m->pairB);
                }
                $pairs = $pairs->sortKeys()->values();
                $index = [];
                foreach ($pairs as $i => $p) $index[$p->id] = $i;
                $n = $pairs->count();
                if ($n === 0) continue;

                $grid = array_fill(0, $n, array_fill(0, $n, null));
                $pills = array_fill(0, $n, []);
                $horario = array_fill(0, $n, []);

                foreach ($groupMatches as $m) {
                    if (! $m->pairA || ! $m->pairB) continue;
                    $ia = $index[$m->pairA->id] ?? null;
                    $ib = $index[$m->pairB->id] ?? null;
                    if ($ia === null || $ib === null) continue;

                    $grid[$ia][$ib] = $this->crucesCell($m, true);
                    $grid[$ib][$ia] = $this->crucesCell($m, false);

                    $pills[$ia][] = ($ia + 1) . '-' . ($ib + 1);
                    $pills[$ib][] = ($ib + 1) . '-' . ($ia + 1);

                    $slot = $this->crucesSlot($m);
                    if ($slot) {
                        $horario[$ia][] = $slot;
                        $horario[$ib][] = $slot;
                    }
                }

                $blocks[] = [
                    'category' => $catName,
                    'group'    => $groupName,
                    'pairs'    => $pairs,
                    'grid'     => $grid,
                    'pills'    => $pills,
                    'horario'  => $horario,
                ];
            }
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.schedule.pdf-cruces', [
            'tournament'  => $tournament,
            'blocks'      => $blocks,
            'generatedAt' => now('America/Mexico_City'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-cruces.pdf');
    }

    /** Cell content: confirmed score (from a pair's perspective), else time, else ''. */
    private function crucesCell(GameMatch $m, bool $fromA): string
    {
        if ($m->state === \App\Enums\MatchState::Confirmed) {
            [$aSets, $bSets] = $m->setsWon();
            return $fromA ? "{$aSets}-{$bSets}" : "{$bSets}-{$aSets}";
        }
        if ($m->starts_at) {
            return $m->starts_at->timezone('America/Mexico_City')
                ->locale('es')->isoFormat('ddd HH:mm');
        }
        return '';
    }

    /** "mié. 18:15 · Cancha 2" for the Horario column, or null if unscheduled. */
    private function crucesSlot(GameMatch $m): ?string
    {
        if (! $m->starts_at) return null;
        $when = $m->starts_at->timezone('America/Mexico_City')->locale('es')->isoFormat('ddd DD MMM · HH:mm');
        $court = $m->court?->name;
        return $court ? "{$when} · {$court}" : $when;
    }

    /**
     * Validate scheduled matches against players' preferred-schedule rules.
     * Returns JSON for the "Validar horarios" bottom sheet: one entry per
     * rule-bearing player with their matches classified ok / error / pending.
     */
    public function scheduleValidation(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $windows = \App\Models\PlayerAvailability::windowsFor($tournament);
        if (empty($windows)) {
            return response()->json(['players' => []]);
        }

        $duration = (int) ($tournament->match_duration_minutes ?: 75);
        $tz = 'America/Mexico_City';

        // All matches in the tournament with both pairs + players, for name matching.
        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->with([
                'category:id,name',
                'court:id,name',
                'pairA.player1:id,name',
                'pairA.player2:id,name',
                'pairB.player1:id,name',
                'pairB.player2:id,name',
            ])
            ->get();

        $dayLabel = fn(string $ymd) => \Illuminate\Support\Str::ucfirst(
            \Carbon\Carbon::parse($ymd, $tz)->locale('es')->isoFormat('ddd DD MMM')
        );

        // Collect, per normalized player name, the matches they appear in + display name.
        $players = []; // normName => ['name'=>, 'matches'=>[GameMatch,...]]
        foreach ($matches as $m) {
            foreach ([$m->pairA, $m->pairB] as $pair) {
                if (! $pair) continue;
                foreach ([$pair->player1, $pair->player2] as $p) {
                    if (! $p) continue;
                    $key = \App\Models\Player::normalize($p->name);
                    if (! isset($windows[$key])) continue; // only rule-bearing players
                    $players[$key] ??= ['name' => $p->name, 'matches' => []];
                    $players[$key]['matches'][$m->id] = $m; // dedupe by id
                }
            }
        }

        $out = [];
        foreach ($players as $key => $info) {
            $rulesByDay = $windows[$key];
            $matchRows = [];
            $okCount = $errCount = $pendingCount = 0;

            foreach ($info['matches'] as $m) {
                $opponent = $m->sideLabel('a') . ' vs ' . $m->sideLabel('b');
                $ctx = $m->contextLabel();

                if (! $m->starts_at) {
                    $pendingCount++;
                    $matchRows[] = [
                        'status' => 'pending',
                        'reason' => 'Sin programar',
                        'context' => $ctx,
                        'label' => $opponent,
                        'when' => null,
                        'court' => $m->court?->name,
                    ];
                    continue;
                }

                $local = $m->starts_at->timezone($tz);
                $ymd = $local->format('Y-m-d');
                $rule = $rulesByDay[$ymd] ?? null;
                [$status, $reason] = $this->validateAgainstRule($local, $duration, $rule);

                if ($status === 'ok') $okCount++;
                else $errCount++;

                $matchRows[] = [
                    'status'  => $status,
                    'reason'  => $reason,
                    'context' => $ctx,
                    'label'   => $opponent,
                    'when'    => $local->locale('es')->isoFormat('ddd DD MMM · HH:mm'),
                    'court'   => $m->court?->name,
                ];
            }

            // Rules as readable strings for the header.
            ksort($rulesByDay);
            $ruleStrings = [];
            foreach ($rulesByDay as $ymd => $win) {
                if (is_array($win) && ! empty($win['off'])) {
                    $ruleStrings[] = $dayLabel($ymd) . ': no disponible';
                    continue;
                }
                $from = is_array($win) ? ($win['from'] ?? null) : $win;
                $until = is_array($win) ? ($win['until'] ?? null) : null;
                if (! $from) continue;
                $ruleStrings[] = $until ? $dayLabel($ymd) . " {$from}–{$until}" : $dayLabel($ymd) . " desde {$from}";
            }

            // Order matches within a player: errors first, then pending, then ok.
            $rank = ['error' => 0, 'pending' => 1, 'ok' => 2];
            usort($matchRows, fn($a, $b) => $rank[$a['status']] <=> $rank[$b['status']]);

            $out[] = [
                'name'    => $info['name'],
                'rules'   => $ruleStrings,
                'ok'      => $okCount,
                'errors'  => $errCount,
                'pending' => $pendingCount,
                'matches' => $matchRows,
            ];
        }

        // Players with problems first, then pending-only, then all-ok; then name.
        usort($out, function ($a, $b) {
            $sev = fn($x) => $x['errors'] > 0 ? 0 : ($x['pending'] > 0 ? 1 : 2);
            return [$sev($a), strtolower($a['name'])] <=> [$sev($b), strtolower($b['name'])];
        });

        return response()->json(['players' => $out]);
    }

    /**
     * Validate one match's local start against a day's rule.
     * @return array{0:string,1:string}  [status(ok|error), reason]
     */
    private function validateAgainstRule(\Carbon\Carbon $start, int $durationMin, ?array $rule): array
    {
        if ($rule === null) return ['ok', 'Sin regla ese día'];
        if (! empty($rule['off'])) return ['error', 'Jugador no disponible ese día'];

        $end = $start->copy()->addMinutes($durationMin);
        $day = $start->format('Y-m-d');
        $tz = 'America/Mexico_City';

        if (! empty($rule['from'])) {
            $fromDt = \Carbon\Carbon::parse("{$day} {$rule['from']}", $tz);
            if ($start->lt($fromDt)) return ['error', "Empieza antes de {$rule['from']}"];
        }
        if (! empty($rule['until'])) {
            $untilDt = \Carbon\Carbon::parse("{$day} {$rule['until']}", $tz);
            if ($end->gt($untilDt)) return ['error', "Termina después de {$rule['until']}"];
        }
        return ['ok', 'Dentro del horario'];
    }

    public function exportEliminationPdf(
        Request $request,
        Tournament $tournament,
        \App\Services\Tournament\GhostQualifierResolver $ghost,
    ) {
        $this->authorize('view', $tournament);

        $categories = $tournament->categories()
            ->with(['groups'])
            ->orderBy('name')
            ->get();

        $out = [];
        foreach ($categories as $category) {
            // Only categories that HAVE a bracket (hybrid / elimination).
            $bracket = GameMatch::where('category_id', $category->id)
                ->whereNull('group_id')          // bracket matches have no group
                ->with([
                    'pairA.player1',
                    'pairA.player2',
                    'pairB.player1',
                    'pairB.player2',
                    'feederA',
                    'feederB',
                ])
                ->orderBy('round')->orderBy('slot')->orderBy('id')
                ->get();

            if ($bracket->isEmpty()) continue;

            $out[] = [
                'category' => $category,
                'rounds' => $bracket->groupBy('round'),          // [round => matches]
                'ghost' => $ghost->mapFor($category),            // [seedLabel => pairName]
            ];
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.schedule.pdf-elimination', [
            'tournament' => $tournament,
            'categories' => $out,
            'generatedAt' => now('America/Mexico_City'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-eliminacion.pdf');
    }

    /** Save the tournament's phase windows + min rest gap. */
    public function savePhaseWindows(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $keys = \App\Support\SchedulePhase::keys();
        $data = $request->validate([
            'min_rest_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'windows' => ['array'],
            'windows.*.phase' => ['required', 'string', 'in:' . implode(',', $keys)],
            'windows.*.starts_at' => ['nullable', 'date'],
            'windows.*.ends_at' => ['nullable', 'date', 'after:windows.*.starts_at'],
        ]);

        $tournament->update(['min_rest_minutes' => $data['min_rest_minutes']]);

        // Replace all windows with the submitted set (only rows with both times).
        $tournament->phaseWindows()->delete();
        foreach ($data['windows'] ?? [] as $w) {
            if (empty($w['starts_at']) || empty($w['ends_at'])) continue;
            $tournament->phaseWindows()->create([
                'phase' => $w['phase'],
                'starts_at' => \Carbon\Carbon::parse($w['starts_at'], 'America/Mexico_City'),
                'ends_at' => \Carbon\Carbon::parse($w['ends_at'], 'America/Mexico_City'),
            ]);
        }

        return back()->with('status', 'Ventanas de fase guardadas.');
    }

    /**
     * Swap or move a match to a target court within the SAME date+slot.
     *
     * Request payload (from schedule.js):
     *   match_id        int    the match being dragged
     *   target_court_id int    the court it's being dropped onto
     *   date            string Y-m-d (the visible day)
     *   slot            string H:i (the slot label the drop landed in)
     *
     * Returns JSON:
     *   { ok: true, action: 'move'|'swap',
     *     moved: [{id, court_id, court_name}], }
     */
    public function switchCourt(Request $request, Tournament $tournament)
    {
        $data = $request->validate([
            'match_id'        => ['required', 'integer'],
            'target_court_id' => ['required', 'integer'],
            'date'            => ['required', 'date_format:Y-m-d'],
            'slot'            => ['required', 'date_format:H:i'],
        ]);

        /** @var GameMatch $match */
        $match = $tournament->matches()->whereKey($data['match_id'])->firstOrFail();

        // The court must belong to this tournament (via its venue). Reuse whatever
        // guard place() already uses; this mirrors the common pattern.
        $targetCourtId = (int) $data['target_court_id'];
        abort_unless(
            $tournament->courts()->whereKey($targetCourtId)->exists(),
            422,
            'Cancha inválida.'
        );

        // No-op: dropped back on its own court.
        if ((int) $match->court_id === $targetCourtId) {
            return response()->json([
                'ok'     => true,
                'action' => 'noop',
                'moved'  => [],
            ]);
        }

        // Is there a match already occupying target court in this exact date+slot?
        // We compare against the SAME slot window the board uses. The simplest and
        // most reliable check: another scheduled match on the target court whose
        // local start time falls in the same slot on the same day.
        //
        // starts_at is stored UTC; we compare in America/Mexico_City to match the
        // board's bucketing. Rather than recompute slot windows here, we rely on the
        // dragged match's own starts_at as the canonical slot time and look for a
        // match on the target court sharing that same starts_at.
        // $occupant = $tournament->matches()
        //     ->where('court_id', $targetCourtId)
        //     ->whereNotNull('starts_at')
        //     ->where('starts_at', $match->starts_at) // exact same slot instant
        //     ->where('id', '!=', $match->id)
        //     ->first();
        $occupant = $tournament->matches()
            ->where('game_matches.court_id', $targetCourtId)
            ->whereNotNull('game_matches.starts_at')
            ->where('game_matches.starts_at', $match->starts_at)
            ->where('game_matches.id', '!=', $match->id)
            ->first();

        return DB::transaction(function () use ($match, $targetCourtId, $occupant, $request) {
            $moved = [];

            if ($occupant) {
                // ---- SWAP: the two matches trade courts (times unchanged) --------
                $fromCourtId = (int) $match->court_id;

                $beforeMatch    = ['court_id' => $match->court_id];
                $beforeOccupant = ['court_id' => $occupant->court_id];

                $match->court_id    = $targetCourtId;
                $occupant->court_id = $fromCourtId;
                $match->save();
                $occupant->save();

                $this->auditCourtChange($match, $beforeMatch, $request, 'switch-court (swap)');
                $this->auditCourtChange($occupant, $beforeOccupant, $request, 'switch-court (swap)');

                $moved[] = $this->courtMovePayload($match);
                $moved[] = $this->courtMovePayload($occupant);

                $action = 'swap';
            } else {
                // ---- MOVE: reassign to the empty target court --------------------
                $before = ['court_id' => $match->court_id];
                $match->court_id = $targetCourtId;
                $match->save();

                $this->auditCourtChange($match, $before, $request, 'switch-court (move)');

                $moved[] = $this->courtMovePayload($match);
                $action = 'move';
            }

            return response()->json([
                'ok'     => true,
                'action' => $action,
                'moved'  => $moved,
            ]);
        });
    }

    public function swapMatches(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = $request->validate([
            'match_id'        => ['required', 'integer'],
            'target_match_id' => ['required', 'integer', 'different:match_id'],
            'force'           => ['nullable', 'boolean'],
        ]);

        $scope = fn($q) => $q->where('tournament_id', $tournament->id);

        /** @var GameMatch $a */
        $a = GameMatch::whereHas('category', $scope)->findOrFail($data['match_id']);
        /** @var GameMatch $b */
        $b = GameMatch::whereHas('category', $scope)->findOrFail($data['target_match_id']);

        // Both must currently be placed (have a court + start time) to swap.
        if (! $a->starts_at || ! $a->court_id || ! $b->starts_at || ! $b->court_id) {
            return response()->json([
                'ok' => false,
                'conflicts' => ['Ambos partidos deben estar programados para intercambiarlos.'],
            ], 422);
        }

        $duration = $tournament->match_duration_minutes ?: 60;

        // Remember originals (for rollback + audit).
        $aOrig = ['court_id' => $a->court_id, 'starts_at' => $a->starts_at->copy()];
        $bOrig = ['court_id' => $b->court_id, 'starts_at' => $b->starts_at->copy()];

        // Manual transaction so we can validate the tentative swap and roll it
        // back cleanly when blocked (returning a normal 422 response, not an
        // exception). DB::transaction()'s closure can't call rollBack() itself.
        DB::beginTransaction();
        try {
            // --- Apply the swap tentatively -------------------------------------
            $a->court_id  = $bOrig['court_id'];
            $a->starts_at = $bOrig['starts_at'];
            $a->duration_minutes = $duration;
            $a->save();

            $b->court_id  = $aOrig['court_id'];
            $b->starts_at = $aOrig['starts_at'];
            $b->duration_minutes = $duration;
            $b->save();

            // --- Re-check BOTH at their new positions ---------------------------
            $aCourt = Court::find($a->court_id);
            $bCourt = Court::find($b->court_id);

            $conflicts = [];

            if ($aCourt) {
                $conflicts = array_merge(
                    $conflicts,
                    $this->prefixConflicts($a, $this->scheduler->conflictsFor($a, $aCourt, $a->starts_at, $duration)),
                    $this->prefixConflicts($a, $this->scheduler->restWarningsFor($a, $a->starts_at, $duration)),
                );
            }
            if ($bCourt) {
                $conflicts = array_merge(
                    $conflicts,
                    $this->prefixConflicts($b, $this->scheduler->conflictsFor($b, $bCourt, $b->starts_at, $duration)),
                    $this->prefixConflicts($b, $this->scheduler->restWarningsFor($b, $b->starts_at, $duration)),
                );
            }

            // De-duplicate identical lines (both matches may report the same shared clash).
            $conflicts = array_values(array_unique($conflicts));

            // Blocked unless forced → roll back and report.
            if (! empty($conflicts) && ! $request->boolean('force')) {
                DB::rollBack();
                return response()->json(['ok' => false, 'conflicts' => $conflicts], 422);
            }

            // Commit: write audit rows for both.
            $this->auditCourtChange($a, ['court_id' => $aOrig['court_id']], $request, 'swap-match');
            $this->auditCourtChange($b, ['court_id' => $bOrig['court_id']], $request, 'swap-match');

            DB::commit();

            return response()->json([
                'ok'       => true,
                'action'   => 'swap',
                'warnings' => $conflicts,
                'moved'    => [$this->courtMovePayload($a), $this->courtMovePayload($b)],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Prefix each conflict line with the match's context so the manager can tell
     * WHICH of the two swapped matches a conflict belongs to.
     */
    private function prefixConflicts(GameMatch $m, array $lines): array
    {
        if (empty($lines)) return [];
        $label = method_exists($m, 'contextLabel') ? $m->contextLabel() : ('Partido #' . $m->id);
        return array_map(fn($l) => "{$label}: {$l}", $lines);
    }

    /** Small helper: shape a moved match for the JS response. */
    private function courtMovePayload(GameMatch $m): array
    {
        return [
            'id'         => $m->id,
            'court_id'   => $m->court_id,
            'court_name' => $m->court?->name ?? optional($m->fresh('court')->court)->name,
        ];
    }

    /**
     * Write a match_audit row for a court change.
     * Adjust the model name / mass-assignment to match your existing audit writes
     * elsewhere in ScheduleController (kept consistent with your columns).
     */
    private function auditCourtChange(GameMatch $m, array $before, Request $request, string $note): void
    {
        \App\Models\MatchAudit::create([
            'game_match_id' => $m->id,
            'user_id'       => $request->user()?->id,
            'action'        => 'court_switch',
            'before'        => $before,                    // {court_id: old}
            'after'         => ['court_id' => $m->court_id],
            'note'          => $note,
        ]);
    }
    private function playerMatchIndex(Tournament $tournament): array
    {
        $tz = 'America/Mexico_City';

        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->with([
                'category:id,name',
                'court:id,name',
                'pairA.player1:id,name',
                'pairA.player2:id,name',
                'pairB.player1:id,name',
                'pairB.player2:id,name',
            ])
            ->orderBy('starts_at')
            ->get();

        $index = []; // key => ['key','name','count','matches'=>[]]

        foreach ($matches as $m) {
            $day = $m->starts_at->timezone($tz);
            $dayYmd = $day->format('Y-m-d');
            $time = $day->format('H:i');
            $dtLabel = \Illuminate\Support\Str::ucfirst($day->locale('es')->isoFormat('ddd D MMM')) . ' · ' . $time;
            $catName = $m->category?->name ?? '';
            $courtName = $m->court?->name ?? 'Sin cancha';

            // For each side, each player's PARTNER is the other player on the same pair.
            $sides = [
                ['pair' => $m->pairA],
                ['pair' => $m->pairB],
            ];

            foreach ($sides as $side) {
                $pair = $side['pair'];
                if (! $pair) continue;

                $members = array_values(array_filter([$pair->player1, $pair->player2]));
                foreach ($members as $p) {
                    if (blank($p->name)) continue;

                    $key = \App\Models\Player::normalize($p->name);
                    $partner = null;
                    foreach ($members as $other) {
                        if ($other->id !== $p->id && filled($other->name)) {
                            $partner = $other->name;
                            break;
                        }
                    }

                    $index[$key] ??= ['key' => $key, 'name' => $p->name, 'count' => 0, 'matches' => []];
                    $index[$key]['count']++;
                    $index[$key]['matches'][] = [
                        'match_id' => $m->id,
                        'category' => $catName,
                        'partner'  => $partner,          // null = singles / partner unknown
                        'court'    => $courtName,
                        'day'      => $dayYmd,
                        'time'     => $time,
                        'label'    => $dtLabel,
                    ];
                }
            }
        }

        // Stable order: by display name.
        $out = array_values($index);
        usort($out, fn($a, $b) => \Illuminate\Support\Str::lower($a['name']) <=> \Illuminate\Support\Str::lower($b['name']));

        return $out;
    }

    protected function possibleR2Matches($pairIds, \App\Models\Tournament $tournament): array
    {
        $pairIds = collect($pairIds)->map(fn($v) => (int) $v)->all();
        if (empty($pairIds)) return [];

        $tz = 'America/Mexico_City';

        // 1) The player's UNPLAYED R1 bracket matches (their pair is bound, no result yet).
        $r1 = \App\Models\GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNull('group_id') // bracket only
            ->where(fn($q) => $q->whereIn('pair_a_id', $pairIds)->orWhereIn('pair_b_id', $pairIds))
            ->whereNull('winner_pair_id') // not decided yet
            ->with(['category:id,name', 'pairA', 'pairB'])
            ->get();

        if ($r1->isEmpty()) return [];
        $r1ById = $r1->keyBy('id');

        // 2) R2 matches fed by any of those R1 matches, still unbound on the fed side.
        $r1Ids = $r1->pluck('id')->all();

        $r2 = \App\Models\GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNull('group_id')
            ->where(fn($q) => $q->whereIn('feeder_a_id', $r1Ids)->orWhereIn('feeder_b_id', $r1Ids))
            ->with([
                'category:id,name',
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

            $out[] = [
                'match_id' => $m->id,
                'category' => $m->category?->name ?? '',
                'round'    => $m->bracketRoundName(),
                'when'     => $whenLabel,                 // null when unscheduled
                'day'      => $day?->format('Y-m-d'),
                'time'     => $day?->format('H:i'),
                'court'    => $m->court?->name,
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
}
