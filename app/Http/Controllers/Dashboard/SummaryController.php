<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\Tournament\BracketService;
use App\Services\Tournament\StandingsService;
use Illuminate\Support\Collection;

class SummaryController extends Controller
{
    public function __construct(
        private StandingsService $standings,
        private BracketService $brackets,
    ) {}

    /** Tournament "resumen": per-category standings + qualifier highlighting. */
    public function show(Tournament $tournament)
    {
        $this->authorize('view', $tournament);

        $categories = $tournament->categories()
            ->with(['groups.pairs.player1', 'groups.pairs.player2'])
            ->orderBy('name')
            ->get();

        // Build a render-ready structure per category.
        $data = $categories->map(function ($category) {
            $groups = $category->groups->map(function ($group) use ($category) {
                $rows = $this->standings->forGroup($group);
                return [
                    'name' => $group->name,
                    'rows' => $rows->map(function ($r) use ($group) {
                        $pair = $group->pairs->firstWhere('id', $r['pair_id']);
                        return array_merge($r, ['pair_name' => $pair?->name() ?? '—']);
                    })->values(),
                ];
            });

            // Which pair ids qualify to the bracket (if hybrid/has bracket).
            $qualifierIds = [];
            if ($category->format->hasBracket() && $category->format->hasGroups()) {
                try {
                    $q = $this->brackets->qualifiers($category);
                    $qualifierIds = $q['qualifiers'] ?? [];
                } catch (\Throwable $e) {
                    $qualifierIds = [];
                }
            }

            // Combined ("General") ranking: pool all group rows, tag with group
            // name + position, sort by group-position → points → game_diff.
            $combined = collect();
            foreach ($groups as $g) {
                foreach ($g['rows'] as $pos => $r) {
                    $combined->push(array_merge($r, [
                        'group_name' => $g['name'],
                        'group_pos' => $pos + 1,
                    ]));
                }
            }
            $combined = $combined->sort(function ($a, $b) {
                return [$a['group_pos'], -$a['points'], -$a['game_diff']]
                    <=> [$b['group_pos'], -$b['points'], -$b['game_diff']];
            })->values();

            // Podium from the bracket, if it has produced a champion.
            $podium = $this->bracketPodium($category);

            return [
                'id' => $category->id,
                'name' => $category->name,
                'format' => $category->format,
                'hasGroups' => $category->format->hasGroups(),
                'hasBracket' => $category->format->hasBracket(),
                'advancePerGroup' => $category->advance_per_group,
                'groups' => $groups,
                'combined' => $combined,
                'qualifierIds' => $qualifierIds,
                'podium' => $podium,
            ];
        });

        return view('dashboard.tournaments.summary', [
            'tournament' => $tournament,
            'categories' => $data,
        ]);
    }

    /**
     * Final podium from the bracket, or null if not resolved.
     * 1st = champion (final winner), 2nd = runner-up (final loser),
     * 3rd = winner of 3rd-place match if any, else BOTH semifinal losers (joint).
     *
     * @return array{first: ?string, second: ?string, third: array<int,string>}|null
     */
    private function bracketPodium(\App\Models\Category $category): ?array
    {
        if (! $category->format->hasBracket()) return null;

        $bracket = $category->matches()
            ->whereNull('group_id')
            ->with(['pairA', 'pairB'])
            ->get();
        if ($bracket->isEmpty()) return null;

        $maxRound = (int) $bracket->max('round');
        $final = $bracket->first(fn($m) => $m->round === $maxRound && ! $m->is_third_place);
        if (! $final || ! $final->winner_pair_id) return null; // not finished

        $nameOf = function ($pairId) use ($bracket) {
            foreach ($bracket as $m) {
                if ($m->pair_a_id === $pairId) return $m->pairA?->name();
                if ($m->pair_b_id === $pairId) return $m->pairB?->name();
            }
            return null;
        };

        $champion = $final->winner_pair_id;
        $runnerUp = $final->pair_a_id === $champion ? $final->pair_b_id : $final->pair_a_id;

        // Third place.
        $third = [];
        $thirdMatch = $bracket->first(fn($m) => $m->is_third_place);
        if ($thirdMatch && $thirdMatch->winner_pair_id) {
            $third[] = $nameOf($thirdMatch->winner_pair_id);
        } else {
            // Joint third = both semifinal losers (round = maxRound - 1).
            $semis = $bracket->filter(fn($m) => $m->round === $maxRound - 1 && ! $m->is_third_place);
            foreach ($semis as $sf) {
                if ($sf->winner_pair_id) {
                    $loser = $sf->pair_a_id === $sf->winner_pair_id ? $sf->pair_b_id : $sf->pair_a_id;
                    if ($loser) $third[] = $nameOf($loser);
                }
            }
        }

        return [
            'first' => $nameOf($champion),
            'second' => $nameOf($runnerUp),
            'third' => array_values(array_filter($third)),
        ];
    }
}
