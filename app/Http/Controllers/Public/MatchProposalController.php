<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\GameMatch;
use App\Services\Tournament\ResultService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Lets a logged-in player propose the score of a match they played, straight
 * from the public tournament page. The proposal moves the match to "proposed"
 * (does NOT count for standings) until the manager confirms or edits it via
 * the existing dashboard flow. All heavy lifting is the existing ResultService.
 */
class MatchProposalController extends Controller
{
    public function __construct(private ResultService $results)
    {
    }

    public function store(Request $request, GameMatch $match)
    {
        $user = $request->user();

        // Eligibility: user must have a linked player in this match, match ready
        // and not yet confirmed. Model helper centralizes the rule.
        if (! $match->canBeProposedBy($user)) {
            $msg = 'No puedes proponer el resultado de este partido.';
            return $request->expectsJson()
                ? response()->json(['message' => $msg], 403)
                : back()->withErrors(['proposal' => $msg]);
        }

        $data = $request->validate([
            'sets' => ['required', 'array', 'min:2', 'max:3'],
            'sets.*.0' => ['nullable', 'integer', 'min:0', 'max:7'],
            'sets.*.1' => ['nullable', 'integer', 'min:0', 'max:7'],
        ], [
            'sets.required' => 'Ingresa el marcador de al menos dos sets.',
            'sets.min' => 'Ingresa el marcador de al menos dos sets.',
        ]);

        try {
            $this->results->propose($match, $data['sets'], $user);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        }

        $ok = 'Resultado propuesto. El organizador lo revisará y confirmará.';
        return $request->expectsJson()
            ? response()->json(['message' => $ok])
            : back()->with('status', $ok);
    }
}