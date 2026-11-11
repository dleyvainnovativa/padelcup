<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tournament;

/**
 * "Pantalla" builder: a manager screen to pick venues + parameters and generate
 * the public kiosk URL (/t/{tournament}/pantalla?…) to bookmark on a TV. The
 * display itself is URL-param driven, so there is nothing to persist here.
 */
class DisplayConfigController extends Controller
{
    public function edit(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $venues = $tournament->venues()->orderBy('name')->get(['id', 'name']);

        // Play days for the optional "pin a date" selector.
        $days = $tournament->playDays()->map(fn($d) => [
            'value' => $d->format('Y-m-d'),
            'label' => ucfirst($d->locale('es')->translatedFormat('l d M')),
        ])->values();

        return view('dashboard.display.config', [
            'tournament' => $tournament,
            'venues'     => $venues,
            'days'       => $days,
            'displayBase' => route('public.display', $tournament),
        ]);
    }
}
