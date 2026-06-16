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

        .cat {
            margin-bottom: 18px;
        }

        .cat-title {
            background: #635bff;
            color: #fff;
            padding: 6px 10px;
            font-size: 13px;
            font-weight: bold;
            border-radius: 4px;
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

        .when {
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
            Calendario por categoría
            @if($tournament->starts_on)
            · {{ $tournament->starts_on->translatedFormat('d M Y') }}@if($tournament->ends_on && !$tournament->ends_on->equalTo($tournament->starts_on)) – {{ $tournament->ends_on->translatedFormat('d M Y') }}@endif
            @endif
        </div>
    </div>

    @forelse($byCategory as $categoryName => $matches)
    <div class="cat">
        <div class="cat-title">{{ $categoryName }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width:90px;">Día / Hora</th>
                    <th style="width:60px;">Cancha</th>
                    <th style="width:70px;">Fase</th>
                    <th>Partido</th>
                    <th style="width:90px;">Resultado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($matches as $m)
                @php $played = $m->state->value === 'confirmed'; @endphp
                <tr>
                    <td class="when">{{ $m->starts_at->timezone('America/Mexico_City')->translatedFormat('D d M · H:i') }}</td>
                    <td class="court">{{ $m->court?->name ?? '—' }}</td>
                    <td class="ctx">{{ $m->contextLabel() }}</td>
                    <td>{{ $m->sideLabel('a') }} <span class="vs">vs</span> {{ $m->sideLabel('b') }}</td>
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