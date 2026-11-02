<?php

namespace App\Http\Controllers;

use App\Enums\RankingAchievement;
use App\Models\RankingSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 1 — Ranking system CRUD + points editor.
 *
 * Manage named ranking systems (AVP, regional leagues, Voleo's own) and the
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

        $system = RankingSystem::create([
            'name'        => $data['name'],
            'owner_label' => $data['owner_label'] ?? null,
            'scope'       => 'player',                 // fixed per current design
            'stacking'    => $data['stacking'],
            'points'      => $data['points'],
            'is_active'   => $request->boolean('is_active', true),
            'created_by'  => Auth::id(),
        ]);

        // Cover image upload (optional). Store on the default disk.
        if ($request->hasFile('cover_image')) {
            $disk = config('filesystems.default');
            $system->update([
                'cover_image_path' => $request->file('cover_image')->store('ranking-covers', $disk),
            ]);
        }

        return redirect()
            ->route('ranking-systems.index')
            ->with('status', 'Sistema de ranking creado.');
    }

    /** Show one system (leaderboard link comes in Phase 4). */
    public function show(RankingSystem $rankingSystem)
    {
        $this->authorizeOwner($rankingSystem);

        $rankingSystem->loadCount('tournaments');

        // Per-tournament config (items #4 + #5): each linked tournament with its
        // categories, which are excluded, and whether it uses an alternate points
        // schedule. Pivot JSON columns aren't cast, so decode defensively.
        $tournaments = $rankingSystem->tournaments()
            ->with(['categories:id,tournament_id,name'])
            ->orderBy('name')
            ->get()
            ->map(function ($t) {
                $excluded = $this->decodePivotArray($t->pivot->excluded_category_ids) ?? [];
                $override = $this->decodePivotArray($t->pivot->points_override);
                return [
                    'id'          => $t->id,
                    'name'        => $t->name,
                    'finalized'   => $t->pivot->finalized_at !== null,
                    'categories'  => $t->categories->map(fn ($c) => [
                        'id'       => $c->id,
                        'name'     => $c->name,
                        'included' => ! in_array((int) $c->id, array_map('intval', $excluded), true),
                    ])->values(),
                    'custom_points' => $override !== null,
                    'points'        => $override, // null when using the default schedule
                ];
            });

        return view('ranking-systems.show', [
            'system'          => $rankingSystem,
            'achievements'    => RankingAchievement::cases(),
            'schedule'        => $rankingSystem->schedule(),
            'linkedTournaments' => $tournaments,
        ]);
    }

    /**
     * Save a tournament's ranking config within this system (items #4 + #5):
     *   - which categories are included (excluded = the rest)
     *   - an optional alternate points schedule ("subranking") for this
     *     tournament, stored on the pivot; null = use the system default.
     * Nothing is recomputed here — the manager re-finalizes the tournament to
     * apply the change to the shared ledger/leaderboard.
     */
    public function updateTournamentConfig(Request $request, RankingSystem $rankingSystem, \App\Models\Tournament $tournament)
    {
        $this->authorizeOwner($rankingSystem);
        abort_unless(
            $rankingSystem->tournaments()->whereKey($tournament->id)->exists(),
            404,
            'Ese torneo no está vinculado a este ranking.'
        );

        // Included category ids (checkboxes). Excluded = every category of the
        // tournament NOT in this list.
        $included = array_map('intval', (array) $request->input('categories', []));
        $allIds = $tournament->categories()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $excluded = array_values(array_diff($allIds, $included));

        // Alternate points schedule — only when the custom toggle is on.
        $pointsOverride = null;
        if ($request->boolean('custom_points')) {
            $pointsOverride = [];
            foreach (RankingAchievement::cases() as $a) {
                $pointsOverride[$a->value] = (int) $request->input("points.{$a->value}", 0);
            }
        }

        $rankingSystem->tournaments()->updateExistingPivot($tournament->id, [
            'excluded_category_ids' => empty($excluded) ? null : json_encode($excluded),
            'points_override'       => $pointsOverride === null ? null : json_encode($pointsOverride),
        ]);

        return back()->with('status', "Configuración de «{$tournament->name}» guardada. Vuelve a finalizar el torneo para aplicar los cambios al ranking.");
    }

    /** Decode a pivot JSON column to an array (pivot attrs aren't cast). */
    private function decodePivotArray($raw): ?array
    {
        if (is_array($raw)) return $raw;
        if (blank($raw)) return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
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

        // Cover image upload (optional). Replace any previous image.
        if ($request->hasFile('cover_image')) {
            $disk = config('filesystems.default');
            if ($rankingSystem->cover_image_path) {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($rankingSystem->cover_image_path);
            }
            $rankingSystem->update([
                'cover_image_path' => $request->file('cover_image')->store('ranking-covers', $disk),
            ]);
        }

        return redirect()
            ->route('ranking-systems.index')
            ->with('status', 'Sistema de ranking actualizado.');
    }

    /**
     * Clone a system (name + points schedule), useful to spin up a regional
     * variant from the Voleo defaults or duplicate AVP's for a new season.
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
        $keys = array_map(fn($a) => $a->value, RankingAchievement::cases());

        $rules = [
            'name'        => ['required', 'string', 'max:120'],
            'owner_label' => ['nullable', 'string', 'max:80'],
            'stacking'    => ['required', 'in:cumulative,best_only'],
            'is_active'   => ['nullable', 'boolean'],
            'points'      => ['array'],
            'cover_image' => ['nullable', 'image', 'max:4096'], // 4 MB, like tournaments
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
