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
            // Category → datetime. Unscheduled (null starts_at) sort last.
            $grouped = $showable
                ->sortBy([
                    fn($m) => $m->category->name,
                    fn($m) => $m->starts_at ? $m->starts_at->timestamp : PHP_INT_MAX,
                ])
                ->groupBy(fn($m) => $m->category->name);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.schedule.pdf-category', [
                'tournament' => $tournament,
                'byCategory' => $grouped,
                'ghostQualifiers' => $ghostQualifiers,
                'generatedAt' => now('America/Mexico_City'),
            ])->setPaper('a4', 'portrait');

            return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-calendario-categoria.pdf');
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
}
