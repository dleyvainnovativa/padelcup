<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Pair;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Manager-side player directory for a tournament: one row per player-per-category
 * (per registration), player highlighted with partner shown subtly. Filterable
 * by category, searchable by player OR partner name.
 *
 * Mirrors the public directory's row shape so both reuse the same list partial;
 * the manager view sees ALL categories (not just listed ones) and links rows to
 * the public player profile (no manager-only profile page exists yet).
 */
class TournamentPlayerController extends Controller
{
    public function index(Tournament $tournament)
    {
        $this->authorize('update', $tournament);
        $categories = $tournament->categories()->orderBy('name')->get(['id', 'name']);

        $pairs = Pair::whereIn('category_id', $categories->pluck('id'))
            ->with([
                'category:id,name',
                'player1:id,name',
                'player2:id,name',
            ])
            ->get();

        $rows = $this->buildPlayerRows($pairs);

        return view('dashboard.players.index', [
            'tournament' => $tournament,
            'categories' => $categories,
            'rows' => $rows,
            'linkPlayers' => true, // link to the public profile page
        ]);
    }

    /** @return Collection<int, array> */
    private function buildPlayerRows(Collection $pairs): Collection
    {
        $rows = collect();

        foreach ($pairs as $pair) {
            $cat = $pair->category;
            $p1 = $pair->player1;
            $p2 = $pair->player2;

            if ($p1) $rows->push($this->playerRow($p1, $p2, $cat, (bool) $pair->is_singles));
            if ($p2) $rows->push($this->playerRow($p2, $p1, $cat, (bool) $pair->is_singles));
        }

        return $rows
            ->sortBy(fn($r) => Str::lower($r['player']['name']) . '|' . $r['category']['name'])
            ->values();
    }

    private function playerRow($player, $partner, $category, bool $isSingles): array
    {
        $partnerName = $partner?->name;

        return [
            'player' => ['id' => $player->id, 'name' => $player->name],
            'partner' => $partnerName,
            'is_singles' => $isSingles,
            'category' => ['id' => $category->id, 'name' => $category->name],
            'search' => Str::lower(trim(($player->name ?? '') . ' ' . ($partnerName ?? ''))),
        ];
    }
}
