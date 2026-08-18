{{--
    Phase 3 — Finalize panel for the tournament show page.
    Replaces/augments the read-only chips from Phase 2 with a finalize/revert
    control per linked ranking system.

    Include in resources/views/dashboard/tournaments/show.blade.php:
      @include('dashboard.tournaments.partials.ranking-finalize')

    Requires $tournament in scope (already present). Loads its ranking systems.
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
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">
            Finaliza para escribir los puntos de este torneo al ranking. Solo se
            cuentan las categorías con final definida. Puedes volver a finalizar
            si editas resultados.
        </p>

        <div class="rk-fin-list">
            @foreach($linked as $sys)
            @php $done = $sys->pivot->finalized_at !== null; @endphp
            <div class="rk-fin-row">
                <div class="rk-fin-row__info">
                    <span class="rk-fin-row__name">{{ $sys->name }}</span>
                    @if($done)
                    <span class="rk-fin-row__status rk-fin-row__status--done">
                        <i class="fa-solid fa-circle-check"></i>
                        Finalizado
                    </span>
                    @else
                    <span class="rk-fin-row__status">
                        <i class="fa-regular fa-clock"></i> Pendiente
                    </span>
                    @endif
                </div>
                <div class="rk-fin-row__actions">
                    <form method="POST" action="{{ route('tournaments.rankings.finalize', [$tournament, $sys]) }}">
                        @csrf
                        <button class="btn {{ $done ? 'btn-soft' : 'btn-accent' }} btn-sm"
                            data-confirm="{{ $done
                                    ? 'Se recalcularán los puntos de este torneo para «'.$sys->name.'», reemplazando los anteriores.'
                                    : 'Se escribirán los puntos de este torneo al ranking «'.$sys->name.'».' }}"
                            data-confirm-title="{{ $done ? 'Recalcular puntos' : 'Finalizar ranking' }}"
                            data-confirm-ok="{{ $done ? 'Recalcular' : 'Finalizar' }}">
                            <i class="fa-solid {{ $done ? 'fa-rotate' : 'fa-flag-checkered' }} me-1"></i>
                            {{ $done ? 'Recalcular' : 'Finalizar' }}
                        </button>
                    </form>

                    @if($done)
                    <form method="POST" action="{{ route('tournaments.rankings.revert', [$tournament, $sys]) }}">
                        @csrf
                        <button class="btn btn-danger btn-sm"
                            data-confirm="Se borrarán los puntos de este torneo para «{{ $sys->name }}» y quedará como pendiente."
                            data-confirm-title="Revertir" data-confirm-ok="Revertir" data-confirm-variant="danger"
                            title="Revertir">
                            <i class="fa-solid fa-arrow-rotate-left"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif