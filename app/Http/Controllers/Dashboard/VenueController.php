<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtAvailability;
use App\Models\Tournament;
use App\Models\Venue;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index(Tournament $tournament)
    {
        $this->authorize('update', $tournament);
        $tournament->load('venues.courts.availabilities');

        return view('dashboard.venues.index', compact('tournament'));
    }

    public function storeVenue(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $tournament->venues()->create($data);

        return back()->with('status', 'Sede agregada.');
    }

    public function storeCourt(Request $request, Tournament $tournament, Venue $venue)
    {
        $this->authorize('update', $tournament);
        abort_unless($venue->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $court = $venue->courts()->create([
            'name' => $data['name'],
            'sort_order' => $venue->courts()->count(),
            'is_active' => true,
        ]);

        // Auto-derive availability from the tournament play window across all
        // play days (decision A-i): a court is open during tournament hours.
        $this->seedAvailability($tournament, $court);

        return back()->with('status', 'Cancha agregada con horario del torneo.');
    }

    /**
     * Create one availability window per play day = [play_start, play_end] in
     * CDMX local time. Idempotent-ish: clears existing windows first.
     */
    private function seedAvailability(Tournament $tournament, Court $court): void
    {
        $court->availabilities()->delete();

        $tz = 'America/Mexico_City';
        $start = \Illuminate\Support\Str::of($tournament->play_start)->substr(0, 5); // 'HH:MM'
        $end = \Illuminate\Support\Str::of($tournament->play_end)->substr(0, 5);

        foreach ($tournament->playDays() as $day) {
            $d = $day->format('Y-m-d');
            $court->availabilities()->create([
                'starts_at' => \Carbon\Carbon::parse("$d $start", $tz),
                'ends_at' => \Carbon\Carbon::parse("$d $end", $tz),
            ]);
        }
    }

    /** Re-seed all courts' availability from current tournament settings. */
    public function resyncAvailability(Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        foreach ($tournament->courts as $court) {
            $this->seedAvailability($tournament, $court);
        }

        return back()->with('status', 'Horarios actualizados según la configuración del torneo.');
    }

    public function destroyCourt(Tournament $tournament, Court $court)
    {
        $this->authorize('update', $tournament);
        abort_unless($court->venue->tournament_id === $tournament->id, 404);

        $court->delete();

        return back()->with('status', 'Cancha eliminada.');
    }

    public function storeAvailability(Request $request, Tournament $tournament, Court $court)
    {
        $this->authorize('update', $tournament);
        abort_unless($court->venue->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $court->availabilities()->create($data);

        return back()->with('status', 'Horario agregado.');
    }

    public function destroyAvailability(Tournament $tournament, CourtAvailability $availability)
    {
        $this->authorize('update', $tournament);
        abort_unless($availability->court->venue->tournament_id === $tournament->id, 404);

        $availability->delete();

        return back()->with('status', 'Horario eliminado.');
    }
}
