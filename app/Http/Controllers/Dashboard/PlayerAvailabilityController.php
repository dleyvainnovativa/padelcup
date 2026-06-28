<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Pair;
use App\Models\Player;
use App\Models\PlayerAvailability;
use App\Models\Tournament;
use Illuminate\Http\Request;

class PlayerAvailabilityController extends Controller
{
    /** Tournament-level availability screen: every player (deduped by name). */
    public function index(Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        // Every player in the tournament, deduped by normalized name, with the
        // categories they appear in (for context).
        $pairs = Pair::whereHas('category', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->with(['category:id,name', 'player1:id,name', 'player2:id,name'])
            ->get();

        $people = []; // normalized_name => ['name' => display, 'categories' => []]
        foreach ($pairs as $pair) {
            $catName = $pair->category?->name;
            foreach ([$pair->player1, $pair->player2] as $p) {
                if (! $p) continue;
                $key = Player::normalize($p->name);
                $people[$key] ??= ['key' => $key, 'name' => $p->name, 'categories' => []];
                if ($catName && ! in_array($catName, $people[$key]['categories'], true)) {
                    $people[$key]['categories'][] = $catName;
                }
            }
        }

        $people = collect($people)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        // Existing availability map + tournament play days.
        $availability = PlayerAvailability::mapFor($tournament);
        $playDays = collect($tournament->playDays())->map(fn ($d) => [
            'ymd' => $d->format('Y-m-d'),
            'label' => \Illuminate\Support\Str::ucfirst($d->locale('es')->isoFormat('ddd D MMM')),
        ]);

        return view('dashboard.availability.index', [
            'tournament' => $tournament,
            'people' => $people,
            'availability' => $availability,
            'playDays' => $playDays,
        ]);
    }

    /** Save (or clear) one person's availability for one day. */
    public function store(Request $request, Tournament $tournament)
    {
        $this->authorize('update', $tournament);

        $validDays = collect($tournament->playDays())->map->format('Y-m-d')->all();

        $data = $request->validate([
            'normalized_name' => ['required', 'string', 'max:255'],
            'day' => ['required', 'date', 'in:' . implode(',', $validDays)],
            'earliest_time' => ['nullable', 'date_format:H:i'],
        ]);

        $key = ['tournament_id' => $tournament->id, 'normalized_name' => $data['normalized_name'], 'day' => $data['day']];

        if (blank($data['earliest_time'] ?? null)) {
            // Empty time → clear the rule for that day.
            PlayerAvailability::where($key)->delete();
            $msg = 'Disponibilidad quitada.';
        } else {
            PlayerAvailability::updateOrCreate($key, ['earliest_time' => $data['earliest_time']]);
            $msg = 'Disponibilidad guardada.';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => $msg]);
        }

        return back()->with('status', $msg);
    }
}
