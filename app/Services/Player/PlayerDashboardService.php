<?php

namespace App\Services\Player;

use App\Enums\MatchState;
use App\Models\GameMatch;
use App\Models\Pair;
use App\Models\Player;
use App\Models\User;
use App\Services\Tournament\StandingsService;
use App\Models\Group;

use Illuminate\Support\Collection;

/**
 * Assembles a player's personal view: their tournaments, upcoming matches, and
 * results — from the Player records linked to their account (players.user_id).
 *
 * Deliberately framework-light and returns plain arrays/collections so the SAME
 * service backs both the web dashboard AND the future Flutter "my matches" API
 * (an API controller can json() these directly).
 */
class PlayerDashboardService
{
    public function __construct(private StandingsService $standings) {}
    /** All Player records linked to this user (one per category historically). */
    public function playerIds(User $user): array
    {
        return Player::where('user_id', $user->id)->pluck('id')->all();
    }

    /** Pairs the user belongs to (as player1 or player2), across all tournaments. */
    public function pairIds(User $user): array
    {
        $pids = $this->playerIds($user);
        if (empty($pids)) return [];

        return Pair::where(fn($q) => $q->whereIn('player1_id', $pids)->orWhereIn('player2_id', $pids))
            ->pluck('id')->all();
    }

    /**
     * Every match involving the user, ordered by time, eager-loaded for display.
     * @return Collection<int, GameMatch>
     */
    public function matches(User $user): Collection
    {
        $pairIds = $this->pairIds($user);
        if (empty($pairIds)) return collect();

        return GameMatch::query()
            ->where(fn($q) => $q->whereIn('pair_a_id', $pairIds)->orWhereIn('pair_b_id', $pairIds))
            ->with([
                'category.tournament',
                'pairA.player1',
                'pairA.player2',
                'pairB.player1',
                'pairB.player2',
                'court',
            ])
            ->orderByRaw('starts_at IS NULL')     // timed matches first
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Upcoming matches: scheduled/proposed, in the future (or undated), soonest
     * first. Each row is a display-ready array.
     */
    public function upcoming(User $user, int $limit = 10): Collection
    {
        $pairIds = $this->pairIds($user);
        return $this->matches($user)
            ->filter(function (GameMatch $m) {
                if ($m->state === MatchState::Confirmed) return false;      // already played
                if ($m->starts_at && $m->starts_at->isPast()) return false; // past & unplayed → not "upcoming"
                return true;
            })
            ->take($limit)
            ->map(fn(GameMatch $m) => $this->presentMatch($m, $pairIds))
            ->values();
    }

    /**
     * Played (confirmed) matches, most recent first, as display rows with a
     * win/loss flag from the user's perspective.
     */
    public function results(User $user, int $limit = 20): Collection
    {
        $pairIds = $this->pairIds($user);
        return $this->matches($user)
            ->filter(fn(GameMatch $m) => $m->state === MatchState::Confirmed)
            ->sortByDesc(fn(GameMatch $m) => $m->starts_at?->timestamp ?? 0)
            ->take($limit)
            ->map(fn(GameMatch $m) => $this->presentMatch($m, $pairIds))
            ->values();
    }

    /**
     * The tournaments/categories the user participates in, each with a small
     * summary (played / total, upcoming count).
     */
    public function tournaments(User $user): Collection
    {
        $matches = $this->matches($user);
        if ($matches->isEmpty()) return collect();

        return $matches
            ->groupBy(fn(GameMatch $m) => $m->category?->tournament?->id)
            ->map(function (Collection $ms) {
                $tournament = $ms->first()->category?->tournament;
                $categories = $ms->groupBy(fn($m) => $m->category?->id)->map(function (Collection $cms) {
                    $cat = $cms->first()->category;
                    return [
                        'name' => $cat?->name,
                        'played' => $cms->where('state', MatchState::Confirmed)->count(),
                        'total' => $cms->count(),
                    ];
                })->values();

                return [
                    'tournament' => $tournament?->name,
                    'tournament_id' => $tournament?->id,
                    'slug' => $tournament?->slug,
                    'starts_on' => $tournament?->starts_on,
                    'categories' => $categories->all(),
                    'total_matches' => $ms->count(),
                    'played' => $ms->where('state', MatchState::Confirmed)->count(),
                ];
            })
            ->sortByDesc(fn($t) => optional($t['starts_on'])->timestamp ?? 0)
            ->values();
    }

    /** Convenience: does this user have any linked player records yet? */
    public function hasProfile(User $user): bool
    {
        return ! empty($this->playerIds($user));
    }

    /**
     * Shape a match into a display-ready array from the USER's perspective:
     * which side is "me", the opponent, the score, win/loss, timing, context.
     */
    private function presentMatch(GameMatch $m, array $myPairIds): array
    {
        $iAmA = in_array($m->pair_a_id, $myPairIds, true);
        $mine = $iAmA ? $m->pairA : $m->pairB;
        $opp  = $iAmA ? $m->pairB : $m->pairA;

        $played = $m->state === MatchState::Confirmed;
        $won = $played && $m->winner_pair_id && $m->winner_pair_id === ($mine?->id);

        // Score from the user's perspective (my games first).
        $score = null;
        if ($played && ! empty($m->sets)) {
            $score = collect($m->sets)->map(function ($s) use ($iAmA) {
                return $iAmA ? "{$s[0]}-{$s[1]}" : "{$s[1]}-{$s[0]}";
            })->implode(' ');
        }

        return [
            'id' => $m->id,
            'tournament' => $m->category?->tournament?->name,
            'tournament_id' => $m->category?->tournament?->id,
            'category' => $m->category?->name,
            'starts_at' => $m->starts_at,
            'court' => $m->court?->name,
            'mine' => $mine?->name(),
            'opponent' => $opp?->name(),
            'played' => $played,
            'won' => $won,
            'score' => $score,
            'state' => $m->state->value,
        ];
    }

    public function standings(\App\Models\User $user): \Illuminate\Support\Collection
    {
        $pairIds = $this->pairIds($user);
        if (empty($pairIds)) return collect();

        // Groups any of my pairs are in.
        $groups = \App\Models\Group::query()
            ->whereHas('pairs', fn($q) => $q->whereIn('pairs.id', $pairIds))
            ->with('category.tournament', 'pairs')
            ->get();

        $out = collect();
        foreach ($groups as $group) {
            $rows = $this->standings->forGroup($group);          // ordered Collection
            if ($rows->isEmpty()) continue;

            // Find my pair in this group and its 1-based position.
            $position = null;
            $myRow = null;
            $i = 0;
            foreach ($rows as $row) {
                $i++;
                $pid = is_array($row) ? ($row['pair_id'] ?? null) : ($row->pair_id ?? null);
                if (in_array($pid, $pairIds, true)) {
                    $position = $i;
                    $myRow = $row;
                    break;
                }
            }
            if (! $position) continue;

            $points = is_array($myRow) ? ($myRow['points'] ?? null) : ($myRow->points ?? null);

            $out->put($group->id, [
                'group' => $group->name,
                'category' => $group->category?->name,
                'tournament' => $group->category?->tournament?->name,
                'tournament_id' => $group->category?->tournament?->id,
                'position' => $position,
                'of' => $rows->count(),
                'points' => $points,
            ]);
        }

        return $out;
    }

    public function stats(\App\Models\User $user): array
    {
        $pairIds = $this->pairIds($user);
        $matches = $this->matches($user)
            ->filter(fn($m) => $m->state === \App\Enums\MatchState::Confirmed)
            // oldest → newest for streak calc
            ->sortBy(fn($m) => $m->starts_at?->timestamp ?? 0)
            ->values();

        $wins = 0;
        $losses = 0;
        $setsWon = 0;
        $setsLost = 0;
        $streak = 0;
        $streakType = 'none';
        $outcomes = []; // true=win in chronological order

        foreach ($matches as $m) {
            $iAmA = in_array($m->pair_a_id, $pairIds, true);
            $mineId = $iAmA ? $m->pair_a_id : $m->pair_b_id;
            $won = $m->winner_pair_id && $m->winner_pair_id === $mineId;

            $won ? $wins++ : $losses++;
            $outcomes[] = $won;

            // sets from my perspective
            [$a, $b] = $m->setsWon();   // [aSets, bSets]
            $setsWon += $iAmA ? $a : $b;
            $setsLost += $iAmA ? $b : $a;
        }

        // Current streak = trailing run of identical outcomes (from newest).
        if (! empty($outcomes)) {
            $last = end($outcomes);
            $streakType = $last ? 'win' : 'loss';
            for ($i = count($outcomes) - 1; $i >= 0; $i--) {
                if ($outcomes[$i] === $last) $streak++;
                else break;
            }
        }

        $played = $wins + $losses;
        $winRate = $played > 0 ? (int) round($wins / $played * 100) : 0;

        // Best group position across all groups (1 = best).
        $best = null;
        foreach ($this->standings($user) as $s) {
            if ($best === null || $s['position'] < $best['position']) {
                $best = $s;
            }
        }

        // Distinct tournaments / categories the player appears in.
        $tournaments = $this->matches($user)
            ->map(fn($m) => $m->category?->tournament?->id)->filter()->unique()->count();
        $categories = $this->matches($user)
            ->map(fn($m) => $m->category?->id)->filter()->unique()->count();

        return [
            'matches_played' => $played,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $winRate,
            'sets_won' => $setsWon,
            'sets_lost' => $setsLost,
            'tournaments' => $tournaments,
            'categories' => $categories,
            'current_streak' => $streak,
            'streak_type' => $streakType,   // 'win' | 'loss' | 'none'
            'best_position' => $best,       // ['position','of','group','category','tournament'] or null
        ];
    }
}
