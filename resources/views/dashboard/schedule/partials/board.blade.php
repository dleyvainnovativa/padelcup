{{-- Calendar board partial — the drag/drop grid + unscheduled tray.
     Rendered inline by index.blade.php AND standalone by ScheduleController::board
     (the live, in-place refresh after a save). Keep every variable it uses in
     ScheduleController::boardViewData(). --}}
<script type="application/json" id="player-match-index">
    @json($playerMatchIndex ?? [])
</script>

@if($courts->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Primero <a href="{{ route('venues.index', $tournament) }}">agrega canchas</a> para programar.
    </div>
</div>
@elseif(empty($slots))
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-muted);">
        Configura la hora de inicio, fin y duración de partido en
        <a href="{{ route('tournaments.edit', $tournament) }}">los ajustes del torneo</a>.
    </div>
</div>
@else
@php
// Per-day slot map: each day has its own slots + step (a longer-duration day
// draws its own rows). [ 'Y-m-d' => ['slots'=>[...], 'step'=>min] ].
$daySlots = $tournament->daySlotMap();

// Bucket each scheduled match into the SLOT (on ITS day) whose window contains
// its start time (so a 12:00 match shows in the 11:45 slot, not vanishing
// because 12:00 isn't an exact slot label). Uses that day's step.
$byCell = [];
foreach ($scheduled as $m) {
$local = $m->starts_at->timezone('America/Mexico_City');
$dayKey = $local->format('Y-m-d');
$startMin = $local->hour * 60 + $local->minute;

$daySlotList = $daySlots[$dayKey]['slots'] ?? ($slots ?? []);
$dayStep = $daySlots[$dayKey]['step'] ?? ($tournament->match_duration_minutes ?: 75);

// Build this day's slot-minute map.
$dayMins = [];
foreach ($daySlotList as $s) {
[$h, $mi] = explode(':', $s);
$dayMins[$s] = ((int) $h) * 60 + (int) $mi;
}

// Find the slot this start time falls into (on its day).
$matchedSlot = null;
foreach ($dayMins as $label => $min) {
if ($startMin >= $min && $startMin < $min + $dayStep) {
    $matchedSlot=$label;
    break;
    }
    }
    // Off-grid (before first / after last slot): clamp to nearest.
    if ($matchedSlot===null && ! empty($dayMins)) {
    $matchedSlot=$startMin < reset($dayMins)
    ? array_key_first($dayMins)
    : array_key_last($dayMins);
    }

    if ($matchedSlot !==null) {
    $key=$m->court_id.'|'.$dayKey.'|'.$matchedSlot;
    $byCell[$key][] = $m; // a cell can hold more than one match
    }
    }
    // Lookup for the pagos subtitle (#9): normalized player name → every category
    // they're registered in. $multiCategoryPlayers already holds only players in
    // 2+ categories, so a lookup miss means the player is in exactly one.
    $playerCategoryMap = [];
    foreach ($multiCategoryPlayers as $__row) {
        $playerCategoryMap[\App\Models\Player::normalize($__row['name'])] = $__row['categories'];
    }

    $boardData = [
    'placeUrl' => route('schedule.place', $tournament),
    'unplaceUrl' => route('schedule.unplace', $tournament),
    'unplaceManyUrl' => route('schedule.unplaceMany', $tournament),
    'switchCourtUrl' => route('schedule.switchCourt', $tournament),
    'swapUrl' => route('schedule.swap', $tournament),
    'setTimeUrl' => route('schedule.setTime', $tournament),
    'boardUrl' => route('schedule.board', $tournament),
    'duration' => $tournament->match_duration_minutes,
    'courts' => $courts->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
    'scheduled' => $scheduled->mapWithKeys(fn ($m) => [$m->id => [
    'id' => $m->id,
    'context' => $m->contextLabel(),
    'a' => $m->sideLabel('a'),
    'b' => $m->sideLabel('b'),
    'ready' => $m->isReadyForResult(),
    'status' => $m->scheduleStatus(),
    'time' => $m->starts_at->timezone('America/Mexico_City')->format('H:i'),
    'sets' => $m->sets ?? [],
    'confirmUrl' => route('results.confirm', [$tournament, $m->category_id, $m->id]),
    'editUrl' => route('results.edit', [$tournament, $m->category_id, $m->id]),
    'clearUrl' => route('results.clear', [$tournament, $m->category_id, $m->id]),
    'courtName' => $courts->firstWhere('id', $m->court_id)?->name,
    'proposal' => ($p = $m->pendingProposal) ? [
    'by' => $p->proposer?->name ?? 'Un jugador',
    'score' => collect($p->sets)->map(fn ($s) => ($s[0] ?? 0).'-'.($s[1] ?? 0))->implode(', '),
    'sets' => $p->sets,
    'acceptUrl' => route('results.proposal.accept', [$tournament, $m->category_id, $m->id]),
    'rejectUrl' => route('results.proposal.reject', [$tournament, $m->category_id, $m->id]),
    ] : null,
    // Mesa de control: live status + per-player payments on the scheduled sheet.
    'controlStatus' => $m->controlStatus(),
    'controlStatusLabel' => $m->controlStatusLabel(),
    'statusUrl' => route('control.setStatus', [$tournament, $m->id]),
    'players' => collect(['a' => $m->pairA, 'b' => $m->pairB])
    ->flatMap(function ($pair) use ($tournament, $m, $playerCategoryMap) {
    if (! $pair) return [];
    $states = $pair->paidPlayerStates();
    $payUrl = route('pairs.playerPayment', [$tournament, $m->category_id, $pair->id]);
    $currentCat = $m->category?->name;
    $out = [];
    foreach ([$pair->player1, $pair->player2] as $pl) {
    if (! $pl) continue;
    // Other categories this player is in (empty unless they're in 2+).
    $allCats = $playerCategoryMap[\App\Models\Player::normalize($pl->name)] ?? [];
    $otherCats = array_values(array_filter($allCats, fn ($c) => $c !== $currentCat));
    $out[] = [
    'pair_id' => $pair->id,
    'player_id' => $pl->id,
    'name' => $pl->name,
    'paid' => (bool) ($states[$pl->id] ?? false),
    'payUrl' => $payUrl,
    'otherCategories' => $otherCats,
    ];
    }
    return $out;
    })->values()->all(),
    ]])->all(),
    'unscheduled' => $unscheduled->map(fn ($m) => [
    'id' => $m->id,
    'category' => $m->category->name,
    'context' => $m->contextLabel(),
    'a' => $m->sideLabel('a'),
    'b' => $m->sideLabel('b'),
    'players' => $m->playerIds(),
    ])->values()->all(),
    ];
    @endphp

    <div class="sched" data-sched-board
        data-sched-config='@json($boardData)'
        x-data="{
                day: sessionStorage.getItem('sched_day_{{ $tournament->id }}') || '{{ $days->first()->format('Y-m-d') }}',
                setDay(d) { this.day = d; sessionStorage.setItem('sched_day_{{ $tournament->id }}', d); }
             }"
        x-init="
                // Validate stored day is within this tournament's days.
                const valid = @json($days->map->format('Y-m-d')->values());
                if (!valid.includes(day)) { day = valid[0]; }
             ">
        <div class="sched-days mb-3">
            @foreach($days as $day)
            <button type="button" class="sched-day"
                data-day="{{ $day->format('Y-m-d') }}"
                :class="{ 'is-active': day === '{{ $day->format('Y-m-d') }}' }"
                @click="setDay('{{ $day->format('Y-m-d') }}')">
                {{ $day->locale('es')->isoFormat('ddd D MMM') }}
            </button>
            @endforeach
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-3">
                <div class="tc-card" x-data="{ trayOpen: false, trayCategory: '' }">
                    <div class="tc-card__head" style="cursor:pointer;display:flex;align-items:center;justify-content:space-between;"
                        @click="trayOpen = !trayOpen">
                        <h3 style="margin:0;">
                            Sin programar
                            <span class="sched-tray__count">{{ $unscheduled->count() }}</span>
                        </h3>
                        <i class="fa-solid" :class="trayOpen ? 'fa-chevron-up' : 'fa-chevron-down'" style="color:var(--text-faint);font-size:12px;"></i>
                    </div>
                    <div class="tc-card__body" id="sched-tray" x-show="trayOpen" x-cloak
                        style="display:flex;flex-direction:column;gap:6px;min-height:60px;">
                        @php
                        $trayCategories = $unscheduled->map(fn($m) => $m->category->name)->unique()->sort()->values();
                        @endphp
                        @if($trayCategories->count() > 1)
                        <select class="form-select form-select-sm sched-tray__filter" x-model="trayCategory"
                            style="margin-bottom:4px;border-radius:var(--radius);">
                            <option value="">Todas las categorías</option>
                            @foreach($trayCategories as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                        @endif
                        @forelse($unscheduled as $m)
                        <div class="sched-chip" draggable="true"
                            data-match-id="{{ $m->id }}"
                            data-category="{{ $m->category->name }}"
                            x-show="!trayCategory || trayCategory === @js($m->category->name)"
                            data-title="{{ $m->sideLabel('a') }} vs {{ $m->sideLabel('b') }}">
                            <span class="sched-chip__context">
                                {{ $m->contextLabel() }}
                                @php $fmt = $m->groupFormatBadge(); @endphp
                                @if($fmt)<span class="fmt-badge fmt-badge--{{ strtolower($fmt) }}" title="{{ $fmt === 'MEX' ? 'Mexicano (grupo de 4)' : 'Todos contra todos (grupo de '.$m->groupSize().')' }}">{{ $fmt }}</span>@endif
                            </span>
                            <div>
                                {{ $m->sideLabel('a') }}
                                @php $ghostA = $m->ghostForIn('a', $ghostQualifiers ?? []); @endphp
                                @if($ghostA)<span class="sched-match__ghost">{{ $ghostA }}</span>@endif
                                <span style="color:var(--text-faint);">vs</span>
                                {{ $m->sideLabel('b') }}
                                @php $ghostB = $m->ghostForIn('b', $ghostQualifiers ?? []); @endphp
                                @if($ghostB)<span class="sched-match__ghost">{{ $ghostB }}</span>@endif
                            </div>
                        </div>
                        @empty
                        <div style="font-size:12px;color:var(--text-faint);">Todo está programado.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-9">
                <div class="tc-card">
                    <div class="sched-grid-wrap">
                        <table class="sched-grid">
                            <thead>
                                @php $multiVenue = $courts->pluck('venue')->filter()->unique('id')->count() > 1; @endphp
                                <tr>
                                    <th class="sched-grid__timecol">Hora</th>
                                    @foreach($courts as $court)
                                    <th>
                                        @if($multiVenue && $court->venue)<span class="sched-grid__venue">{{ $court->venue->name }}</span>@endif
                                        {{ $court->name }}
                                    </th>
                                    @endforeach
                                </tr>
                            </thead>
                            @foreach($days as $day)
                            @php
                            $dk = $day->format('Y-m-d');
                            $daySlotList = $daySlots[$dk]['slots'] ?? [];
                            @endphp
                            <tbody data-day-body="{{ $dk }}" x-show="day === '{{ $dk }}'">
                                @foreach($daySlotList as $slot)
                                <tr>
                                    <td class="sched-grid__timecol font-mono">{{ $slot }}</td>
                                    @foreach($courts as $court)
                                    <td class="sched-cell" data-cell
                                        data-court="{{ $court->id }}"
                                        data-slot="{{ $slot }}"
                                        data-date="{{ $dk }}">
                                        @php $key = $court->id.'|'.$dk.'|'.$slot; $cellMatches = $byCell[$key] ?? []; @endphp
                                        @forelse($cellMatches as $m)
                                        @php
                                        $status = $m->scheduleStatus();
                                        $mLocal = $m->starts_at->timezone('America/Mexico_City');
                                        $offGrid = $mLocal->format('H:i') !== $slot;
                                        @endphp
                                        <div class="sched-match is-{{ $status }} {{ $offGrid ? 'is-offgrid' : '' }}"
                                            data-match-id="{{ $m->id }}"
                                            data-ready="{{ $m->isReadyForResult() ? '1' : '0' }}"
                                            data-status="{{ $status }}"
                                            data-match-cat="{{ $m->category_id }}"
                                            data-match-phase="{{ $m->publicPhase()['key'] }}"
                                            data-match-players="{{ $m->searchableNames($ghostQualifiers ?? []) }}"
                                            data-match-projected="{{ $m->isProjected($ghostQualifiers ?? []) ? '1' : '0' }}"
                                            draggable="true">
                                            <div class="sched-match__context">
                                                {{ $m->contextLabel() }}
                                                @php $fmt = $m->groupFormatBadge(); @endphp
                                                @if($fmt)<span class="fmt-badge fmt-badge--{{ strtolower($fmt) }}" title="{{ $fmt === 'MEX' ? 'Mexicano (grupo de 4)' : 'Todos contra todos (grupo de '.$m->groupSize().')' }}">{{ $fmt }}</span>@endif
                                                @if($offGrid)<span class="sched-match__time" title="Horario fuera de la cuadrícula">{{ $mLocal->format('H:i') }}</span>@endif
                                            </div>
                                            <div style="font-weight:600;">
                                                {{ $m->sideLabel('a') }}
                                                @php $ghostA = $m->ghostForIn('a', $ghostQualifiers ?? []); @endphp
                                                @if($ghostA)<span class="sched-match__ghost">{{ $ghostA }}</span>@endif
                                            </div>
                                            <div style="font-size:10px;color:var(--text-faint);">vs</div>
                                            <div style="font-weight:600;">
                                                {{ $m->sideLabel('b') }}
                                                @php $ghostB = $m->ghostForIn('b', $ghostQualifiers ?? []); @endphp
                                                @if($ghostB)<span class="sched-match__ghost">{{ $ghostB }}</span>@endif
                                            </div>
                                            @if($status === 'played' && $m->sets)
                                            <div class="sched-match__scores">
                                                @foreach($m->sets as $i => $s)
                                                <span class="sched-score-badge">{{ $s[0] }}-{{ $s[1] }}</span>
                                                @endforeach
                                            </div>
                                            @endif
                                            <button type="button" class="sched-match__remove" data-unplace="{{ $m->id }}" title="Quitar">&times;</button>
                                        </div>
                                        @empty
                                        <span class="sched-cell__add"><i class="fa-solid fa-plus"></i></span>
                                        @endforelse
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                            @endforeach
                        </table>
                    </div>
                </div>
                <div style="font-size:12px;color:var(--text-faint);margin-top:8px;">
                    <span class="d-none d-md-inline">Arrastra un partido a una celda libre.</span>
                    <span class="d-md-none">Toca un partido y luego una celda libre.</span>
                </div>
            </div>
        </div>
    </div>
    @endif
