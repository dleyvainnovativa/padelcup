@extends('layouts.app')

@section('title', 'Mesa de control')

@section('content')
<x-breadcrumb :items="[
        ['label' => 'Torneos', 'url' => route('tournaments.index')],
        ['label' => $tournament->name, 'url' => route('tournaments.show', $tournament)],
        ['label' => 'Mesa de control'],
    ]" />

@php
    $tz = 'America/Mexico_City';
    $today = \Carbon\Carbon::now($tz)->format('Y-m-d');
    $dayKeys = $days->map->format('Y-m-d')->values();
    $defaultDay = $dayKeys->contains($today) ? $today : ($dayKeys->first() ?? $today);
@endphp

<div data-control-board
    x-data="{
        day: sessionStorage.getItem('mesa_day_{{ $tournament->id }}') || '{{ $defaultDay }}',
        setDay(d) { this.day = d; sessionStorage.setItem('mesa_day_{{ $tournament->id }}', d); }
    }"
    x-init="
        const valid = @json($dayKeys);
        if (!valid.includes(day)) { day = valid[0] || '{{ $defaultDay }}'; }
    ">

    <div class="page-head">
        <div>
            <h1>Mesa de control</h1>
            <div class="page-sub">{{ $tournament->name }}</div>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @if($delay !== null && $delay > 0)
                <span class="mesa-delay"><i class="fa-solid fa-hourglass-half me-1"></i> ~{{ $delay }} min de retraso</span>
            @endif
            <a href="{{ route('schedule.index', $tournament) }}" class="btn btn-soft">
                <i class="fa-solid fa-calendar-days me-1"></i><span class="btn-label">Calendario</span>
            </a>
        </div>
    </div>

    <div class="mesa-counts mb-3">
        <span class="mesa-count mesa-count--pending"><strong>{{ $counts['pending'] ?? 0 }}</strong> pendientes</span>
        <span class="mesa-count mesa-count--called"><strong>{{ $counts['called'] ?? 0 }}</strong> llamados</span>
        <span class="mesa-count mesa-count--playing"><strong>{{ $counts['playing'] ?? 0 }}</strong> en juego</span>
        <span class="mesa-count mesa-count--finished"><strong>{{ $counts['finished'] ?? 0 }}</strong> terminados</span>
    </div>

    @include('dashboard.partials.flash')

    @if($days->isNotEmpty())
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
    @endif

    @forelse($byDay as $dayKey => $dayMatches)
    <div x-show="day === '{{ $dayKey }}'" x-cloak>
        <div class="tc-card">
            <div class="tc-card__body mesa-list">
                @foreach($dayMatches as $m)
                @php
                    $cs = $m->controlStatus();
                    $delayM = $m->delayMinutes();
                @endphp
                <div class="mesa-row mesa-row--{{ $cs }}">
                    <div class="mesa-row__when">
                        <div class="mesa-row__time">{{ $m->starts_at->timezone($tz)->format('H:i') }}</div>
                        <div class="mesa-row__court">{{ $m->court?->name ?? '—' }}</div>
                        @if($delayM !== null && $delayM > 0)
                        <div class="mesa-row__delay">+{{ $delayM }}′</div>
                        @endif
                    </div>

                    <div class="mesa-row__main">
                        <div class="mesa-row__ctx">{{ $m->contextLabel() }}</div>
                        <div class="mesa-row__pairs">{{ $m->sideLabel('a') }} <span class="vs">vs</span> {{ $m->sideLabel('b') }}</div>

                        <div class="mesa-steps" data-ctrl-steps-row
                            data-url="{{ route('control.setStatus', [$tournament, $m->id]) }}"
                            data-current="{{ $cs }}">
                            @php
                                $steps = [
                                    ['pending', 'Pendiente', 'fa-regular fa-circle'],
                                    ['called', 'Llamado', 'fa-solid fa-bullhorn'],
                                    ['playing', 'En juego', 'fa-solid fa-circle-play'],
                                    ['finished', 'Terminado', 'fa-solid fa-flag-checkered'],
                                ];
                                $order = ['pending', 'called', 'playing', 'finished'];
                                $curIdx = array_search($cs, $order, true);
                            @endphp
                            @foreach($steps as $i => $step)
                            <button type="button"
                                class="mesa-step{{ $i <= $curIdx ? ' is-active' : '' }}{{ $i === $curIdx ? ' is-current' : '' }}"
                                data-ctrl-status data-status="{{ $step[0] }}">
                                <i class="{{ $step[2] }}"></i><span>{{ $step[1] }}</span>
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mesa-row__pagos">
                        <div class="mesa-pagos__label"><i class="fa-solid fa-money-bill-wave me-1"></i> Pagos</div>
                        @foreach(['a' => $m->pairA, 'b' => $m->pairB] as $pair)
                            @if($pair)
                            @php
                                $states = $pair->paidPlayerStates();
                                $payUrl = route('pairs.playerPayment', [$tournament, $m->category_id, $pair->id]);
                            @endphp
                            @foreach([$pair->player1, $pair->player2] as $pl)
                                @if($pl)
                                @php $paid = (bool) ($states[$pl->id] ?? false); @endphp
                                <label class="sched-pago mesa-pago{{ $paid ? ' is-paid' : '' }}"
                                    data-pago data-pago-pair="{{ $pair->id }}">
                                    <input type="checkbox" data-pago-cb
                                        data-url="{{ $payUrl }}" data-player="{{ $pl->id }}"
                                        @checked($paid)>
                                    <span class="sched-pago__name">{{ $pl->name }}</span>
                                    <span class="sched-pago__state" data-pago-state>{{ $paid ? 'Pagado' : 'Pendiente' }}</span>
                                </label>
                                @endif
                            @endforeach
                            @endif
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @empty
    <div class="tc-card"><div class="tc-card__body">
        <p style="color:var(--text-faint);margin:0;">No hay partidos programados todavía. Programa el calendario para usar la mesa de control.</p>
    </div></div>
    @endforelse
</div>
@endsection
