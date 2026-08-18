{{--
    Phase 2 — Bulk "categorías que necesitan regenerarse" panel.

    Include on the tournament show page, ABOVE the categories list
    (before the @forelse ($tournament->categories ...) around line 104):

        @include('dashboard.tournaments.partials.structure-bulk')

    Shows only categories whose structure is stale. Per-row action reuses the
    Phase 1 routes; a "Regenerar todas las seguras" button appears when at least
    one lossless category exists. Nothing risky is force-regenerated in bulk.

    Self-computes via CategoryStructureState (no controller change needed).
    Cleaner option: pass $structures from the controller — note at the bottom.

    Requires $tournament in scope (already on the page).
--}}
@php
    $state = app(\App\Services\Tournament\CategoryStructureState::class);
    $locked = $tournament->isLocked();

    // Build [category, snapshot] for stale categories only.
    $stale = collect($tournament->categories)
        ->map(fn ($c) => ['cat' => $c, 'snap' => $state->for($c)])
        ->filter(fn ($row) => $row['snap']['stale'])
        ->values();

    $safeCount = $stale->filter(fn ($r) =>
        $r['snap']['can_regenerate_safely'] || $r['snap']['can_rebuild_bracket_only']
    )->count();
@endphp

@if($stale->isNotEmpty() && !$locked)
<div class="tc-card mb-3 rk-bulk">
    <div class="tc-card__head" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
        <div>
            <h3 style="margin:0;"><i class="fa-solid fa-arrows-rotate me-1"></i> Categorías por regenerar</h3>
            <p style="font-size:12px;color:var(--text-muted);margin:4px 0 0;">
                Las parejas o la configuración cambiaron en {{ $stale->count() }} categoría(s).
            </p>
        </div>
        @if($safeCount > 0)
        <form method="POST" action="{{ route('tournaments.regenerateSafe', $tournament) }}">
            @csrf
            <button class="btn btn-accent btn-sm"
                data-confirm="Se regenerarán {{ $safeCount }} categoría(s) sin resultados. Las que tengan partidos jugados se omitirán."
                data-confirm-title="Regenerar categorías seguras"
                data-confirm-ok="Regenerar {{ $safeCount }}">
                <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Regenerar todas las seguras ({{ $safeCount }})
            </button>
        </form>
        @endif
    </div>

    <div class="tc-table-wrap">
        <table class="tc-table rk-bulk__table">
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th class="text-end">Parejas</th>
                    <th class="text-end">Resultados</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($stale as $row)
                    @php
                        $c = $row['cat']; $s = $row['snap'];
                        $bracketOnly = $s['bracket_stale'] && !$s['group_stale'] && $s['can_rebuild_bracket_only'];
                        $safe = $s['can_regenerate_safely'];
                    @endphp
                    <tr>
                        <td style="font-weight:600;">
                            <a href="{{ route('categories.show', [$tournament, $c]) }}">{{ $c->name }}</a>
                        </td>
                        <td>
                            @if($bracketOnly)
                                <span class="rk-bulk__badge rk-bulk__badge--info">Llave desalineada</span>
                            @elseif($safe)
                                <span class="rk-bulk__badge rk-bulk__badge--info">Parejas cambiaron</span>
                            @else
                                <span class="rk-bulk__badge rk-bulk__badge--danger">Con resultados</span>
                            @endif
                        </td>
                        <td class="text-end pub-mono">
                            {{ $s['grouped_pairs'] }} / {{ $s['pool_pairs'] }}
                        </td>
                        <td class="text-end pub-mono">
                            {{ $s['results_at_risk'] > 0 ? $s['results_at_risk'] : '—' }}
                        </td>
                        <td class="text-end">
                            @if($bracketOnly)
                                <form method="POST" action="{{ route('categories.rebuildBracket', [$tournament, $c]) }}">
                                    @csrf
                                    <button class="btn btn-soft btn-sm"><i class="fa-solid fa-sitemap me-1"></i> Reconstruir llave</button>
                                </form>
                            @elseif($safe)
                                <form method="POST" action="{{ route('categories.regenerate', [$tournament, $c]) }}">
                                    @csrf
                                    <button class="btn btn-accent btn-sm"><i class="fa-solid fa-arrows-rotate me-1"></i> Regenerar</button>
                                </form>
                            @else
                                {{-- Risky: no bulk force. Link to the per-category page for a deliberate confirm. --}}
                                <a href="{{ route('categories.show', [$tournament, $c]) }}" class="btn btn-soft btn-sm">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> Revisar
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{--
    OPTIONAL cleaner wiring — pass $structures from TournamentController::show()
    instead of resolving inline:

        use App\Services\Tournament\CategoryStructureState;
        public function show(Tournament $tournament, CategoryStructureState $state) {
            // ...existing...
            $structures = $tournament->categories->mapWithKeys(
                fn ($c) => [$c->id => $state->for($c)]
            );
            return view('dashboard.tournaments.show', compact('tournament', 'structures'));
        }

    Then read $structures[$c->id] in this partial instead of calling the service.
    (Inline is fine for typical category counts; pass-in avoids re-querying if the
    page grows large.)
--}}
