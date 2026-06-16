<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <style>
        * {
            font-family: DejaVu Sans, sans-serif;
        }

        body {
            font-size: 11px;
            color: #1a1a2e;
            margin: 0;
        }

        .head {
            border-bottom: 2px solid #635bff;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .head h1 {
            font-size: 20px;
            margin: 0 0 4px;
            color: #111;
        }

        .head .sub {
            font-size: 11px;
            color: #666;
        }

        .day {
            margin-bottom: 18px;
        }

        .day-title {
            background: #635bff;
            color: #fff;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: capitalize;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #888;
            padding: 5px 8px;
            border-bottom: 1px solid #ddd;
            letter-spacing: .03em;
        }

        td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
            font-size: 11px;
            vertical-align: top;
        }

        .time {
            font-weight: bold;
            white-space: nowrap;
        }

        .court {
            color: #635bff;
            font-weight: bold;
            white-space: nowrap;
        }

        .ctx {
            color: #888;
            font-size: 9px;
            text-transform: uppercase;
        }

        .cat {
            color: #555;
            font-size: 10px;
        }

        .vs {
            color: #aaa;
            font-size: 9px;
        }

        .score {
            font-weight: bold;
            white-space: nowrap;
        }

        .foot {
            margin-top: 20px;
            font-size: 9px;
            color: #999;
            text-align: center;
        }

        .empty {
            color: #999;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="head">
        <h1>{{ $tournament->name }}</h1>
        <div class="sub">
            Calendario de partidos
            @if($tournament->starts_on)
            · {{ $tournament->starts_on->translatedFormat('d M Y') }}@if($tournament->ends_on && !$tournament->ends_on->equalTo($tournament->starts_on)) – {{ $tournament->ends_on->translatedFormat('d M Y') }}@endif
            @endif
        </div>
    </div>

    @forelse($byDay as $day => $matches)
    @php $d = \Carbon\Carbon::parse($day, 'America/Mexico_City'); @endphp
    <div class="day">
        <div class="day-title">{{ $d->translatedFormat('l d \d\e F') }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width:48px;">Hora</th>
                    <th style="width:70px;">Cancha</th>
                    <th>Categoría / Fase</th>
                    <th>Partido</th>
                    <th style="width:90px;">Resultado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matches as $m)
                @php $played = $m->state->value === 'confirmed'; @endphp
                <tr>
                    <td class="time">{{ $m->starts_at->timezone('America/Mexico_City')->format('H:i') }}</td>
                    <td class="court">{{ $m->court?->name ?? '—' }}</td>
                    <td>
                        <span class="cat">{{ $m->category->name }}</span><br>
                        <span class="ctx">{{ $m->contextLabel() }}</span>
                    </td>
                    <td>
                        {{ $m->sideLabel('a') }} <span class="vs">vs</span> {{ $m->sideLabel('b') }}
                    </td>
                    <td class="score">
                        @if($played && $m->sets)
                        @foreach($m->sets as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last), @endif @endforeach
                        @else
                        —
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @empty
    <div class="empty">Aún no hay partidos programados.</div>
    @endforelse

    <div class="foot">
        Generado el {{ $generatedAt->translatedFormat('d M Y · H:i') }} · PadelCup
    </div>
</body>

</html>