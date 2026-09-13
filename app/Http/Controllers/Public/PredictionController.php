<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\Tournament\PredictionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PredictionController extends Controller
{
    public function __construct(private PredictionService $predictions)
    {
    }

    /** Logged-in user submits/updates a score prediction for a match. */
    public function store(Request $request, GameMatch $match)
    {
        $user = $request->user();

        $data = $request->validate([
            'sets' => ['required', 'array', 'min:2', 'max:3'],
            'sets.*.0' => ['nullable', 'integer', 'min:0', 'max:7'],
            'sets.*.1' => ['nullable', 'integer', 'min:0', 'max:7'],
        ], [
            'sets.required' => 'Ingresa el marcador de al menos dos sets.',
            'sets.min' => 'Ingresa el marcador de al menos dos sets.',
        ]);

        try {
            $this->predictions->predict($match, $data['sets'], $user);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        }

        $msg = '¡Predicción guardada! Suerte 🎾';
        return $request->expectsJson()
            ? response()->json(['message' => $msg])
            : back()->with('status', $msg);
    }

    /** Public per-tournament prediction leaderboard. */
    public function leaderboard(Tournament $tournament)
    {
        $rows = $this->predictions->leaderboard($tournament);

        return view('public.predictions-leaderboard', [
            'tournament' => $tournament,
            'rows' => $rows,
        ]);
    }
}
