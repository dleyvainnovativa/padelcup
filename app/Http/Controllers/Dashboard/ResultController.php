<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\MatchResultType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GameMatch;
use App\Models\Tournament;
use App\Services\Tournament\ResultService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResultController extends Controller
{
    public function __construct(private ResultService $results) {}

    /** Match list for a category (group matches + bracket), with entry forms. */
    public function index(Tournament $tournament, Category $category)
    {
        $this->authorize('view', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $groupMatches = $category->matches()
            ->whereNotNull('group_id')
            ->with(['group', 'pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->orderBy('round')->orderBy('slot')->orderBy('id')
            ->get()
            ->groupBy('group_id');

        $bracketMatches = $category->matches()
            ->whereNull('group_id')
            ->with(['pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2'])
            ->orderBy('round')->orderBy('slot')
            ->get();

        return view('dashboard.results.index', compact(
            'tournament',
            'category',
            'groupMatches',
            'bracketMatches'
        ));
    }

    /** Manager enters + confirms a score in one step. */
    public function confirm(Request $request, Tournament $tournament, Category $category, GameMatch $match)
    {
        $this->authorize('update', $category);
        $this->assertMatchInCategory($match, $category);

        $data = $request->validate([
            'sets' => ['required', 'array', 'min:2', 'max:3'],
            'sets.*.0' => ['nullable', 'integer', 'min:0', 'max:7'],
            'sets.*.1' => ['nullable', 'integer', 'min:0', 'max:7'],
        ]);

        if (! $match->isReady()) {
            return back()->withErrors(['result' => 'El partido aún no tiene ambas parejas definidas.']);
        }

        try {
            $this->results->confirm($match, $request->user(), $data['sets']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Resultado confirmado.');
    }

    /** Edit an already-confirmed result. */
    public function edit(Request $request, Tournament $tournament, Category $category, GameMatch $match)
    {
        $this->authorize('update', $category);
        $this->assertMatchInCategory($match, $category);

        $data = $request->validate([
            'sets' => ['required', 'array', 'min:2', 'max:3'],
            'sets.*.0' => ['nullable', 'integer', 'min:0', 'max:7'],
            'sets.*.1' => ['nullable', 'integer', 'min:0', 'max:7'],
        ]);

        try {
            $this->results->edit($match, $data['sets'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Resultado actualizado.');
    }

    /** Record a walkover / retirement / default. */
    public function special(Request $request, Tournament $tournament, Category $category, GameMatch $match)
    {
        $this->authorize('update', $category);
        $this->assertMatchInCategory($match, $category);

        $data = $request->validate([
            'winner_pair_id' => ['required', 'integer', 'in:' . $match->pair_a_id . ',' . $match->pair_b_id],
            'type' => ['required', 'in:walkover,retirement,default'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->results->recordSpecial(
            $match,
            (int) $data['winner_pair_id'],
            MatchResultType::from($data['type']),
            $request->user(),
            $data['note'] ?? null,
        );

        return back()->with('status', 'Resultado registrado.');
    }

    private function assertMatchInCategory(GameMatch $match, Category $category): void
    {
        abort_unless($match->category_id === $category->id, 404);
    }
}
