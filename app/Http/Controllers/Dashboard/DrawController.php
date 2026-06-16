<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CategoryFormat;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Tournament;
use App\Services\Tournament\BracketService;
use App\Services\Tournament\GroupGenerationService;
use App\Services\Tournament\StandingsService;
use Illuminate\Http\Request;

class DrawController extends Controller
{
    public function __construct(
        private GroupGenerationService $groups,
        private BracketService $brackets,
        private StandingsService $standings,
    ) {}

    /** Move a pair between groups or to/from the unassigned pool (pre-lock). */
    public function movePair(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);
        abort_if($tournament->isLocked(), 403, 'El torneo ya inició; no se pueden mover parejas.');

        $data = $request->validate([
            'pair_id' => ['required', 'integer'],
            // 0 (or null) means the unassigned pool on either end.
            'from_group_id' => ['nullable', 'integer'],
            'to_group_id' => ['nullable', 'integer'],
        ]);

        $pair = \App\Models\Pair::findOrFail($data['pair_id']);
        abort_unless($pair->category_id === $category->id, 404);

        $from = ! empty($data['from_group_id'])
            ? \App\Models\Group::where('category_id', $category->id)->findOrFail($data['from_group_id'])
            : null;
        $to = ! empty($data['to_group_id'])
            ? \App\Models\Group::where('category_id', $category->id)->findOrFail($data['to_group_id'])
            : null;

        // No-op if both ends are the pool.
        if (! $from && ! $to) {
            return response()->json(['ok' => true, 'warning' => null]);
        }

        // If moving out of a group, the pair must actually be in it.
        if ($from) {
            abort_unless($from->pairs()->where('pairs.id', $pair->id)->exists(), 422, 'La pareja no está en el grupo de origen.');
        }

        $result = $this->groups->movePair($pair, $from, $to);

        return response()->json(['ok' => true, 'warning' => $result['warning']]);
    }

    /** Group generation preview (no writes). */
    public function previewGroups(Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $pairs = $category->poolPairs()->with(['player1', 'player2'])->get();
        $preview = $this->groups->preview($category, $pairs);

        return view('dashboard.groups.preview', compact('tournament', 'category', 'preview', 'pairs'));
    }

    /** Commit groups + round-robin matches (pre-lock only). */
    public function generateGroups(Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);
        abort_if($tournament->isLocked(), 403, 'El torneo ya inició; no se pueden regenerar los grupos.');

        $pairs = $category->poolPairs()->with(['player1', 'player2'])->get();

        if ($pairs->count() < 2) {
            return back()->withErrors(['draw' => 'Se necesitan al menos 2 parejas confirmadas.']);
        }

        $this->groups->generate($category, $pairs);

        return redirect()
            ->route('draw.groups', [$tournament, $category])
            ->with('status', 'Grupos y partidos generados.');
    }

    /** Show groups + current standings. */
    public function groups(Tournament $tournament, Category $category)
    {
        $this->authorize('view', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $groups = $category->groups()->with('pairs.player1', 'pairs.player2')->get();
        $standings = $groups->mapWithKeys(fn($g) => [$g->id => $this->standings->forGroup($g)]);
        $unassigned = $this->groups->unassignedPairs($category);

        return view('dashboard.groups.index', compact('tournament', 'category', 'groups', 'standings', 'unassigned'));
    }

    /** Build the elimination bracket (elimination or hybrid). */
    public function buildBracket(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        if ($category->format === CategoryFormat::Hybrid) {
            // If the group phase isn't finished, build a POSITIONAL bracket with
            // seed labels (A1 vs B2…) — pairs bind automatically once groups end.
            if (! $this->brackets->groupsComplete($category)) {
                $this->brackets->buildPositional($category);
                return redirect()->route('draw.bracket', [$tournament, $category])
                    ->with('status', 'Llave preliminar generada con posiciones (A1, B2…). Las parejas se asignarán al terminar los grupos. Puedes intercambiar posiciones antes de que inicien.');
            }

            $result = $this->brackets->qualifiers($category);

            // Unresolved boundary tie → ask the manager to choose.
            if ($result['tie']) {
                if ($request->filled('resolved')) {
                    $chosen = array_map('intval', $request->input('resolved', []));
                    $seeds = $this->mergeResolvedTie($result, $chosen);
                    $this->brackets->build($category, $seeds);
                    return redirect()->route('draw.bracket', [$tournament, $category])
                        ->with('status', 'Llave generada.');
                }

                return view('dashboard.brackets.resolve-tie', [
                    'tournament' => $tournament,
                    'category' => $category,
                    'tie' => $result['tie'],
                ]);
            }

            $this->brackets->build($category, $result['qualifiers']);
        } else {
            // Pure elimination: seed by registration order (or manual seed later).
            $seedIds = $category->poolPairs()->orderBy('seed')->pluck('id')->all();
            $this->brackets->build($category, $seedIds);
        }

        return redirect()
            ->route('draw.bracket', [$tournament, $category])
            ->with('status', 'Llave generada.');
    }

    /** Swap two round-1 bracket slots (manual seeding adjustment). */
    public function swapBracket(Request $request, Tournament $tournament, Category $category)
    {
        $this->authorize('update', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $data = $request->validate([
            'match_a' => ['required', 'integer'],
            'side_a' => ['required', 'in:a,b'],
            'match_b' => ['required', 'integer'],
            'side_b' => ['required', 'in:a,b'],
        ]);

        $a = \App\Models\GameMatch::where('category_id', $category->id)->findOrFail($data['match_a']);
        $b = \App\Models\GameMatch::where('category_id', $category->id)->findOrFail($data['match_b']);

        // Only allow swapping before the bracket has results.
        abort_if($a->state === \App\Enums\MatchState::Confirmed || $b->state === \App\Enums\MatchState::Confirmed, 422, 'No se puede mover un partido ya jugado.');

        $this->brackets->swapSlots($a, $data['side_a'], $b, $data['side_b']);

        return back()->with('status', 'Posiciones intercambiadas.');
    }

    /** Show the bracket. */
    public function bracket(Tournament $tournament, Category $category)
    {
        $this->authorize('view', $category);
        abort_unless($category->tournament_id === $tournament->id, 404);

        $matches = $category->matches()
            ->whereNull('group_id')
            ->with('pairA.player1', 'pairA.player2', 'pairB.player1', 'pairB.player2')
            ->orderBy('round')->orderBy('slot')
            ->get()
            ->groupBy('round');

        return view('dashboard.brackets.index', compact('tournament', 'category', 'matches'));
    }

    /**
     * Merge the manager's tie resolution into the qualifier list: auto
     * qualifiers + the manager-chosen extras (in the order they picked).
     */
    private function mergeResolvedTie(array $result, array $chosen): array
    {
        // qualifiers already contains the auto qualifiers; tie['pairs'] were the
        // contested ones. Recompute auto (non-tied) then append chosen.
        $tiePairs = $result['tie']['pairs'];
        $auto = array_values(array_filter(
            $result['qualifiers'],
            fn($id) => ! in_array($id, $tiePairs, true)
        ));
        return array_merge($auto, $chosen);
    }
}
