{{-- Match chips for the audit report. $items: [ ['label','vs','court','time','possible','kind'?,'alts'?] ] --}}
<div class="conflict-row__matches">
    @foreach($items as $mi)
    <div class="conflict-row__match">
        <span class="conflict-row__label">
            {{ $mi['label'] }}
            @if(($mi['kind'] ?? null) === 'r2_sure')
                <span class="conflict-row__tag">R2 (uno de {{ 1 + count($mi['alts'] ?? []) }})</span>
            @elseif(($mi['kind'] ?? null) === 'r2_possible' || (!isset($mi['kind']) && !empty($mi['possible'])))
                <span class="conflict-row__tag conflict-row__tag--possible">Posible</span>
            @endif
        </span>
        @if(!empty($mi['vs']))
            <span class="conflict-row__vs">{{ $mi['vs'] }}</span>
        @endif
        <span class="conflict-row__when">
            @if($mi['court'])<i class="fa-solid fa-location-dot"></i> {{ $mi['court'] }} · @endif
            {{ $mi['time'] ?? 'sin hora' }}
        </span>
        @foreach(($mi['alts'] ?? []) as $alt)
            <span class="conflict-row__when conflict-row__alt">
                o {{ $alt['court'] ? $alt['court'] . ' · ' : '' }}{{ $alt['time'] ?? 'sin hora' }}
            </span>
        @endforeach
    </div>
    @endforeach
</div>
