<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #1a1a2e; margin: 0; }

        .head { border-bottom: 2px solid #635bff; padding-bottom: 10px; margin-bottom: 16px; }
        .head h1 { font-size: 20px; margin: 0 0 4px; color: #111; }
        .head .sub { font-size: 11px; color: #666; }

        .cat { margin-bottom: 20px; page-break-inside: avoid; }
        .cat-title {
            background: #635bff; color: #fff; padding: 6px 10px;
            font-size: 13px; font-weight: bold; border-radius: 4px;
        }

        .round { margin-top: 10px; }
        .round-name {
            font-size: 10px; text-transform: uppercase; letter-spacing: .04em;
            color: #635bff; font-weight: bold; margin: 0 0 4px; padding-left: 2px;
        }

        table { width: 100%; border-collapse: collapse; }
        .bm { margin-bottom: 5px; }
        .bm-when {
            font-size: 9px; color: #666; font-weight: bold;
            margin: -2px 0 7px 2px; text-transform: capitalize;
        }
        .bm td {
            border: 1px solid #e2e2ea; padding: 5px 8px; vertical-align: top;
            width: 44%;
        }
        .bm td.vs {
            width: 12%; text-align: center; border: none; color: #aaa;
            font-size: 9px; font-weight: bold; vertical-align: middle;
        }
        .side-name { font-weight: bold; font-size: 11px; color: #1a1a2e; }        .side-label { color: #666; font-size: 10px; }
        .side-ghost {
            display: block; color: #635bff; font-weight: bold; font-size: 10px;
            margin-top: 1px;
        }
        .side-win { color: #1a7f37; }
        .sc { float: right; color: #635bff; font-weight: bold; font-size: 10px; }

        .pending { color: #999; font-style: italic; font-size: 10px; }
        .foot { margin-top: 8px; font-size: 9px; color: #999; }
        .legend { margin-top: 4px; font-size: 9px; color: #888; }
        .legend b { color: #635bff; }
    </style>
</head>

<body>
    <div class="head">
        <h1>{{ $tournament->name }}</h1>
        <div class="sub">
            Fase de eliminación · Generado {{ $generatedAt->translatedFormat('d M Y, H:i') }}
        </div>
    </div>

    @forelse($categories as $entry)
        @php $category = $entry['category']; $rounds = $entry['rounds']; $ghost = $entry['ghost']; @endphp
        <div class="cat">
            <div class="cat-title">{{ $category->name }}</div>

            @foreach($rounds as $round => $matches)
            <div class="round">
                <div class="round-name">{{ $matches->first()->bracketRoundName() }}</div>
                @foreach($matches as $m)
                @php
                    $played = $m->state->value === 'confirmed';
                    $ghostA = $m->ghostFor('a', $ghost);
                    $ghostB = $m->ghostFor('b', $ghost);
                    $hasPairA = (bool) $m->pairA;
                    $hasPairB = (bool) $m->pairB;
                @endphp
                <table class="bm">
                    <tr>
                        <td>
                            @if($hasPairA)
                                <span class="side-name {{ $m->winner_pair_id === $m->pair_a_id ? 'side-win' : '' }}">{{ $m->sideLabel('a') }}</span>
                            @else
                                <span class="side-label">{{ $m->sideLabel('a') }}</span>
                                @if($ghostA)<span class="side-ghost">{{ $ghostA }}</span>@endif
                            @endif
                            @if($played && $m->sets)<span class="sc">{{ collect($m->sets)->map(fn($s) => $s[0])->implode(' ') }}</span>@endif
                        </td>
                        <td class="vs">VS</td>
                        <td>
                            @if($hasPairB)
                                <span class="side-name {{ $m->winner_pair_id === $m->pair_b_id ? 'side-win' : '' }}">{{ $m->sideLabel('b') }}</span>
                            @else
                                <span class="side-label">{{ $m->sideLabel('b') }}</span>
                                @if($ghostB)<span class="side-ghost">{{ $ghostB }}</span>@endif
                            @endif
                            @if($played && $m->sets)<span class="sc">{{ collect($m->sets)->map(fn($s) => $s[1])->implode(' ') }}</span>@endif
                        </td>
                    </tr>
                </table>
                @if($m->starts_at)
                <div class="bm-when">{{ $m->starts_at->timezone('America/Mexico_City')->translatedFormat('D d M · H:i') }}</div>
                @endif
                @endforeach
            </div>
            @endforeach

            <div class="legend">
                <b>Negrita azul</b> = pareja ya clasificada de un grupo terminado ·
                texto gris = aún por definir (grupo en curso)
            </div>
        </div>
    @empty
        <p class="pending">No hay categorías con fase de eliminación todavía.</p>
    @endforelse

    <div class="foot">
        PadelCup · {{ $tournament->name }} · Fase de eliminación
    </div>
</body>

</html>