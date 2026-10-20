<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1a1a2e; margin: 0; }

        .head { border-bottom: 2px solid #0e3b2e; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { font-size: 18px; margin: 0 0 4px; color: #111; }
        .head .sub { font-size: 10px; color: #666; }
        .head .filters { font-size: 10px; color: #0e3b2e; margin-top: 3px; }

        .day { margin-bottom: 16px; page-break-inside: avoid; }
        .day-title {
            background: #0e3b2e; color: #fff; padding: 5px 9px; font-size: 12px;
            font-weight: bold; border-radius: 3px; text-transform: capitalize;
        }

        table.grid { width: 100%; border-collapse: collapse; margin-top: 5px; table-layout: fixed; }
        table.grid th, table.grid td {
            border: 1px solid #d0d8ca; padding: 3px 4px; vertical-align: top;
            font-size: 9px; word-wrap: break-word;
        }
        table.grid th { background: #f1f4ee; text-align: center; font-size: 9px; color: #333; }
        table.grid td.hora, table.grid th.hora {
            width: 44px; text-align: center; font-weight: bold; background: #f7f9f5;
            white-space: nowrap;
        }
        .cell-match { margin-bottom: 3px; padding-bottom: 2px; border-bottom: 1px dotted #e4e9e0; }
        .cell-match:last-child { margin-bottom: 0; border-bottom: none; }
        .cell-match .cat { display: block; font-size: 7.5px; color: #5c6b62; text-transform: uppercase; }
        .cell-match .pairs { font-weight: bold; color: #13211c; }
        .cell-match .vs { color: #97a399; font-weight: normal; }

        .empty { color: #666; font-size: 11px; padding: 20px 0; }
        .foot { margin-top: 10px; font-size: 8px; color: #999; }
    </style>
</head>

<body>
    <div class="head">
        <h1>{{ $tournament->name }}</h1>
        <div class="sub">Calendario · vista de cuadrícula</div>
        @if(!empty($catLabels))
        <div class="filters">Categorías: {{ implode(' · ', $catLabels) }}</div>
        @endif
    </div>

    @forelse($days as $day)
    @php $dayGrid = $grid[$day] ?? []; @endphp
    <div class="day">
        <div class="day-title">{{ \Carbon\Carbon::parse($day, 'America/Mexico_City')->locale('es')->translatedFormat('l d \d\e F') }}</div>
        <table class="grid">
            <thead>
                <tr>
                    <th class="hora">Hora</th>
                    @foreach($courts as $court)
                    <th>{{ $court->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($slots as $slot)
                @php $rowCells = $dayGrid[$slot] ?? []; @endphp
                @if(!empty($rowCells))
                <tr>
                    <td class="hora">{{ $slot }}</td>
                    @foreach($courts as $court)
                    <td>
                        @foreach(($rowCells[$court->id] ?? []) as $m)
                        <div class="cell-match">
                            <span class="cat">{{ $m->category->name }}</span>
                            <span class="pairs">{{ $m->sideLabel('a') }} <span class="vs">vs</span> {{ $m->sideLabel('b') }}</span>
                        </div>
                        @endforeach
                    </td>
                    @endforeach
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @empty
    <div class="empty">No hay partidos programados para los filtros seleccionados.</div>
    @endforelse

    <div class="foot">Generado el {{ $generatedAt->locale('es')->translatedFormat('d M Y · H:i') }}</div>
</body>

</html>
