<?php

namespace App\Services\Tournament;

use App\Enums\MatchResultType;
use App\Enums\MatchState;
use App\Models\GameMatch;
use App\Models\MatchAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Match results: scoring, the propose → confirm state machine, the tournament
 * lock on first confirmed result, walkover/retirement handling, and bracket
 * progression (advancing winners). Editing a confirmed result re-propagates
 * the bracket and is fully audited.
 */
class ResultService
{
    public function __construct(private BracketService $brackets) {}

    /**
     * Validate a sets array for a best-of-3 match.
     * sets = [[gamesA, gamesB], ...] — 2 or 3 sets, each 0..7.
     * Returns the winner pair id, or throws on an inconsistent score.
     */
    public function resolveWinner(GameMatch $match, array $sets): int
    {
        $sets = array_values(array_filter(
            $sets,
            fn($s) =>
            isset($s[0], $s[1]) && ($s[0] !== '' || $s[1] !== '')
        ));

        if (count($sets) < 2 || count($sets) > 3) {
            throw ValidationException::withMessages(['sets' => 'Un partido se decide a 2 o 3 sets.']);
        }

        $aSets = $bSets = 0;
        foreach ($sets as $i => $s) {
            $ga = (int) $s[0];
            $gb = (int) $s[1];
            if ($ga === $gb) {
                throw ValidationException::withMessages(['sets' => "El set " . ($i + 1) . " no puede quedar empatado."]);
            }
            if ($ga < 0 || $gb < 0 || $ga > 7 || $gb > 7) {
                throw ValidationException::withMessages(['sets' => "Marcador inválido en el set " . ($i + 1) . "."]);
            }
            $ga > $gb ? $aSets++ : $bSets++;
        }

        if ($aSets === $bSets) {
            throw ValidationException::withMessages(['sets' => 'El marcador no define un ganador.']);
        }

        return $aSets > $bSets ? $match->pair_a_id : $match->pair_b_id;
    }

    /**
     * Propose a result (player or manager). Stores the score and moves the
     * match to "proposed" — awaiting manager confirmation. Manager proposals
     * can be confirmed in the same step (see confirm()).
     */
    public function propose(GameMatch $match, array $sets, User $by): GameMatch
    {
        $winnerId = $this->resolveWinner($match, $sets);

        return DB::transaction(function () use ($match, $sets, $winnerId, $by) {
            $before = $match->only(['state', 'sets', 'winner_pair_id']);

            $match->update([
                'sets' => $this->normalizeSets($sets),
                'winner_pair_id' => $winnerId,
                'result_type' => MatchResultType::Normal,
                'state' => MatchState::Proposed,
                'proposed_by' => $by->id,
            ]);

            $this->audit($match, $by, 'proposed', $before);

            return $match->fresh();
        });
    }

    /**
     * Confirm a result (manager only). Locks the tournament if this is the
     * first confirmed result, and advances the bracket winner if applicable.
     * If $sets is given, it sets/overrides the score before confirming
     * (manager entering directly).
     */
    public function confirm(GameMatch $match, User $by, ?array $sets = null): GameMatch
    {
        return DB::transaction(function () use ($match, $by, $sets) {
            $before = $match->only(['state', 'sets', 'winner_pair_id']);

            if ($sets !== null) {
                $winnerId = $this->resolveWinner($match, $sets);
                $match->sets = $this->normalizeSets($sets);
                $match->winner_pair_id = $winnerId;
                $match->result_type = MatchResultType::Normal;
            }

            if (! $match->winner_pair_id) {
                throw ValidationException::withMessages(['result' => 'No hay un resultado para confirmar.']);
            }

            $match->state = MatchState::Confirmed;
            $match->confirmed_by = $by->id;
            $match->confirmed_at = now();
            $match->save();

            $this->audit($match, $by, 'confirmed', $before);

            // Lock the tournament on the first confirmed result.
            $match->category->tournament->lock();

            // Bracket progression (group_id null).
            if ($match->group_id === null) {
                $this->brackets->advanceWinner($match->fresh());
            }

            // Mexicano (or any group match with feeders): fill round-2 slots.
            $this->resolveFeeders($match->fresh());

            return $match->fresh();
        });
    }

    /**
     * Record a walkover / retirement / default. Winner is given explicitly;
     * the loser conceded. Walkover no-show uses a conventional 6-3 6-3 score.
     */
    public function recordSpecial(
        GameMatch $match,
        int $winnerPairId,
        MatchResultType $type,
        User $by,
        ?string $note = null,
    ): GameMatch {
        return DB::transaction(function () use ($match, $winnerPairId, $type, $by, $note) {
            $before = $match->only(['state', 'sets', 'winner_pair_id', 'result_type']);

            $loserIsA = $winnerPairId === $match->pair_b_id;
            // Conventional walkover score 6-3 6-3 from the winner's perspective.
            $winSet = [6, 3];
            $sets = $type === MatchResultType::Walkover
                ? [$loserIsA ? array_reverse($winSet) : $winSet, $loserIsA ? array_reverse($winSet) : $winSet]
                : null;

            $match->update([
                'sets' => $sets,
                'winner_pair_id' => $winnerPairId,
                'result_type' => $type,
                'incident_note' => $note,
                'state' => MatchState::Confirmed,
                'confirmed_by' => $by->id,
                'confirmed_at' => now(),
            ]);

            $this->audit($match, $by, 'confirmed', $before, $note);

            $match->category->tournament->lock();

            if ($match->group_id === null) {
                $this->brackets->advanceWinner($match->fresh());
            }

            return $match->fresh();
        });
    }

    /**
     * Edit an already-confirmed result (manager). Re-resolves the winner and,
     * for bracket matches, re-propagates downstream if the winner changed.
     */
    public function edit(GameMatch $match, array $sets, User $by): GameMatch
    {
        return DB::transaction(function () use ($match, $sets, $by) {
            $before = $match->only(['state', 'sets', 'winner_pair_id']);
            $oldWinner = $match->winner_pair_id;

            $winnerId = $this->resolveWinner($match, $sets);
            $match->update([
                'sets' => $this->normalizeSets($sets),
                'winner_pair_id' => $winnerId,
                'result_type' => MatchResultType::Normal,
            ]);

            $this->audit($match, $by, 'edited', $before);

            // Bracket: if the winner changed, re-propagate downstream.
            if ($match->group_id === null && $oldWinner !== $winnerId) {
                $this->repropagate($match->fresh());
            }

            // Mexicano group feeder: if a round-1 winner changed, re-resolve the
            // round-2 participants and clear any now-stale round-2 results.
            if ($match->group_id !== null && $oldWinner !== $winnerId) {
                $this->clearFedResults($match);
                $this->resolveFeeders($match->fresh());
            }

            return $match->fresh();
        });
    }

    /** Clear results of matches fed by $match (their participants will change). */
    private function clearFedResults(GameMatch $match): void
    {
        $fed = GameMatch::where('feeder_a_id', $match->id)
            ->orWhere('feeder_b_id', $match->id)->get();
        foreach ($fed as $child) {
            if ($child->winner_pair_id || $child->state !== MatchState::Scheduled) {
                $child->update([
                    'winner_pair_id' => null,
                    'sets' => null,
                    'state' => MatchState::Scheduled,
                    'result_type' => MatchResultType::Normal,
                    'confirmed_by' => null,
                    'confirmed_at' => null,
                ]);
            }
        }
    }

    /**
     * Fill any match fed by $match's result, choosing winner or loser per the
     * feeder source. Used by Mexicano round-2 (and any feeder-linked group
     * match). Bracket matches use BracketService::advanceWinner separately, but
     * this also handles the generic winner case harmlessly if both run.
     */
    private function resolveFeeders(GameMatch $match): void
    {
        if (! $match->winner_pair_id) return;

        $winnerId = $match->winner_pair_id;
        $loserId = $match->loserPairId();

        // Matches fed on slot A by this match.
        foreach (GameMatch::where('feeder_a_id', $match->id)->get() as $child) {
            $pairId = $child->feeder_a_source === 'loser' ? $loserId : $winnerId;
            if ($child->pair_a_id !== $pairId) {
                $child->update(['pair_a_id' => $pairId]);
            }
        }
        // Matches fed on slot B by this match.
        foreach (GameMatch::where('feeder_b_id', $match->id)->get() as $child) {
            $pairId = $child->feeder_b_source === 'loser' ? $loserId : $winnerId;
            if ($child->pair_b_id !== $pairId) {
                $child->update(['pair_b_id' => $pairId]);
            }
        }
    }

    /**
     * Re-propagate a bracket match's winner downstream, clearing stale results
     * in affected descendant matches (their participants changed).
     */
    private function repropagate(GameMatch $match): void
    {
        $parent = GameMatch::where('feeder_a_id', $match->id)->first();
        $slot = 'pair_a_id';
        if (! $parent) {
            $parent = GameMatch::where('feeder_b_id', $match->id)->first();
            $slot = 'pair_b_id';
        }
        if (! $parent) return;

        // If the parent already had a result, it's now invalid (participant changed).
        $hadResult = $parent->winner_pair_id !== null;

        $parent->update([
            $slot => $match->winner_pair_id,
            'winner_pair_id' => null,
            'sets' => null,
            'state' => MatchState::Scheduled,
            'result_type' => MatchResultType::Normal,
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);

        // Cascade: clear further descendants if the parent had propagated.
        if ($hadResult) {
            $this->repropagate($parent->fresh());
        }
    }

    private function normalizeSets(array $sets): array
    {
        return array_values(array_map(
            fn($s) => [(int) $s[0], (int) $s[1]],
            array_filter($sets, fn($s) => isset($s[0], $s[1]) && ($s[0] !== '' || $s[1] !== ''))
        ));
    }

    private function audit(GameMatch $match, User $by, string $action, array $before, ?string $note = null): void
    {
        MatchAudit::create([
            'game_match_id' => $match->id,
            'user_id' => $by->id,
            'action' => $action,
            'before' => $before,
            'after' => $match->only(['state', 'sets', 'winner_pair_id', 'result_type']),
            'note' => $note,
        ]);
    }
}
