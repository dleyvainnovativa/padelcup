<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\StoreTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\Request;

class TournamentController extends Controller
{
    public function index(Request $request)
    {
        $tournaments = Tournament::where('manager_id', $request->user()->id)
            ->withCount('categories')
            ->latest()
            ->paginate(12);

        return view('dashboard.tournaments.index', compact('tournaments'));
    }

    public function create()
    {
        $this->authorize('create', Tournament::class);

        return view('dashboard.tournaments.create');
    }

    public function store(StoreTournamentRequest $request)
    {
        $this->authorize('create', Tournament::class);

        $tournament = Tournament::create([
            ...$request->validated(),
            'manager_id' => $request->user()->id,
            'is_listed' => $request->boolean('is_listed'),
        ]);

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('status', 'Torneo creado.');
    }

    public function show(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $tournament->load(['categories' => fn($q) => $q->withCount([
            'pairs' => fn($p) => $p->whereHas('registration', fn($r) => $r->whereNotIn('status', [
                \App\Enums\RegistrationStatus::Cancelled->value,
                \App\Enums\RegistrationStatus::Withdrawn->value,
            ])),
        ])]);

        return view('dashboard.tournaments.show', compact('tournament'));
    }

    public function edit(Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $scheduledCount = \App\Models\GameMatch::whereHas('category', fn($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('starts_at')
            ->count();

        return view('dashboard.tournaments.edit', compact('tournament', 'scheduledCount'));
    }

    public function update(StoreTournamentRequest $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        // Detect whether scheduling-affecting fields changed (to know if we need
        // to re-derive availability and prune now-invalid placements).
        $schedFields = ['play_start', 'play_end', 'match_duration_minutes', 'starts_on', 'ends_on'];
        $before = $tournament->only($schedFields);

        $data = $request->validated();

        // Cover image upload (optional). Store on the default disk; replace any
        // previous image.
        if ($request->hasFile('cover_image')) {
            $disk = config('filesystems.default');
            if ($tournament->cover_image_path) {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($tournament->cover_image_path);
            }
            $data['cover_image_path'] = $request->file('cover_image')
                ->store('tournament-covers', $disk);
        }

        $tournament->update([
            ...$data,
            'is_listed' => $request->boolean('is_listed'),
        ]);

        $schedChanged = collect($schedFields)->contains(
            fn($f) => (string) data_get($before, $f) !== (string) $tournament->{$f}
        );

        $status = 'Torneo actualizado.';

        if ($schedChanged) {
            // Re-derive each court's availability from the new play window.
            $this->resyncCourtAvailability($tournament);

            // Unschedule only the matches that no longer fit the new grid.
            $moved = app(\App\Services\Tournament\SchedulingService::class)
                ->pruneInvalidSchedule($tournament);

            if ($moved > 0) {
                $status .= " {$moved} " . ($moved === 1 ? 'partido quedó' : 'partidos quedaron')
                    . " fuera del nuevo horario y se enviaron a «Sin programar».";
            }
        }

        return redirect()
            ->route('tournaments.show', $tournament)
            ->with('status', $status);
    }

    /** Re-seed all courts' availability windows from current tournament settings. */
    private function resyncCourtAvailability(Tournament $tournament): void
    {
        $tz = 'America/Mexico_City';
        $start = \Illuminate\Support\Str::of($tournament->play_start)->substr(0, 5);
        $end = \Illuminate\Support\Str::of($tournament->play_end)->substr(0, 5);

        foreach ($tournament->courts as $court) {
            $court->availabilities()->delete();
            foreach ($tournament->playDays() as $day) {
                $d = $day->format('Y-m-d');
                $court->availabilities()->create([
                    'starts_at' => \Carbon\Carbon::parse("$d $start", $tz),
                    'ends_at' => \Carbon\Carbon::parse("$d $end", $tz),
                ]);
            }
        }
    }

    public function destroy(Tournament $tournament)
    {
        $this->authorize('delete', $tournament);

        $tournament->delete();

        return redirect()
            ->route('tournaments.index')
            ->with('status', 'Torneo eliminado.');
    }
}
