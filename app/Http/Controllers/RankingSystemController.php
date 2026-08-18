<?php

namespace App\Http\Controllers;

use App\Enums\RankingAchievement;
use App\Models\RankingSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 1 — Ranking system CRUD + points editor.
 *
 * Manage named ranking systems (AVP, regional leagues, PadelCup's own) and the
 * points awarded for each achievement. Scoped to the signed-in manager via
 * created_by, mirroring the rest of the app.
 *
 * Points are stored as a JSON map { achievement_key => points } on the system.
 * The editor always renders all 8 achievements from the RankingAchievement enum
 * so a partial/empty schedule still shows every field.
 */
class RankingSystemController extends Controller
{
    /** List this manager's ranking systems. */
    public function index()
    {
        $systems = RankingSystem::query()
            ->where('created_by', Auth::id())
            ->withCount('tournaments')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('ranking-systems.index', [
            'systems' => $systems,
        ]);
    }

    /** New-system form, pre-filled with default placeholder points. */
    public function create()
    {
        return view('ranking-systems.create', [
            'achievements' => RankingAchievement::cases(),
            'schedule'     => RankingAchievement::defaultSchedule(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        RankingSystem::create([
            'name'        => $data['name'],
            'owner_label' => $data['owner_label'] ?? null,
            'scope'       => 'player',                 // fixed per current design
            'stacking'    => $data['stacking'],
            'points'      => $data['points'],
            'is_active'   => $request->boolean('is_active', true),
            'created_by'  => Auth::id(),
        ]);

        return redirect()
            ->route('ranking-systems.index')
            ->with('status', 'Sistema de ranking creado.');
    }

    /** Show one system (leaderboard link comes in Phase 4). */
    public function show(RankingSystem $rankingSystem)
    {
        $this->authorizeOwner($rankingSystem);

        $rankingSystem->loadCount('tournaments');

        return view('ranking-systems.show', [
            'system'       => $rankingSystem,
            'achievements' => RankingAchievement::cases(),
            'schedule'     => $rankingSystem->schedule(),
        ]);
    }

    public function edit(RankingSystem $rankingSystem)
    {
        $this->authorizeOwner($rankingSystem);

        return view('ranking-systems.edit', [
            'system'       => $rankingSystem,
            'achievements' => RankingAchievement::cases(),
            'schedule'     => $rankingSystem->schedule(),
        ]);
    }

    public function update(Request $request, RankingSystem $rankingSystem)
    {
        $this->authorizeOwner($rankingSystem);

        $data = $this->validated($request);

        $rankingSystem->update([
            'name'        => $data['name'],
            'owner_label' => $data['owner_label'] ?? null,
            'stacking'    => $data['stacking'],
            'points'      => $data['points'],
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('ranking-systems.index')
            ->with('status', 'Sistema de ranking actualizado.');
    }

    /**
     * Clone a system (name + points schedule), useful to spin up a regional
     * variant from the PadelCup defaults or duplicate AVP's for a new season.
     */
    public function duplicate(RankingSystem $rankingSystem)
    {
        $this->authorizeOwner($rankingSystem);

        $copy = $rankingSystem->replicate(['created_at', 'updated_at']);
        $copy->name = $rankingSystem->name . ' (copia)';
        $copy->created_by = Auth::id();
        $copy->save();

        return redirect()
            ->route('ranking-systems.edit', $copy)
            ->with('status', 'Copia creada. Ajusta el nombre y los puntos.');
    }

    public function destroy(RankingSystem $rankingSystem)
    {
        $this->authorizeOwner($rankingSystem);

        // Deleting a system cascades its pivot links and ledger rows (Phase 0
        // migration uses cascadeOnDelete). Guard in the view with data-confirm.
        $rankingSystem->delete();

        return redirect()
            ->route('ranking-systems.index')
            ->with('status', 'Sistema de ranking eliminado.');
    }

    // --- helpers ----------------------------------------------------------

    /**
     * Validate the form. Points arrive as points[<achievement_key>] = int and
     * are normalized to a clean map keyed only by valid achievements (unknown
     * keys dropped, missing ones defaulted to 0).
     */
    private function validated(Request $request): array
    {
        $keys = array_map(fn ($a) => $a->value, RankingAchievement::cases());

        $rules = [
            'name'        => ['required', 'string', 'max:120'],
            'owner_label' => ['nullable', 'string', 'max:80'],
            'stacking'    => ['required', 'in:cumulative,best_only'],
            'is_active'   => ['nullable', 'boolean'],
            'points'      => ['array'],
        ];
        foreach ($keys as $k) {
            $rules["points.$k"] = ['nullable', 'integer', 'min:0', 'max:100000'];
        }

        $request->validate($rules);

        // Normalize points to every achievement (0 if blank), drop unknown keys.
        $points = [];
        foreach ($keys as $k) {
            $points[$k] = (int) $request->input("points.$k", 0);
        }

        return [
            'name'        => $request->input('name'),
            'owner_label' => $request->input('owner_label'),
            'stacking'    => $request->input('stacking'),
            'points'      => $points,
        ];
    }

    private function authorizeOwner(RankingSystem $system): void
    {
        abort_unless($system->created_by === Auth::id(), 403);
    }
}
