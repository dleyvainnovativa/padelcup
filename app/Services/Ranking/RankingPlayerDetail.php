<?php

namespace App\Services\Ranking;

use App\Enums\RankingAchievement;
use App\Enums\MatchState;
use App\Models\GameMatch;
use App\Models\Pair;
use App\Models\Player;
use App\Models\RankingPoint;
use App\Models\RankingSystem;
use Illuminate\Support\Collection;

/**
 * Detail for ONE player within a ranking system: the "why do they have N points"
 * justification, built purely from the ledger (no match records yet — W/L is a
 * planned follow-up).
 *
 * Player identity matches the leaderboard: (normalized_name, created_by),
 * encoded into a url-safe key so it can live in a route.
 */
class RankingPlayerDetail
{
    public function __construct(private RankingLeaderboard $leaderboard) {}

    /** Encode a "norm|owner" identity into a url-safe route key. */
    public static function encodeKey(string $norm, ?int $owner): string
    {
        return rtrim(strtr(base64_encode($norm . '|' . ($owner ?? '')), '+/', '-_'), '=');
    }

    /** Decode a route key back to [norm, owner]. */
    public static function decodeKey(string $key): array
    {
        $b64 = strtr($key, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        $raw = base64_decode($b64, true) ?: '';
        $parts = explode('|', $raw, 2);
        return [$parts[0] ?? '', ($parts[1] ?? '') === '' ? null : (int) $parts[1]];
    }

    /**
     * Build the full detail payload for a player in a system.
     *
     * @param  string       $key    encoded identity
     * @param  string|null  $scope  'all' | category_key — the view they arrived
     *                              from, used for the displayed rank + label
     * @return array|null            null if the player has no points here
     */
    public function for(RankingSystem $system, string $key, ?string $scope = null): ?array
    {
        [$norm, $owner] = self::decodeKey($key);

        // All ledger rows for this player in this system.
        $rows = RankingPoint::query()
            ->where('ranking_points.ranking_system_id', $system->id)
            ->join('players', 'players.id', '=', 'ranking_points.player_id')
            ->join('categories', 'categories.id', '=', 'ranking_points.category_id')
            ->join('tournaments', 'tournaments.id', '=', 'ranking_points.tournament_id')
            ->where('players.normalized_name', $norm)
            ->when($owner !== null, fn($q) => $q->where('players.created_by', $owner))
            ->selectRaw('
                players.name as player_name,
                ranking_points.player_id,
                ranking_points.tournament_id,
                ranking_points.category_id,
                ranking_points.achievement,
                ranking_points.points,
                categories.category_key as cat_key,
                categories.name as cat_name,
                tournaments.name as tournament_name
            ')
            ->get();

        if ($rows->isEmpty()) return null;

        $total = (int) $rows->sum('points');
        $displayName = $rows->first()->player_name ?? '—';

        // Achievement breakdown: per type → count, unit points, subtotal.
        // Sorted by subtotal desc for the bar chart.
        $maxSubtotal = 0;
        $breakdown = $rows
            ->groupBy('achievement')
            ->map(function ($g, $ach) use (&$maxSubtotal) {
                $subtotal = (int) $g->sum('points');
                $maxSubtotal = max($maxSubtotal, $subtotal);
                $case = RankingAchievement::tryFrom($ach);
                return [
                    'achievement' => $ach,
                    'label'       => $case ? $case->label() : $ach,
                    'count'       => $g->count(),
                    'points'      => $subtotal,
                ];
            })
            ->sortByDesc('points')
            ->values();

        // Add a bar width % (relative to the biggest subtotal) for the visual.
        $breakdown = $breakdown->map(function ($b) use ($maxSubtotal) {
            $b['bar'] = $maxSubtotal > 0 ? round($b['points'] / $maxSubtotal * 100) : 0;
            return $b;
        });

        // Sourcing rows: per (tournament, category), what they did + points.
        $sources = $rows
            ->groupBy(fn($r) => $r->tournament_id . ':' . $r->cat_key)
            ->map(function ($g) {
                $best = $g->sortByDesc('points')->first();
                return [
                    'tournament' => $g->first()->tournament_name,
                    'category'   => $g->first()->cat_name,
                    'points'     => (int) $g->sum('points'),
                    // The highest achievement label reached in this tournament/category.
                    'top_label'  => optional(RankingAchievement::tryFrom($best->achievement))->label() ?? $best->achievement,
                    'achievements' => $g->groupBy('achievement')->map(fn($x) => [
                        'label'  => optional(RankingAchievement::tryFrom($x->first()->achievement))->label() ?? $x->first()->achievement,
                        'points' => (int) $x->sum('points'),
                    ])->values(),
                ];
            })
            ->sortByDesc('points')
            ->values();

        // Rank within the scope they arrived from (combined or a category).
        [$rank, $scopeLabel] = $this->rankInScope($system, $norm, $owner, $scope);

        // Match record (W/L) from game_matches, scoped to the exact categories
        // this player earned points in (game_matches has category_id, not
        // tournament_id — the tournament is reached via categories.tournament_id).
        $playerIds = $rows->pluck('player_id')->unique()->values()->all();
        $categoryIds = $rows->pluck('category_id')->unique()->values()->all();
        $record = $this->matchRecord($playerIds, $categoryIds);

        return [
            'name'         => $displayName,
            'total'        => $total,
            'tournaments'  => $rows->pluck('tournament_id')->unique()->count(),
            'rank'         => $rank,
            'scope_label'  => $scopeLabel,
            'breakdown'    => $breakdown,
            'sources'      => $sources,
            'record'       => $record,
            'key'          => $key,
        ];
    }

    /**
     * Win/loss record from confirmed matches, scoped to the categories this
     * player earned points in and the pairs they belonged to.
     *
     * NOTE: game_matches has category_id, NOT tournament_id — scoping by the
     * exact categories that fed the ranking is both correct and more precise
     * than by tournament (it excludes other categories in the same events).
     *
     * @param  int[]  $playerIds     every Player row for this human
     * @param  int[]  $categoryIds   categories that fed the ranking (scope)
     * @return array{played:int, won:int, lost:int, win_pct:?int, sets_won:int, sets_lost:int}
     */
    private function matchRecord(array $playerIds, array $categoryIds): array
    {
        $empty = ['played' => 0, 'won' => 0, 'lost' => 0, 'win_pct' => null, 'sets_won' => 0, 'sets_lost' => 0];
        if (empty($playerIds) || empty($categoryIds)) return $empty;

        // Pairs this player was part of (either slot).
        $pairIds = Pair::query()
            ->where(fn($q) => $q->whereIn('player1_id', $playerIds)->orWhereIn('player2_id', $playerIds))
            ->pluck('id')->all();
        if (empty($pairIds)) return $empty;

        // Confirmed matches in those categories involving those pairs.
        $matches = GameMatch::query()
            ->whereIn('category_id', $categoryIds)
            ->where('state', MatchState::Confirmed->value)
            ->where(fn($q) => $q->whereIn('pair_a_id', $pairIds)->orWhereIn('pair_b_id', $pairIds))
            ->get(['pair_a_id', 'pair_b_id', 'winner_pair_id', 'sets']);

        $pairSet = array_flip($pairIds);
        $won = $lost = $played = $setsWon = $setsLost = 0;

        foreach ($matches as $m) {
            $mine = isset($pairSet[$m->pair_a_id]) ? 'a' : (isset($pairSet[$m->pair_b_id]) ? 'b' : null);
            if ($mine === null) continue; // safety
            $played++;

            if ($m->winner_pair_id) {
                $iWon = ($mine === 'a' && $m->winner_pair_id === $m->pair_a_id)
                    || ($mine === 'b' && $m->winner_pair_id === $m->pair_b_id);
                $iWon ? $won++ : $lost++;
            }

            // Sets won/lost from the sets array [[a,b],...] if present.
            $sets = $this->decodeSets($m->sets);
            foreach ($sets as [$a, $b]) {
                $my = $mine === 'a' ? $a : $b;
                $op = $mine === 'a' ? $b : $a;
                if ($my > $op) $setsWon++;
                elseif ($op > $my) $setsLost++;
            }
        }

        $decided = $won + $lost;
        return [
            'played'   => $played,
            'won'      => $won,
            'lost'     => $lost,
            'win_pct'  => $decided > 0 ? (int) round($won / $decided * 100) : null,
            'sets_won' => $setsWon,
            'sets_lost' => $setsLost,
        ];
    }

    /** Normalize the sets column (array cast or JSON string) to [[a,b],...]. */
    private function decodeSets($sets): array
    {
        if (is_array($sets)) return $sets;
        if (is_string($sets) && $sets !== '') {
            $decoded = json_decode($sets, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    /**
     * Find this player's rank within the scope they came from.
     * @return array{0:?int,1:string}  [rank, scopeLabel]
     */
    private function rankInScope(RankingSystem $system, string $norm, ?int $owner, ?string $scope): array
    {
        $targetKey = $norm . '|' . ($owner ?? '');

        if ($scope && $scope !== 'all') {
            $entry = $this->leaderboard->byCategory($system)->get($scope);
            if ($entry) {
                $rank = $this->findRank($entry['board'], $norm, $owner);
                return [$rank, $entry['label']];
            }
        }
        // Default: combined board.
        $board = $this->leaderboard->forSystem($system);
        return [$this->findRank($board, $norm, $owner), 'General'];
    }

    /** Locate a player's rank in a board by matching one of their player_ids. */
    private function findRank(Collection $board, string $norm, ?int $owner): ?int
    {
        // The board rows carry player_ids; match by resolving those ids back to
        // this identity. Simpler: re-derive via name match on the row.
        foreach ($board as $row) {
            // A row belongs to this human if any of its player_ids normalizes to
            // the same identity. We approximate by comparing the display name's
            // normalized form — but the safest check is player_ids membership,
            // which we resolve below.
            if (($row['_norm'] ?? null) === $norm && ($row['_owner'] ?? null) === $owner) {
                return $row['rank'];
            }
        }
        return null;
    }
}
