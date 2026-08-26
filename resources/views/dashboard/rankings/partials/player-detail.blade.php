{{--
    Shared player-detail body. Expects $p (from RankingPlayerDetail::for):
      name, total, tournaments, rank, scope_label, breakdown[], sources[]
--}}
<div class="rk-pd">

    {{-- Header (skippable when the surrounding view already shows one) --}}
    @unless($hideHeader ?? false)
    <div class="rk-pd__head">
        <div class="rk-pd__name">{{ $p['name'] }}</div>
        <div class="rk-pd__meta">
            @if($p['rank'])<span class="rk-pd__rank">#{{ $p['rank'] }} en {{ $p['scope_label'] }}</span>@endif
            <span>{{ $p['tournaments'] }} torneo(s)</span>
        </div>
        <div class="rk-pd__total">
            <span class="rk-pd__total-num">{{ number_format($p['total']) }}</span>
            <span class="rk-pd__total-label">puntos</span>
        </div>
    </div>
    @endunless

    {{-- Match record (W/L) — only when there are played matches --}}
    @if(!empty($p['record']) && $p['record']['played'] > 0)
    @php $r = $p['record']; @endphp
    <div class="rk-pd__record">
        <div class="rk-pd__stat">
            <div class="rk-pd__stat-num">{{ $r['played'] }}</div>
            <div class="rk-pd__stat-label">Jugados</div>
        </div>
        <div class="rk-pd__stat rk-pd__stat--win">
            <div class="rk-pd__stat-num">{{ $r['won'] }}</div>
            <div class="rk-pd__stat-label">Ganados</div>
        </div>
        <div class="rk-pd__stat rk-pd__stat--loss">
            <div class="rk-pd__stat-num">{{ $r['lost'] }}</div>
            <div class="rk-pd__stat-label">Perdidos</div>
        </div>
        @if($r['win_pct'] !== null)
        <div class="rk-pd__stat">
            <div class="rk-pd__stat-num">{{ $r['win_pct'] }}%</div>
            <div class="rk-pd__stat-label">Efectividad</div>
        </div>
        @endif
        @if($r['sets_won'] + $r['sets_lost'] > 0)
        <div class="rk-pd__stat">
            <div class="rk-pd__stat-num">{{ $r['sets_won'] }}–{{ $r['sets_lost'] }}</div>
            <div class="rk-pd__stat-label">Sets</div>
        </div>
        @endif
    </div>
    @endif

    {{-- Points justification: one bar per achievement type --}}
    <div class="rk-pd__section-title">Cómo se ganaron los puntos</div>
    <div class="rk-pd__bars">
        @foreach($p['breakdown'] as $b)
        <div class="rk-pd__bar-row">
            <div class="rk-pd__bar-label">
                {{ $b['label'] }}
                @if($b['count'] > 1)<span class="rk-pd__bar-count">×{{ $b['count'] }}</span>@endif
            </div>
            <div class="rk-pd__bar-track">
                <div class="rk-pd__bar-fill" style="width:{{ max(4, $b['bar']) }}%;"></div>
            </div>
            <div class="rk-pd__bar-pts">{{ number_format($b['points']) }}</div>
        </div>
        @endforeach
    </div>

    {{-- Sourcing: where the points came from --}}
    <div class="rk-pd__section-title">Detalle por torneo y categoría</div>
    <div class="rk-pd__sources">
        @foreach($p['sources'] as $s)
        <div class="rk-pd__source">
            <div class="rk-pd__source-head">
                <div>
                    <div class="rk-pd__source-tour">{{ $s['tournament'] }}</div>
                    <div class="rk-pd__source-cat">{{ $s['category'] }}</div>
                </div>
                <div class="rk-pd__source-pts">{{ number_format($s['points']) }} pts</div>
            </div>
            <div class="rk-pd__source-tags">
                @foreach($s['achievements'] as $a)
                    <span class="rk-pd__tag">{{ $a['label'] }} · {{ $a['points'] }}</span>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

</div>
