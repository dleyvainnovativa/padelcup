<?php

namespace App\Services\Ranking;

use App\Models\Player;
use App\Models\RankingPoint;
use App\Models\RankingSystem;
use Illuminate\Support\Collection;

/**
 * Reads the ranking_points ledger and produces leaderboards for a ranking system.
 *
 * Player identity: the same human has a SEPARATE Player row per category (dedup
 * only on email/phone), so we aggregate by (normalized_name, created_by) —
 * mirroring PublicTournamentController::player().
 *
 * Category identity (Phase 1+2): categories are free text per tournament, so the
 * SAME category typed differently across tournaments is merged via
 * categories.category_key (normalized name). This gives three views:
 *   - forSystem()  → one COMBINED leaderboard across all categories (unchanged).
 *   - byCategory() → a leaderboard PER category_key, each labeled with the most
 *                    frequent original spelling.
 *   - categories() → the list of category keys + labels present in the ledger.
 *
 * A leaderboard row:
 *   [ rank, name, points, player_ids[], tournaments, breakdown? ]
 */
class RankingLeaderboard
{
    /**
     * Combined leaderboard for a system (all categories together). Unchanged
     * behavior from Phase 4.
     */
    public function forSystem(RankingSystem $system, bool $withBreakdown = false, ?int $tournamentId = null): Collection
    {
        $rows = $this->ledgerRows($system, $tournamentId);
        if ($rows->isEmpty()) return collect();
        return $this->buildBoard($rows, $withBreakdown);
    }

    /**
     * Per-category leaderboards. Returns a Collection keyed by category_key:
     *   [
     *     '5ta femenil' => [
     *        'key'   => '5ta femenil',
     *        'label' => '5ta Femenil',        // most frequent original spelling
     *        'board' => Collection<row>,      // ranked standings for this category
     *        'players' => int,                // distinct humans
     *     ],
     *     ...
     *   ]
     * Ordered by label (A→Z) for stable display.
     */
    public function byCategory(RankingSystem $system, bool $withBreakdown = false, ?int $tournamentId = null): Collection
    {
        $rows = $this->ledgerRows($system, $tournamentId);
        if ($rows->isEmpty()) return collect();

        return $rows
            ->groupBy(fn ($r) => $r->cat_key ?? '')
            ->map(function ($catRows, $key) use ($withBreakdown) {
                return [
                    'key'     => $key,
                    'label'   => $this->representativeLabel($catRows),
                    'board'   => $this->buildBoard($catRows, $withBreakdown),
                    'players' => $catRows
                        ->groupBy(fn ($r) => $r->norm . '|' . ($r->owner ?? ''))
                        ->count(),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->keyBy('key');
    }

    /**
     * Lightweight list of categories present in a system's ledger:
     *   [ ['key' => ..., 'label' => ..., 'players' => int], ... ]
     * For building the category selector without computing full boards.
     */
    public function categories(RankingSystem $system, ?int $tournamentId = null): Collection
    {
        $rows = $this->ledgerRows($system, $tournamentId);
        if ($rows->isEmpty()) return collect();

        return $rows
            ->groupBy(fn ($r) => $r->cat_key ?? '')
            ->map(fn ($catRows, $key) => [
                'key'     => $key,
                'label'   => $this->representativeLabel($catRows),
                'players' => $catRows->groupBy(fn ($r) => $r->norm . '|' . ($r->owner ?? ''))->count(),
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    // ── internals ─────────────────────────────────────────────────────────

    /**
     * List of tournaments that fed this ranking (have ledger rows), for the
     * tournament selector. [{ id, name, players }], ordered by name.
     */
    public function tournaments(RankingSystem $system): Collection
    {
        $rows = $this->ledgerRows($system);
        if ($rows->isEmpty()) return collect();

        return $rows
            ->groupBy('tournament_id')
            ->map(fn ($tRows, $id) => [
                'id'      => (int) $id,
                'name'    => $tRows->first()->tournament_name ?? ('Torneo #' . $id),
                'players' => $tRows->groupBy(fn ($r) => $r->norm . '|' . ($r->owner ?? ''))->count(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Pull the ledger joined to players (identity), categories (key + name), and
     * tournaments (name). Optionally scoped to a single tournament.
     */
    private function ledgerRows(RankingSystem $system, ?int $tournamentId = null): Collection
    {
        return RankingPoint::query()
            ->where('ranking_points.ranking_system_id', $system->id)
            ->when($tournamentId, fn ($q) => $q->where('ranking_points.tournament_id', $tournamentId))
            ->join('players', 'players.id', '=', 'ranking_points.player_id')
            ->join('categories', 'categories.id', '=', 'ranking_points.category_id')
            ->join('tournaments', 'tournaments.id', '=', 'ranking_points.tournament_id')
            ->selectRaw('
                players.normalized_name as norm,
                players.created_by as owner,
                ranking_points.player_id,
                ranking_points.tournament_id,
                ranking_points.achievement,
                ranking_points.points,
                categories.category_key as cat_key,
                categories.name as cat_name,
                tournaments.name as tournament_name
            ')
            ->get();
    }

    /**
     * Build a ranked board from a set of ledger rows (already scoped to a system
     * and, for byCategory, to one category).
     */
    private function buildBoard(Collection $rows, bool $withBreakdown): Collection
    {
        $humans = $rows->groupBy(fn ($r) => $r->norm . '|' . ($r->owner ?? ''));
        $displayNames = $this->displayNamesFor($humans);

        $board = $humans->map(function ($group, $key) use ($withBreakdown, $displayNames) {
            // key is "norm|owner"; split for identity + build a url-safe detail key.
            $sep = strrpos($key, '|');
            $norm = $sep === false ? $key : substr($key, 0, $sep);
            $ownerRaw = $sep === false ? '' : substr($key, $sep + 1);
            $owner = $ownerRaw === '' ? null : (int) $ownerRaw;

            $row = [
                'name'        => $displayNames[$key] ?? '—',
                'points'      => (int) $group->sum('points'),
                'player_ids'  => $group->pluck('player_id')->unique()->values()->all(),
                'tournaments' => $group->pluck('tournament_id')->unique()->count(),
                // Identity for rank lookup + linking to the detail page.
                '_norm'       => $norm,
                '_owner'      => $owner,
                'key'         => \App\Services\Ranking\RankingPlayerDetail::encodeKey($norm, $owner),
            ];
            if ($withBreakdown) {
                $row['breakdown'] = $group->groupBy('achievement')
                    ->map(fn ($g) => (int) $g->sum('points'))
                    ->sortDesc()->all();
            }
            return $row;
        })->values();

        return $this->rankRows($board);
    }

    /** Standard competition ranking: ties share a rank, next rank skips. */
    private function rankRows(Collection $board): Collection
    {
        $board = $board->sortByDesc('points')->values();
        $rank = 0; $seen = 0; $prev = null;
        return $board->map(function ($row) use (&$rank, &$seen, &$prev) {
            $seen++;
            if ($prev === null || $row['points'] < $prev) $rank = $seen;
            $prev = $row['points'];
            $row['rank'] = $rank;
            return $row;
        });
    }

    /**
     * Most frequent original spelling among a category's rows → the display
     * label. Ties broken by the longest (usually most complete) spelling, then
     * alphabetically for determinism.
     */
    private function representativeLabel(Collection $catRows): string
    {
        $counts = [];
        foreach ($catRows as $r) {
            $name = $r->cat_name ?? '';
            if ($name === '') continue;
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        if (empty($counts)) return '—';

        arsort($counts); // by frequency desc
        $top = max($counts);
        // Candidates tied on frequency → prefer a nicely-cased spelling over an
        // all-caps one, then the longest (usually most complete), then alpha.
        $tied = array_keys(array_filter($counts, fn ($c) => $c === $top));
        usort($tied, function ($a, $b) {
            $aCaps = ($a === mb_strtoupper($a)) ? 1 : 0;
            $bCaps = ($b === mb_strtoupper($b)) ? 1 : 0;
            return ($aCaps <=> $bCaps)                 // non-all-caps first
                ?: (mb_strlen($b) <=> mb_strlen($a))   // longer first
                ?: strcmp($a, $b);                     // deterministic
        });
        return $tied[0];
    }

    /**
     * Resolve a display name per human key (representative Player->name).
     *
     * @param  Collection  $humans  keyed by "norm|owner"
     * @return array<string,string>
     */
    private function displayNamesFor(Collection $humans): array
    {
        $repIds = $humans->map(fn ($g) => $g->first()->player_id)->all();
        $names = Player::whereIn('id', array_values($repIds))->get()->keyBy('id');

        $out = [];
        foreach ($humans as $key => $group) {
            $pid = $group->first()->player_id;
            $player = $names->get($pid);
            $out[$key] = $player ? $player->name : '—';
        }
        return $out;
    }
}
