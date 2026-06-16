<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\Tournament\SchedulingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
                'group',
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
            // with two real seed labels (e.g. "Grupo G - 1 vs Grupo J - 1") whose
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
        ]);
    }

    /** Run the greedy auto-scheduler over unscheduled matches. */
    public function auto(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $courts = $tournament->courts()->with('availabilities')->get();
        if ($courts->isEmpty()) {
            return back()->withErrors(['schedule' => 'Agrega canchas y horarios antes de programar.']);
        }

        $duration = (int) $request->input('duration', $tournament->match_duration_minutes ?: 90);
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

        // Conflicts block unless explicitly forced (manager override).
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

        $matches = GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->with(['category', 'group', 'court', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->get();

        if ($order === 'category') {
            // Category → round → datetime.
            $grouped = $matches
                ->sortBy([
                    fn($m) => $m->category->name,
                    fn($m) => (int) ($m->round ?? 0),
                    fn($m) => optional($m->starts_at)->timestamp ?? 0,
                ])
                ->groupBy(fn($m) => $m->category->name);

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.schedule.pdf-category', [
                'tournament' => $tournament,
                'byCategory' => $grouped,
                'generatedAt' => now('America/Mexico_City'),
            ])->setPaper('a4', 'portrait');

            return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-calendario-categoria.pdf');
        }

        // Default: chronological (day → time).
        $byDay = $matches->sortBy(fn($m) => $m->starts_at->timestamp)
            ->groupBy(fn($m) => $m->starts_at->timezone('America/Mexico_City')->format('Y-m-d'));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.schedule.pdf', [
            'tournament' => $tournament,
            'byDay' => $byDay,
            'generatedAt' => now('America/Mexico_City'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(\Illuminate\Support\Str::slug($tournament->name) . '-calendario.pdf');
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
}
