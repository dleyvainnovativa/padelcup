<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1a1a2e; margin: 0; }

        .head { border-bottom: 2px solid #635bff; padding-bottom: 8px; margin-bottom: 14px; }
        .head h1 { font-size: 18px; margin: 0 0 3px; color: #111; }
        .head .sub { font-size: 10px; color: #666; }

        .block { margin-bottom: 16px; page-break-inside: avoid; }
        .block-title {
            background: #635bff; color: #fff; font-size: 11px; font-weight: bold;
            padding: 5px 9px; border-radius: 5px 5px 0 0;
        }
        .block-title .cat { opacity: .85; font-weight: normal; }

        table.cruces { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.cruces th, table.cruces td {
            border: 1px solid #e3e5ec; padding: 4px 5px; vertical-align: middle;
        }
        table.cruces thead th {
            background: #f4f5f8; color: #555; font-size: 9px; font-weight: bold; text-align: center;
        }
        .col-num { width: 18px; text-align: center; color: #888; }
        .col-pair { width: 130px; }
        .col-op { text-align: center; width: 52px; }
        .col-cruces { width: 90px; }
        .col-horario { width: 150px; }

        td.pair-name { font-weight: bold; font-size: 9px; color: #222; }
        td.cell { text-align: center; font-size: 9px; color: #635bff; }
        td.cell.score { color: #111; font-weight: bold; }
        td.diag { background-image: repeating-linear-gradient(45deg, #f0f0f4, #f0f0f4 3px, #fff 3px, #fff 6px); }

        .pill {
            display: inline-block; background: #eef0ff; color: #4b45cc;
            border-radius: 8px; padding: 1px 6px; font-size: 8px; font-weight: bold;
            margin: 1px;
        }
        .horario { font-size: 8px; color: #444; line-height: 1.5; }

        .foot { margin-top: 10px; font-size: 8px; color: #999; text-align: right; }
        .empty { color: #999; font-size: 10px; padding: 8px; }
    </style>
</head>

<body>
    <div class="head">
        <h1>{{ $tournament->name }}</h1>
        <div class="sub">Cruces por grupo · Fase de grupos · Generado {{ $generatedAt->locale('es')->isoFormat('DD MMM YYYY, HH:mm') }}</div>
    </div>

    @forelse($blocks as $b)
        @php $n = $b['pairs']->count(); @endphp
        <div class="block">
            <div class="block-title">
                Grupo {{ $b['group'] }} <span class="cat">· {{ $b['category'] }}</span>
            </div>
            <table class="cruces">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-pair" style="text-align:left;">Parejas</th>
                        @for($j = 0; $j < $n; $j++)
                            <th class="col-op">{{ $j + 1 }}</th>
                        @endfor
                        <th class="col-cruces">Cruces</th>
                        <th class="col-horario">Horario</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < $n; $i++)
                    <tr>
                        <td class="col-num">{{ $i + 1 }}</td>
                        <td class="pair-name">{{ $b['pairs'][$i]->name() }}</td>
                        @for($j = 0; $j < $n; $j++)
                            @if($i === $j)
                                <td class="diag"></td>
                            @else
                                @php $val = $b['grid'][$i][$j]; @endphp
                                <td class="cell {{ $val && preg_match('/^\d+-\d+$/', $val) ? 'score' : '' }}">{{ $val }}</td>
                            @endif
                        @endfor
                        <td>
                            @foreach($b['pills'][$i] as $pill)<span class="pill">{{ $pill }}</span>@endforeach
                        </td>
                        <td class="horario">
                            @foreach($b['horario'][$i] as $h){{ $h }}@if(!$loop->last)<br>@endif @endforeach
                        </td>
                    </tr>
                    @endfor
                </tbody>
            </table>
        </div>
    @empty
        <div class="empty">No hay partidos de fase de grupos para mostrar.</div>
    @endforelse

    <div class="foot">PadelCup · {{ $tournament->name }}</div>
</body>

</html>
