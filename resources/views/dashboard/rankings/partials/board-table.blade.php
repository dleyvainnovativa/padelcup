{{--
    Reusable ranking board table. Expects:
      $board    — Collection of rows [rank, name, tournaments, points, key]
      $system   — RankingSystem (for the detail link)
      $scope    — current category scope ('all'|category_key) to carry through
      $compact  — bool (optional)
      $linkPlayers — bool (optional, default true): link names to detail
--}}
@php
    $compact = $compact ?? false;
    $linkPlayers = $linkPlayers ?? true;
    $scope = $scope ?? 'all';
@endphp

@if($board->isEmpty())
    <div class="tc-card__body" style="color:var(--text-muted);">Aún no hay puntos en esta tabla.</div>
@else
<div class="tc-table-wrap">
    <table class="tc-table rk-board">
        <thead>
            <tr>
                <th style="width:56px;">#</th>
                <th>Jugador</th>
                @unless($compact)<th style="width:90px;" class="text-end">Torneos</th>@endunless
                <th style="width:110px;" class="text-end">Puntos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($board as $row)
            <tr>
                <td class="rk-board__rank">
                    @if($row['rank'] <= 3)
                        <span class="rk-board__medal rk-board__medal--{{ $row['rank'] }}">{{ $row['rank'] }}</span>
                    @else {{ $row['rank'] }} @endif
                </td>
                <td style="font-weight:600;">
                    @if($linkPlayers && !empty($row['key']))
                        <a href="{{ route('ranking-systems.player', [$system, $row['key'], 'cat' => $scope]) }}" class="rk-board__link">
                            {{ $row['name'] }}
                        </a>
                    @else
                        {{ $row['name'] }}
                    @endif
                </td>
                @unless($compact)<td class="text-end">{{ $row['tournaments'] }}</td>@endunless
                <td class="text-end font-mono" style="font-weight:700;">{{ number_format($row['points']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
