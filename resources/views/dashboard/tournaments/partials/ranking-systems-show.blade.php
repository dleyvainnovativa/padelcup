{{--
    Phase 2 — OPTIONAL read-only display of linked rankings on the tournament
    show page. Include where you want it in
      resources/views/dashboard/tournaments/show.blade.php
    e.g. near the tournament meta/summary.

    Requires $tournament in scope (already present on that page).
--}}
@php
    $linked = $tournament->rankingSystems ?? collect();
@endphp

@if($linked->isNotEmpty())
<div class="tc-card mb-3">
    <div class="tc-card__head">
        <h3><i class="fa-solid fa-ranking-star me-1"></i> Rankings</h3>
    </div>
    <div class="tc-card__body">
        <div class="rk-chip-row">
            @foreach($linked as $sys)
                <span class="rk-chip {{ $sys->pivot->finalized_at ? 'rk-chip--done' : '' }}">
                    {{ $sys->name }}
                    @if($sys->pivot->finalized_at)
                        <i class="fa-solid fa-lock" title="Finalizado"></i>
                    @endif
                </span>
            @endforeach
        </div>
        <p style="font-size:11px;color:var(--text-faint);margin:10px 0 0;">
            Este torneo otorga puntos a los rankings mostrados. El bloqueo indica que ya fue finalizado (Fase 3).
        </p>
    </div>
</div>
@endif

{{-- Chip styles (or move to theme.css):
.rk-chip-row { display:flex; flex-wrap:wrap; gap:6px; }
.rk-chip {
  display:inline-flex; align-items:center; gap:6px;
  font-size:12px; font-weight:600; padding:5px 10px; border-radius:999px;
  background: color-mix(in srgb, var(--accent) 8%, var(--surface));
  color: var(--accent); border:1px solid color-mix(in srgb, var(--accent) 25%, transparent);
}
.rk-chip--done { background: var(--surface); color: var(--text-muted);
  border-color: var(--border-strong); }
--}}
