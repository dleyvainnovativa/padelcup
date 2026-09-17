{{-- One match card in the category calendar. $m = GameMatch, $showTime = bool.
     Player names link to their public page; played matches get a share button. --}}
@php
$status = $m->scheduleStatus();
$played = $m->state->value === 'confirmed';

// Build linked side labels: each real player links to their public page.
$sideLink = function ($pair) use ($tournament) {
if (! $pair) return null;
$parts = [];
foreach ([$pair->player1, $pair->player2] as $p) {
if ($p) {
$url = route('public.player', [$tournament, $p]);
$parts[] = '<a href="'.$url.'" class="pub-match__player">'.e($p->name).'</a>';
}
}
return $parts ? implode(' / ', $parts) : e($pair->name());
};
$aLink = $sideLink($m->pairA);
$bLink = $sideLink($m->pairB);

if ($played) {
$shareData = [
'tournament' => $tournament->name,
'category' => $category->name,
'context' => $m->contextLabel(),
'pairA' => $m->pairA?->name() ?? '—',
'pairB' => $m->pairB?->name() ?? '—',
'sets' => $m->sets ?? [],
'winner' => $m->winner_pair_id === $m->pair_a_id ? 'a' : ($m->winner_pair_id === $m->pair_b_id ? 'b' : null),
];
}
@endphp
<div class="pub-match pub-match--{{ $status }}">
    <div class="pub-match__time">
        @if($showTime && $m->starts_at)
        {{ $m->starts_at->timezone('America/Mexico_City')->format('H:i') }}
        @else
        <span class="pub-muted">—</span>
        @endif
        @if($m->court)<span class="pub-match__court"><i class="fa-solid fa-location-dot"></i> @if($m->court->venue){{ $m->court->venue->name }} · @endif{{ $m->court->name }}</span>@endif
    </div>
    <div class="pub-match__body">
        <div class="pub-match__ctx">
            {{ $m->contextLabel() }}
            @php
            // This partial passes a FLAT [label => name] map as $ghostQualifiers
            // (used by ghostFor). isProjected expects [category_id => map], so wrap it.
            $projected = !$played && $m->isProjected([$m->category_id => ($ghostQualifiers ?? [])]);
            @endphp
            @if($projected)
            <span class="pub-match__projected" title="Participantes por confirmar según resultados previos">Por confirmar</span>
            @endif
        </div>
        <div class="pub-match__pairs">
            <span class="{{ $m->winner_pair_id === $m->pair_a_id && $m->pair_a_id ? 'is-win' : '' }}">
                {!! $aLink ?? $m->sideLabel('a') !!}
                @php $ghostA = $m->ghostFor('a', $ghostQualifiers ?? []); @endphp
                @if($ghostA)<span class="pub-match__ghost" title="Clasificado (grupo terminado)">{{ $ghostA }}</span>@endif
            </span>
            @if($status === 'played' && $m->sets)
            <span class="pub-match__sc pub-mono">
                @foreach($m->sets as $s){{ $s[0] }}-{{ $s[1] }}@if(!$loop->last) @endif @endforeach
            </span>
            @else
            <span class="pub-match__vs">vs</span>
            @endif
            <span class="{{ $m->winner_pair_id === $m->pair_b_id && $m->pair_b_id ? 'is-win' : '' }}">
                {!! $bLink ?? $m->sideLabel('b') !!}
                @php $ghostB = $m->ghostFor('b', $ghostQualifiers ?? []); @endphp
                @if($ghostB)<span class="pub-match__ghost" title="Clasificado (grupo terminado)">{{ $ghostB }}</span>@endif
            </span>
        </div>
    </div>
    <div class="pub-match__actions">

        @if($played)
        <button type="button" class="pub-share-btn pub-match__share" data-share-match='@json($shareData)' title="Compartir imagen">
            <i class="fa-solid fa-image"></i>
        </button>
        @endif

        {{-- Gamification (propose/predict) only while the match has NO official
         result. Once confirmed, only share + the confirmed check remain. --}}
        @unless($played)
        @php $canPropose = auth()->check() && $m->canBeProposedBy(auth()->user()); @endphp
        @if($m->pendingProposal()->exists())
        <span class="pub-match__proposed" title="Resultado propuesto, esperando confirmación del organizador">
            <i class="fa-solid fa-hourglass-half"></i>
        </span>
        @endif
        @if($canPropose)
        <button type="button" class="pub-share-btn pub-match__propose"
            title="Proponer resultado"
            data-propose-match="{{ $m->id }}"
            data-propose-url="{{ route('public.match.propose', $m) }}"
            data-propose-a="{{ $m->pairA?->name() ?? 'A' }}"
            data-propose-b="{{ $m->pairB?->name() ?? 'B' }}"
            data-propose-ctx="{{ $m->contextLabel() }}">
            <i class="fa-solid fa-pen-to-square"></i>
        </button>
        @endif

        {{-- Prediction game: any logged-in user can guess the exact score until lock --}}
        @auth
        @php
        $predOpen = app(\App\Services\Tournament\PredictionService::class)->isOpen($m);
        $myPred = $m->relationLoaded('myPrediction') ? $m->myPrediction : \App\Models\MatchPrediction::where('game_match_id', $m->id)->where('user_id', auth()->id())->first();
        @endphp
        @if($predOpen || $myPred)
        <button type="button"
            class="pub-share-btn pub-match__predict {{ $myPred ? 'has-pred' : '' }}"
            title="{{ $predOpen ? 'Predecir el marcador' : 'Predicciones cerradas' }}"
            @if($predOpen)
            data-predict-match="{{ $m->id }}"
            data-predict-url="{{ route('public.match.predict', $m) }}"
            data-predict-a="{{ $m->pairA?->name() ?? 'A' }}"
            data-predict-b="{{ $m->pairB?->name() ?? 'B' }}"
            data-predict-ctx="{{ $m->contextLabel() }}"
            data-predict-current="{{ $myPred ? json_encode($myPred->sets) : '' }}"
            @else disabled @endif>
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </button>
        @endif
        @endauth
        @endunless
    </div>
</div>