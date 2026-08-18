{{--
    Shared ranking-system form fields (name, owner, stacking, active, points).
    Included by create + edit. Expects:
      $achievements  — RankingAchievement[] (order = display order)
      $schedule      — [achievement_key => points]
      $system        — RankingSystem|null (edit vs create)
--}}
@php $system = $system ?? null; @endphp

<div class="tc-card mb-3">
    <div class="tc-card__head"><h3><i class="fa-solid fa-sliders me-1"></i> Datos del sistema</h3></div>
    <div class="tc-card__body">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" style="font-size:13px;font-weight:600;">Nombre</label>
                <input type="text" name="name" required maxlength="120"
                    value="{{ old('name', $system->name ?? '') }}"
                    class="form-control" style="border-radius:var(--radius);"
                    placeholder="Ej. Ranking AVP 2026">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" style="font-size:13px;font-weight:600;">Asociación / dueño <span style="color:var(--text-faint);font-weight:400;">(opcional)</span></label>
                <input type="text" name="owner_label" maxlength="80"
                    value="{{ old('owner_label', $system->owner_label ?? '') }}"
                    class="form-control" style="border-radius:var(--radius);"
                    placeholder="Ej. AVP">
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label" style="font-size:13px;font-weight:600;">Acumulación de puntos</label>
                <select name="stacking" class="form-control" style="border-radius:var(--radius);">
                    @php $stk = old('stacking', $system->stacking ?? 'cumulative'); @endphp
                    <option value="cumulative" @selected($stk==='cumulative')>Acumulativa (suma cada fase alcanzada)</option>
                    <option value="best_only" @selected($stk==='best_only')>Solo el mejor logro</option>
                </select>
                <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">
                    Acumulativa: un campeón suma grupo + cada ronda + finalista + campeón. Solo el mejor: únicamente su logro más alto.
                </div>
            </div>
            <div class="col-12 col-md-6 d-flex align-items-end">
                <label class="d-flex align-items-center gap-2" style="font-size:13px;font-weight:500;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1"
                        @checked(old('is_active', $system->is_active ?? true))>
                    Activo
                </label>
            </div>
        </div>
    </div>
</div>

<div class="tc-card mb-3">
    <div class="tc-card__head"><h3><i class="fa-solid fa-list-ol me-1"></i> Puntos por logro</h3></div>
    <div class="tc-card__body">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
            Puntos que gana <strong>cada jugador</strong> de la pareja al alcanzar cada logro. Deja en 0 los que no apliquen.
            Los logros de eliminación solo se otorgan si la categoría realmente tuvo esa ronda.
        </p>

        <div class="rk-points-grid">
            @foreach($achievements as $a)
            <div class="rk-points-row">
                <label class="rk-points-row__label" for="pts_{{ $a->value }}">{{ $a->label() }}</label>
                <input type="number" min="0" max="100000" id="pts_{{ $a->value }}"
                    name="points[{{ $a->value }}]"
                    value="{{ old('points.'.$a->value, $schedule[$a->value] ?? 0) }}"
                    class="form-control rk-points-row__input">
            </div>
            @endforeach
        </div>
    </div>
</div>
