<?php

namespace App\Services\Tournament;

use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Resolves the elimination podium (champion / runner-up / 3rd) for a category,
 * and the whole-tournament roll-up used by the manager "Ganadores" page and its
 * Excel export, plus the public winners page.
 *
 * A category is "decided" only when its final (the single max-round bracket
 * match) is confirmed with a winner. 3rd place is reported ONLY when a real,
 * confirmed 3rd-place match exists — never invented from semifinal losers.
 *
 * Extracted from PublicTournamentController so the public page, the manager page
 * and the export all read the same numbers. The returned array is a superset of
 * what the public view already consumed, so adding player-name detail here is
 * backward-compatible.
 */
class PodiumService
{
    /** @return array<string,mixed> podium for one category (see keys below). */
    public function forCategory(Category $category): array
    {
        $base = [
            'category_id'       => $category->id,
            'category'          => $category->name,
            'decided'           => false,
            'has_bracket'       => $category->format->hasBracket(),
            'champion'          => null,   // pair name
            'runner_up'         => null,   // pair name (null for a bye-final)
            'third'             => null,   // pair name (only if a 3rd-place match exists)
            'final_score'       => null,   // e.g. "6-3 4-6 6-2"
            'champion_players'  => [],     // [player name, …]
            'runner_up_players' => [],
            'third_players'     => [],
        ];

        if (! $category->format->hasBracket()) {
            return $base;
        }

        $bracket = $category->matches()
            ->whereNull('group_id')
            ->with(['pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->orderBy('round')->orderBy('slot')
            ->get();

        if ($bracket->isEmpty()) {
            return $base;
        }

        $maxRound = $bracket->max('round');
        $finalRoundMatches = $bracket->where('round', $maxRound);
        $final = $finalRoundMatches->firstWhere('state.value', 'confirmed')
            ?? $finalRoundMatches->first();

        if (! $final || $final->state->value !== 'confirmed' || ! $final->winner_pair_id) {
            return $base;
        }

        $champPair  = $final->winner_pair_id === $final->pair_a_id ? $final->pairA : $final->pairB;
        $runnerPair = $final->winner_pair_id === $final->pair_a_id ? $final->pairB : $final->pairA;

        $base['decided']           = true;
        $base['champion']          = $champPair?->name() ?? '—';
        $base['runner_up']         = $runnerPair?->name(); // bye-final → null
        $base['final_score']       = $this->formatSets($final->sets);
        $base['champion_players']  = $this->playerNames($champPair);
        $base['runner_up_players'] = $this->playerNames($runnerPair);

        $thirdPair = $this->thirdPlacePair($bracket, $final);
        $base['third']         = $thirdPair?->name();
        $base['third_players'] = $this->playerNames($thirdPair);

        return $base;
    }

    /** @return Collection<int,array<string,mixed>> one podium per category. */
    public function forTournament(Tournament $tournament): Collection
    {
        return $tournament->categories()->orderBy('name')->get()
            ->map(fn (Category $c) => $this->forCategory($c))
            ->values();
    }

    /**
     * Confirmed 3rd-place playoff, if the bracket actually has one: a confirmed
     * match in the SAME round as the final but a different match. Returns the
     * winning Pair, or null when no defined 3rd place exists.
     */
    private function thirdPlacePair($bracket, GameMatch $final)
    {
        $candidate = $bracket
            ->where('round', $final->round)
            ->where('id', '!=', $final->id)
            ->firstWhere('state.value', 'confirmed');

        if ($candidate && $candidate->winner_pair_id) {
            return $candidate->winner_pair_id === $candidate->pair_a_id
                ? $candidate->pairA
                : $candidate->pairB;
        }

        return null;
    }

    /** @return array<int,string> the pair's player names (0–2 entries). */
    private function playerNames($pair): array
    {
        if (! $pair) {
            return [];
        }
        return collect([$pair->player1, $pair->player2])
            ->filter()
            ->map(fn ($p) => $p->name)
            ->values()
            ->all();
    }

    /** "6-3 4-6 6-2" from the sets JSON, or null. */
    public function formatSets(?array $sets): ?string
    {
        if (empty($sets)) {
            return null;
        }
        return collect($sets)
            ->map(fn ($s) => ($s[0] ?? '') . '-' . ($s[1] ?? ''))
            ->implode(' ');
    }
}
