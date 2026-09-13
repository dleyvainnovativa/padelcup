<?php

namespace App\Services\Tournament;

use App\Models\GameMatch;
use App\Models\MatchPrediction;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Score-prediction game ("quiniela"). A logged-in user guesses the exact set
 * score of a match before it locks; when the official result is confirmed, the
 * prediction scores 1 point if every set matches exactly, else 0.
 */
class PredictionService
{
    /**
     * Is prediction still open for this match?
     * Locks at kickoff (starts_at passed) or once confirmed — whichever first.
     */
    public function isOpen(GameMatch $match): bool
    {
        if ($match->isConfirmed()) return false;
        if (! $match->isReady()) return false; // both pairs must be known
        if ($match->starts_at && $match->starts_at->isPast()) return false;
        return true;
    }

    /** Create or update the user's prediction for a match (until it locks). */
    public function predict(GameMatch $match, array $sets, User $user): MatchPrediction
    {
        if (! $this->isOpen($match)) {
            throw ValidationException::withMessages([
                'prediction' => 'Las predicciones para este partido ya están cerradas.',
            ]);
        }

        $clean = $this->normalize($sets);
        if (count($clean) < 2) {
            throw ValidationException::withMessages([
                'prediction' => 'Ingresa el marcador de al menos dos sets.',
            ]);
        }

        return MatchPrediction::updateOrCreate(
            ['game_match_id' => $match->id, 'user_id' => $user->id],
            [
                'tournament_id' => $match->category->tournament_id,
                'sets' => $clean,
                'points' => null,
                'correct' => null,
                'scored_at' => null,
            ],
        );
    }

    /**
     * Score every unscored prediction for a confirmed match. Called after a
     * result is confirmed. Idempotent — only touches unscored rows.
     */
    public function scoreMatch(GameMatch $match): void
    {
        if (! $match->isConfirmed()) return;

        $actual = $this->normalize($match->sets ?? []);

        MatchPrediction::where('game_match_id', $match->id)
            ->whereNull('scored_at')
            ->get()
            ->each(function (MatchPrediction $p) use ($actual) {
                $correct = $this->exactMatch($this->normalize($p->sets), $actual);
                $p->update([
                    'correct' => $correct,
                    'points' => $correct ? 1 : 0,
                    'scored_at' => now(),
                ]);
            });
    }

    /**
     * Per-tournament leaderboard: users ranked by total points, then by number
     * of correct predictions, then earliest activity. Returns rows with
     * user, points, correct, total.
     */
    public function leaderboard(Tournament $tournament, int $limit = 100): array
    {
        return MatchPrediction::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('scored_at')
            ->selectRaw('user_id,
                COALESCE(SUM(points),0) as points,
                SUM(CASE WHEN correct = 1 THEN 1 ELSE 0 END) as correct,
                COUNT(*) as total')
            ->groupBy('user_id')
            ->orderByDesc('points')
            ->orderByDesc('correct')
            ->limit($limit)
            ->with('user:id,name')
            ->get()
            ->map(fn ($r) => [
                'user' => $r->user,
                'points' => (int) $r->points,
                'correct' => (int) $r->correct,
                'total' => (int) $r->total,
            ])
            ->all();
    }

    /** A user's predictions across a tournament (for the player dashboard). */
    public function forUser(Tournament $tournament, User $user)
    {
        return MatchPrediction::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->with(['match.pairA', 'match.pairB', 'match.category'])
            ->latest()
            ->get();
    }

    /* ---------------------------------------------------------------- */

    /** Normalize sets to a clean [[int,int],...], dropping empty rows. */
    private function normalize(array $sets): array
    {
        $out = [];
        foreach ($sets as $set) {
            $a = $set[0] ?? null;
            $b = $set[1] ?? null;
            if ($a === '' || $a === null) $a = null;
            if ($b === '' || $b === null) $b = null;
            if ($a === null && $b === null) continue;
            $out[] = [(int) ($a ?? 0), (int) ($b ?? 0)];
        }
        return $out;
    }

    /** Exact set-by-set equality. */
    private function exactMatch(array $a, array $b): bool
    {
        if (count($a) !== count($b)) return false;
        foreach ($a as $i => $set) {
            if (($set[0] ?? null) !== ($b[$i][0] ?? null)) return false;
            if (($set[1] ?? null) !== ($b[$i][1] ?? null)) return false;
        }
        return true;
    }
}
