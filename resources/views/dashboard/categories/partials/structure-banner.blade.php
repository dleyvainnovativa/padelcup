{{--
    Phase 1 — Structure regeneration banner for the category show page.

    Include right after the <div class="page-head"> block in
    resources/views/dashboard/categories/show.blade.php:

        @include('dashboard.categories.partials.structure-banner')

    Shows ONLY when the category's structure is stale. Wording depends on state:
      - fresh          → calm "las parejas cambiaron · regenerar ahora" (one tap)
      - in-progress /  → danger "regenerar borrará N resultados" (force confirm
        bracket-started    via the calendar-style modal / data-confirm)
      - bracket-stale  → lossless "reconstruir llave"

    Self-computes the state via CategoryStructureState so no controller change is
    needed. (Cleaner option: have CategoryController::show pass $structure and
    delete the inline resolve below — see note at the bottom.)

    Requires $tournament and $category in scope (both already on the page).
--}}
@php
    $structure = $structure
        ?? app(\App\Services\Tournament\CategoryStructureState::class)->for($category);

    $locked = $tournament->isLocked();
@endphp

@if($structure['stale'] && !$locked)
    @php
        $state = $structure['state'];
        $atRisk = $structure['results_at_risk'];
        // bracket-only path: only the bracket is stale and it's rebuildable losslessly
        $bracketOnly = $structure['bracket_stale']
            && !$structure['group_stale']
            && $structure['can_rebuild_bracket_only'];
        $safe = $structure['can_regenerate_safely']; // fresh
    @endphp

    <div class="tc-card mb-3 rk-regen-banner rk-regen-banner--{{ $safe || $bracketOnly ? 'info' : 'danger' }}">
        <div class="tc-card__body" style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
            <div style="flex:1 1 320px;min-width:0;">
                @if($bracketOnly)
                    <div class="rk-regen-banner__title">
                        <i class="fa-solid fa-sitemap me-1"></i> La configuración de la llave cambió
                    </div>
                    <div class="rk-regen-banner__text">
                        Cambió “avanzan por grupo” o “clasificados extra”. Puedes reconstruir la
                        llave sin afectar los resultados de grupos.
                    </div>
                @elseif($safe)
                    <div class="rk-regen-banner__title">
                        <i class="fa-solid fa-arrows-rotate me-1"></i> Las parejas cambiaron
                    </div>
                    <div class="rk-regen-banner__text">
                        El número de parejas ya no coincide con los grupos actuales
                        ({{ $structure['grouped_pairs'] }} en grupos · {{ $structure['pool_pairs'] }} confirmadas).
                        Regenerar es seguro: todavía no hay resultados.
                    </div>
                @else
                    <div class="rk-regen-banner__title">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Las parejas cambiaron y ya hay resultados
                    </div>
                    <div class="rk-regen-banner__text">
                        Regenerar los grupos borrará <strong>{{ $atRisk }}</strong>
                        resultado(s) ya {{ $atRisk === 1 ? 'jugado' : 'jugados' }}. Solo hazlo si estás seguro.
                    </div>
                @endif
            </div>

            <div class="rk-regen-banner__actions" style="display:flex;gap:8px;flex:none;">
                @if($bracketOnly)
                    <form method="POST" action="{{ route('categories.rebuildBracket', [$tournament, $category]) }}">
                        @csrf
                        <button class="btn btn-accent btn-sm">
                            <i class="fa-solid fa-sitemap me-1"></i> Reconstruir llave
                        </button>
                    </form>
                @elseif($safe)
                    <form method="POST" action="{{ route('categories.regenerate', [$tournament, $category]) }}">
                        @csrf
                        <button class="btn btn-accent btn-sm">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Regenerar ahora
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('categories.regenerate', [$tournament, $category]) }}">
                        @csrf
                        <input type="hidden" name="force" value="1">
                        <button class="btn btn-danger btn-sm"
                            data-confirm="Se borrarán {{ $atRisk }} resultado(s) ya jugados y se regenerarán los grupos{{ $category->format->hasBracket() ? ' y la llave' : '' }}. Esta acción no se puede deshacer."
                            data-confirm-title="Regenerar y borrar resultados"
                            data-confirm-ok="Regenerar y borrar"
                            data-confirm-variant="danger">
                            <i class="fa-solid fa-arrows-rotate me-1"></i> Regenerar de todos modos
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endif

{{--
    OPTIONAL cleaner wiring — pass $structure from the controller instead of the
    inline app() resolve. In CategoryController::show():

        use App\Services\Tournament\CategoryStructureState;
        public function show(Tournament $tournament, Category $category, CategoryStructureState $state) {
            // ...existing loads...
            $structure = $state->for($category);
            return view('dashboard.tournaments.categories.show', compact('tournament','category','structure'));
        }

    Then remove the `$structure ??= app(...)` fallback at the top of this file.
--}}
