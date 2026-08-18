<?php

namespace App\Services\Ranking;

use App\Enums\RankingAchievement;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Group;
use App\Models\Pair;
use App\Models\RankingPoint;
use App\Models\RankingSystem;
use App\Models\Tournament;
use App\Services\Tournament\StandingsService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 — resolves each pair's ranking achievements for a tournament and
 * writes the ranking_points ledger for a given ranking system.
 *
 * Detection is wired to the REAL app methods:
 *   - bracket matches:   ->whereNull('group_id')
 *   - round identity:    GameMatch::phaseKey()  → 'final'|'semifinal'|
 *                        'quarterfinal'|'r16'|'r32'|'groups'
 *   - the final:         phaseKey()==='final' AND is_third_place === false
 *   - 3rd place match:   is_third_place === true (first-class column)
 *   - played match:      state === MatchState::Confirmed (isConfirmed())
 *   - winner/loser:      winner_pair_id / loserPairId()
 *   - group placement:   StandingsService::forGroup(Group) → row 0 = winner
 *   - players of a pair:  Pair::playerIds()
 *
 * Rules (agreed):
 *   - Points awarded to PLAYERS (both members of a pair get a ledger row each).
 *   - "reached X" = the pair APPEARED in a match of that round (won or lost).
 *   - champion ALWAYS counts, even a bye-final with no real opponent.
 *   - phantom finalist NEVER awarded: 'finalist' only when the final had two
 *     real pairs and was confirmed.
 *   - stacking: cumulative (default) sums every achievement a pair earned;
 *     best_only keeps just the single highest-value achievement per player.
 *   - "don't invent": an achievement is only emitted if its round actually
 *     occurred (no semifinal matches ⇒ nobody gets reached_sf).
 *
 * Idempotent: finalizing deletes this (system, tournament) ledger slice and
 * rewrites it, so re-running after an edited result recomputes cleanly.
 */
class RankingFinalizer
{
    public function __construct(private StandingsService $standings) {}

    /**
     * Finalize one tournament for one ranking system. Returns a small summary:
     *   ['rows' => int, 'players' => int, 'categories' => int]
     */
    public function finalize(Tournament $tournament, RankingSystem $system): array
    {
        // Guard: the tournament must actually feed this system (pivot exists).
        $linked = $tournament->rankingSystems()
            ->whereKey($system->id)
            ->exists();
        abort_unless($linked, 422, 'Este torneo no está vinculado a ese ranking.');

        // Build every ledger row in memory first, then persist atomically.
        $ledger = $this->buildLedger($tournament, $system);

        return DB::transaction(function () use ($tournament, $system, $ledger) {
            // Wipe this system's slice for this tournament (idempotent rewrite).
            RankingPoint::where('ranking_system_id', $system->id)
                ->where('tournament_id', $tournament->id)
                ->delete();

            $now = now();
            $playerIds = [];

            foreach ($ledger as $row) {
                $row['awarded_at'] = $now;
                RankingPoint::create($row);
                $playerIds[$row['player_id']] = true;
            }

            // Stamp finalized_at on the pivot for this system.
            $tournament->rankingSystems()->updateExistingPivot($system->id, [
                'finalized_at' => $now,
            ]);

            return [
                'rows'       => count($ledger),
                'players'    => count($playerIds),
                'categories' => collect($ledger)->pluck('category_id')->unique()->count(),
            ];
        });
    }

    /**
     * Build the full list of ledger rows (not yet persisted) for a tournament
     * under one system's points schedule + stacking mode.
     *
     * @return array<int,array<string,mixed>>  ranking_points insert rows
     */
    private function buildLedger(Tournament $tournament, RankingSystem $system): array
    {
        $rows = [];

        $categories = $tournament->categories()->with('groups.pairs')->get();

        foreach ($categories as $category) {
            // Only FINISHED categories count: the final must be confirmed.
            $final = $this->finalMatch($category);
            $categoryDecided = $final && $final->isConfirmed() && $final->winner_pair_id;

            // Even if the bracket isn't decided, group achievements for a
            // fully-played group stage could exist — but to keep "finished
            // categories only" (your public-summary rule) we require the
            // category to be decided before awarding ANYTHING. This mirrors the
            // finalize semantics: you finalize a DONE tournament.
            if (! $categoryDecided) {
                continue;
            }

            // achievements[pairId] = set of RankingAchievement (dedup per pair)
            $byPair = [];

            $this->collectGroupAchievements($category, $byPair);
            $this->collectBracketAchievements($category, $final, $byPair);

            // Convert pair achievements → player ledger rows.
            foreach ($byPair as $pairId => $achievements) {
                $pair = $this->resolvePair($category, (int) $pairId);
                if (! $pair) continue;

                $playerIds = $pair->playerIds();
                if (empty($playerIds)) continue;

                $awarded = $this->applyStacking($achievements, $system);

                foreach ($awarded as $achievement => $points) {
                    if ($points <= 0) continue;
                    foreach ($playerIds as $playerId) {
                        $rows[] = [
                            'ranking_system_id' => $system->id,
                            'tournament_id'     => $tournament->id,
                            'category_id'       => $category->id,
                            'player_id'         => $playerId,
                            'pair_id'           => $pair->id,
                            'achievement'       => $achievement,
                            'points'            => $points,
                        ];
                    }
                }
            }
        }

        return $rows;
    }

    // ── Group-phase achievements ──────────────────────────────────────────

    /**
     * group_stage for every pair that played ≥1 confirmed group match;
     * group_win for the pair ranked #1 in each group's standings.
     */
    private function collectGroupAchievements(Category $category, array &$byPair): void
    {
        foreach ($category->groups as $group) {
            // Standings from confirmed matches; row 0 = winner (empty if none played).
            $rows = $this->standings->forGroup($group);
            if ($rows->isEmpty()) continue;

            // group_stage: any pair with ≥1 played match in this group.
            foreach ($rows as $row) {
                if (($row['played'] ?? 0) > 0) {
                    $this->add($byPair, $row['pair_id'], RankingAchievement::GroupStage);
                }
            }

            // group_win: the top row, but only if it actually played.
            $top = $rows->first();
            if ($top && ($top['played'] ?? 0) > 0) {
                $this->add($byPair, $top['pair_id'], RankingAchievement::GroupWin);
            }
        }
    }

    // ── Bracket achievements ──────────────────────────────────────────────

    /**
     * reached_r32 / reached_r16 / reached_qf / reached_sf for any pair that
     * appeared in a confirmed match of that round; finalist + champion from the
     * final. "Reached" = appeared (won or lost). Rounds that don't exist emit
     * nothing (don't invent).
     */
    private function collectBracketAchievements(Category $category, GameMatch $final, array &$byPair): void
    {
        $bracket = GameMatch::where('category_id', $category->id)
            ->whereNull('group_id')
            ->where('state', 'confirmed')
            ->get();

        // Map phaseKey → the "reached" achievement it grants to both sides.
        $reachedByPhase = [
            'r32'          => RankingAchievement::ReachedR32,
            'r16'          => RankingAchievement::ReachedR16,
            'quarterfinal' => RankingAchievement::ReachedQf,
            'semifinal'    => RankingAchievement::ReachedSf,
        ];

        foreach ($bracket as $m) {
            // Skip the 3rd-place match for "reached" purposes: its players already
            // earned reached_sf from the actual semifinal. (It's not a round of
            // progression.) We still let it exist; we just don't grant round pts.
            if ($m->is_third_place) {
                continue;
            }

            $phase = $m->phaseKey();

            if (isset($reachedByPhase[$phase])) {
                $ach = $reachedByPhase[$phase];
                // Both sides "reached" this round if the side is a real pair.
                if ($m->pair_a_id) $this->add($byPair, $m->pair_a_id, $ach);
                if ($m->pair_b_id) $this->add($byPair, $m->pair_b_id, $ach);
            }
        }

        // Finalist + champion from THE final (already confirmed, has winner).
        $championId = $final->winner_pair_id;
        $runnerId   = $final->loserPairId();

        // champion always counts (even a bye-final where runnerId is null).
        $this->add($byPair, $championId, RankingAchievement::Champion);

        // finalist only when a REAL opponent played the final (no phantom).
        if ($runnerId && $final->pair_a_id && $final->pair_b_id) {
            $this->add($byPair, $runnerId, RankingAchievement::Finalist);
            // The champion is also a finalist (they were in the final). Under
            // cumulative stacking a champion should get finalist points too, so
            // add finalist to the champion as well when a real final happened.
            $this->add($byPair, $championId, RankingAchievement::Finalist);
        }
    }

    /**
     * The final match of a category: bracket (no group), highest round, and NOT
     * the 3rd-place match. Returns null if the category has no bracket.
     */
    private function finalMatch(Category $category): ?GameMatch
    {
        return GameMatch::where('category_id', $category->id)
            ->whereNull('group_id')
            ->where('is_third_place', false)
            ->orderByDesc('round')
            ->first();
    }

    // ── Stacking ──────────────────────────────────────────────────────────

    /**
     * Turn a pair's set of achievements into [achievement_key => points] under
     * the system's stacking mode.
     *
     * @param  array<int,RankingAchievement>  $achievements
     * @return array<string,int>
     */
    private function applyStacking(array $achievements, RankingSystem $system): array
    {
        // Dedup + map to points.
        $unique = [];
        foreach ($achievements as $a) {
            $unique[$a->value] = $system->pointsFor($a);
        }

        if ($system->isCumulative()) {
            return $unique; // sum of all (each row inserted separately)
        }

        // best_only: keep just the single highest-value achievement.
        if (empty($unique)) return [];
        arsort($unique); // highest points first
        $topKey = array_key_first($unique);
        return [$topKey => $unique[$topKey]];
    }

    // ── helpers ───────────────────────────────────────────────────────────

    /** Add an achievement to a pair's set (dedup by enum). */
    private function add(array &$byPair, ?int $pairId, RankingAchievement $a): void
    {
        if (! $pairId) return;
        $byPair[$pairId] ??= [];
        $byPair[$pairId][$a->value] = $a; // keyed by value → natural dedup
    }

    /**
     * Resolve a Pair model for a category. Pairs are loaded on groups, but a
     * bracket-only qualifier might not be in a group collection, so fall back to
     * a direct find. Eager player relations for playerIds().
     */
    private function resolvePair(Category $category, int $pairId): ?Pair
    {
        foreach ($category->groups as $group) {
            $hit = $group->pairs->firstWhere('id', $pairId);
            if ($hit) {
                $hit->loadMissing('player1', 'player2');
                return $hit;
            }
        }
        return Pair::with('player1', 'player2')->find($pairId);
    }
}
