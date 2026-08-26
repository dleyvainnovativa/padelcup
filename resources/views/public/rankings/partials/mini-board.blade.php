{{--
    Compact public board for summary sections — shows top rows only, in the
    pub-rank row style. Expects $board; optional $limit (default 5).
--}}
@php $limit = $limit ?? 5; @endphp

@if($board->isEmpty())
<div class="pub-rank__empty" style="padding:16px;">Sin puntos.</div>
@else
<div class="pub-rank__list">
    @foreach($board->take($limit) as $row)
    <div class="pub-rank__row {{ $row['rank'] <= 3 ? 'pub-rank__row--podium' : '' }}">
        <div class="pub-rank__pos">
            @if($row['rank'] === 1) 🥇
            @elseif($row['rank'] === 2) 🥈
            @elseif($row['rank'] === 3) 🥉
            @else <span class="pub-rank__num">{{ $row['rank'] }}</span>
            @endif
        </div>
        <div class="pub-rank__name">{{ $row['name'] }}</div>
        <div class="pub-rank__pts">{{ number_format($row['points']) }}<span class="pub-rank__pts-label">pts</span></div>
    </div>
    @endforeach
</div>
@endif