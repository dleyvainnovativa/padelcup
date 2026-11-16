@php
    /** @var array $layoutData  from BracketPdfComposer::compose() */
    $W = $layoutData['width']; $H = $layoutData['height'];
    $BW = $layoutData['boxW']; $RH = $layoutData['rowH'];
    $sideH = $RH / 2;

    // Resolve a side's display name + winner flag from the match.
    $nameFor = function ($m, string $side) use ($ghost) {
        $pair = $side === 'a' ? $m->pairA : $m->pairB;
        if ($pair) return $pair->name();
        $label = $side === 'a' ? $m->seed_label_a : $m->seed_label_b;
        if ($label === 'BYE') return 'BYE';
        if ($label && isset($ghost[$label])) return $ghost[$label];
        return $label ?: '';
    };
    $isWin = fn($m, $side) => $m->winner_pair_id && $m->winner_pair_id === ($side === 'a' ? $m->pair_a_id : $m->pair_b_id);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; }
    .sheet { position: relative; width: {{ $W }}pt; height: {{ $H }}pt; }

    .pdf-head { position: absolute; left: 24pt; top: 18pt; right: 24pt; height: 48pt; }
    .pdf-head img { height: 30pt; vertical-align: middle; }
    .pdf-head .t { display: inline-block; vertical-align: middle; margin-left: 12pt; }
    .pdf-head .cat { font-size: 15pt; font-weight: bold; color: #0e3b2e; line-height: 1.1; }
    .pdf-head .url { font-size: 8pt; color: #7a857f; }
    .pdf-head .tn { position: absolute; right: 24pt; top: 4pt; font-size: 9pt; color: #7a857f; text-align: right; }

    .ln { position: absolute; background: #9aa8a2; }

    .rl {
        position: absolute; width: {{ $BW }}pt; text-align: center;
        font-size: 8pt; font-weight: bold; color: #0e3b2e;
        text-transform: uppercase; letter-spacing: .04em;
    }

    .bx { position: absolute; width: {{ $BW }}pt; }
    .bx .s {
        height: {{ $sideH }}pt; box-sizing: border-box; border: 0.6pt solid #c9d2ce;
        background: #f6f8f7; padding: 2pt 5pt; overflow: hidden;
        font-size: 7.5pt; color: #1a1a2e; white-space: nowrap;
    }
    .bx .s.b { border-top: none; }
    .bx .s.win { background: #e8f3ec; color: #1a7f37; font-weight: bold; }
    .bx .s.tbd { color: #b4bbb7; font-style: italic; }
</style>
</head>
<body>
<div class="sheet">
    <div class="pdf-head">
        <img src="{{ public_path('img/voleo-logo.png') }}" alt="Voleo">
        <span class="t">
            <span class="cat">{{ $category->name }}</span><br>
            <span class="url">{{ $categoryUrl }}</span>
        </span>
        <span class="tn">{{ $tournament->name }}<br>{{ $generatedAt->translatedFormat('d M Y, H:i') }}</span>
    </div>

    @foreach($layoutData['roundLabels'] as $rl)
        <div class="rl" style="left: {{ $rl['x'] }}pt; top: {{ $layoutData['labelY'] }}pt;">{{ $rl['label'] }}</div>
    @endforeach

    @foreach($layoutData['lines'] as $l)
        <div class="ln" style="left: {{ $l['x'] }}pt; top: {{ $l['y'] }}pt; width: {{ $l['w'] }}pt; height: {{ $l['h'] }}pt;"></div>
    @endforeach

    @foreach($matches as $m)
        @php $b = $layoutData['boxes'][$m->id] ?? null; @endphp
        @if($b)
            <div class="bx" style="left: {{ $b['x'] }}pt; top: {{ $b['y'] }}pt;">
                @foreach(['a', 'b'] as $i => $side)
                    @php $nm = $nameFor($m, $side); @endphp
                    <div class="s {{ $side === 'b' ? 'b' : '' }} {{ $isWin($m, $side) ? 'win' : ($nm ? '' : 'tbd') }}">{{ $nm !== '' ? $nm : '—' }}</div>
                @endforeach
            </div>
        @endif
    @endforeach
</div>
</body>
</html>
