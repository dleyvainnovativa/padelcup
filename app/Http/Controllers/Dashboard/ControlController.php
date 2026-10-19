<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\Tournament\ControlTableService;
use Illuminate\Http\Request;

/**
 * Mesa de control — the event-day board. Shows scheduled matches with a running
 * status (pendiente → llamado → jugando → terminado), a live delay, and the
 * per-player payment checkboxes. The page soft-refreshes every 30s.
 *
 * Status timestamps are independent of the competition `state`: calling/starting
 * a match never captures a score (that stays in the results flow), and finishing
 * here just stamps ended_at.
 */
class ControlController extends Controller
{
    public function __construct(private ControlTableService $control) {}

    public function index(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $data = $this->control->build($tournament);

        return view('dashboard.control.index', [
            'tournament' => $tournament,
            'byDay' => $data['byDay'],
            'counts' => $data['counts'],
            'delay' => $data['delay'],
            'days' => $data['days'],
        ]);
    }

    /**
     * Set a match's mesa status. Stamps the matching timestamp (keeping an
     * earlier real one) and clears later stamps when stepping back.
     */
    public function setStatus(Request $request, Tournament $tournament, GameMatch $match)
    {
        $this->authorize('update', $tournament);
        abort_unless($match->category->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:pending,called,playing,finished'],
        ]);

        $this->applyStatus($match, $data['status']);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $match->controlStatus(),
                'label' => $match->controlStatusLabel(),
                'delay' => $match->delayMinutes(),
            ]);
        }

        return back()->with('status', 'Estado actualizado.');
    }

    /**
     * Apply a status by stamping/clearing the control timestamps. Earlier real
     * stamps are preserved (?? $now) so moving forward keeps the true call/start
     * times; moving back to an earlier status clears the later stamps.
     */
    private function applyStatus(GameMatch $match, string $status): void
    {
        $now = now();

        $stamps = match ($status) {
            'pending' => [
                'called_at' => null,
                'started_at' => null,
                'ended_at' => null,
            ],
            'called' => [
                'called_at' => $match->called_at ?? $now,
                'started_at' => null,
                'ended_at' => null,
            ],
            'playing' => [
                'called_at' => $match->called_at ?? $now,
                'started_at' => $match->started_at ?? $now,
                'ended_at' => null,
            ],
            'finished' => [
                'called_at' => $match->called_at ?? $now,
                'started_at' => $match->started_at ?? $now,
                'ended_at' => $match->ended_at ?? $now,
            ],
        };

        $match->forceFill($stamps)->save();
    }
}
