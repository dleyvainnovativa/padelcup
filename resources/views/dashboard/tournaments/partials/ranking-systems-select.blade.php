{{--
    Phase 2 — Ranking systems selector for the tournament create/edit form.

    Drop this include INSIDE your tournament <form> in both:
      resources/views/dashboard/tournaments/create.blade.php
      resources/views/dashboard/tournaments/edit.blade.php
    (or in the shared _form partial if you have one), e.g. near the "is_listed"
    toggle.

    It renders a checkbox list of the manager's ranking systems. Checked ones
    post as ranking_system_ids[]. On edit it pre-checks currently linked systems.

    Requires in scope:
      $tournament  — the model on edit; NULL/undefined on create (handled).

    Data source: we fetch the manager's active systems here via a view composer
    OR you can pass $rankingSystems from the controller. To keep this drop-in
    with ZERO controller-view wiring, it reads from a helper on the fly. If you
    prefer explicit passing, see the note at the bottom.
--}}
@php
    $tournament = $tournament ?? null;

    // Manager's own systems. Prefer a controller-provided $rankingSystems; else
    // query inline (safe: scoped to the auth manager).
    $rankingSystems = $rankingSystems
        ?? \App\Models\RankingSystem::query()
            ->where('created_by', auth()->id())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

    // Pre-checked ids on edit (old() wins after a validation bounce).
    $linkedIds = old(
        'ranking_system_ids',
        $tournament
            ? $tournament->rankingSystems->pluck('id')->all()
            : []
    );
    $linkedIds = array_map('intval', $linkedIds);

    // Which linked systems are already finalized (lock visual hint on edit).
    $finalizedIds = $tournament
        ? $tournament->rankingSystems
            ->filter(fn ($s) => $s->pivot->finalized_at !== null)
            ->pluck('id')->all()
        : [];
@endphp

<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-ranking-star me-1"></i> Rankings</h3>
    </div>
    <div class="tc-card__body">
        @if($rankingSystems->isEmpty())
            <p style="font-size:13px;color:var(--text-muted);margin:0;">
                No tienes sistemas de ranking.
                <a href="{{ route('ranking-systems.create') }}">Crea uno</a>
                para que este torneo otorgue puntos.
            </p>
        @else
            <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">
                Selecciona a qué rankings aporta puntos este torneo. Puede aportar a más de uno.
            </p>

            <div class="rk-link-list">
                @foreach($rankingSystems as $sys)
                    @php
                        $checked    = in_array($sys->id, $linkedIds, true);
                        $isFinal    = in_array($sys->id, $finalizedIds, true);
                    @endphp
                    <label class="rk-link-row {{ $checked ? 'is-checked' : '' }}">
                        <input type="checkbox" name="ranking_system_ids[]"
                            value="{{ $sys->id }}" @checked($checked)>
                        <span class="rk-link-row__body">
                            <span class="rk-link-row__name">{{ $sys->name }}</span>
                            @if($sys->owner_label)
                                <span class="rk-link-row__owner">{{ $sys->owner_label }}</span>
                            @endif
                        </span>
                        @if($isFinal)
                            <span class="rk-link-row__badge" title="Ya finalizado para este ranking">
                                <i class="fa-solid fa-lock"></i> finalizado
                            </span>
                        @elseif(!$sys->is_active)
                            <span class="rk-link-row__badge rk-link-row__badge--muted">inactivo</span>
                        @endif
                    </label>
                @endforeach
            </div>

            @error('ranking_system_ids')
                <div style="font-size:12px;color:var(--danger-text);margin-top:8px;">{{ $message }}</div>
            @enderror
        @endif
    </div>
</div>

{{--
    OPTIONAL — explicit controller passing instead of the inline query above.
    If you'd rather pass the data, add to TournamentController@create and @edit:

        $rankingSystems = \App\Models\RankingSystem::where('created_by', auth()->id())
            ->orderByDesc('is_active')->orderBy('name')->get();
        return view('dashboard.tournaments.create', compact('rankingSystems'));
        // edit: compact('tournament', 'scheduledCount', 'rankingSystems')

    Then delete the inline $rankingSystems ??= ... fallback at the top. The
    partial works either way.
--}}
