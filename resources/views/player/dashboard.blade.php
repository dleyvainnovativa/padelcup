@extends('layouts.app')

@section('title', 'Mi perfil')

@section('content')
<div class="page-head">
    <div>
        <h1>Mi perfil</h1>
        <div class="page-sub">Tus torneos, próximos partidos y resultados.</div>
    </div>
    @if($hasProfile)
    <a href="{{ route('player.claim.create') }}" class="btn btn-soft">Reclamar otro perfil</a>
    @endif
</div>

@include('dashboard.partials.flash')

@unless($hasProfile)
{{-- Empty state: no linked players yet --}}
<div class="tc-card">
    <div class="tc-card__body" style="text-align:center;padding:44px 24px;">
        <div style="font-size:38px;margin-bottom:10px;">🎾</div>
        <h2 style="font-size:18px;font-weight:700;margin:0 0 6px;">Aún no tienes un perfil vinculado</h2>
        <p style="color:var(--text-muted);font-size:14px;max-width:420px;margin:0 auto 18px;">
            Si ya jugaste un torneo, reclama tu perfil para ver aquí tus partidos, resultados y torneos.
        </p>
        <a href="{{ route('player.claim.create') }}" class="btn btn-accent">Reclamar mi perfil</a>
        <div style="margin-top:12px;">
            <a href="{{ route('player.claims') }}" style="font-size:13px;">Ver mis solicitudes</a>
        </div>
    </div>
</div>
@else

{{-- Próximos partidos --}}
<h2 class="pub-section-title" style="margin-bottom:12px;">Próximos partidos</h2>
@if($upcoming->isEmpty())
<div class="tc-card" style="margin-bottom:24px;">
    <div class="tc-card__body" style="color:var(--text-faint);text-align:center;padding:24px;">
        No tienes partidos programados por ahora.
    </div>
</div>
@else
<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
    @foreach($upcoming as $m)
    <div class="tc-card">
        <div class="tc-card__body" style="display:flex;gap:14px;align-items:center;">
            <div style="min-width:74px;text-align:center;">
                @if($m['starts_at'])
                <div style="font-weight:700;font-size:14px;">{{ $m['starts_at']->translatedFormat('H:i') }}</div>
                <div style="font-size:11px;color:var(--text-faint);">{{ $m['starts_at']->translatedFormat('d M') }}</div>
                @else
                <div style="font-size:11px;color:var(--text-faint);">Sin horario</div>
                @endif
                @if($m['court'])<div style="font-size:10px;color:var(--text-faint);margin-top:2px;"><i class="fa-solid fa-location-dot"></i> {{ $m['court'] }}</div>@endif
            </div>
            <div style="flex:1;">
                <div style="font-size:10px;color:var(--accent-text);font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px;">
                    {{ $m['tournament'] }}@if($m['category']) · {{ $m['category'] }}@endif
                </div>
                <div style="font-size:13.5px;">
                    <span style="font-weight:600;">{{ $m['mine'] }}</span>
                    <span style="color:var(--text-faint);font-size:11px;"> vs </span>
                    {{ $m['opponent'] ?? 'Por definir' }}
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@if(($standings ?? collect())->isNotEmpty())
<h2 class="pub-section-title" style="margin-bottom:12px;">Mi posición</h2>
<div class="pub-grid" style="margin-bottom:24px;">
    @foreach($standings as $s)
    <div class="tc-card">
        <div class="tc-card__body">
            <div style="display:flex;align-items:baseline;gap:8px;">
                <span style="font-size:26px;font-weight:800;color:var(--accent-text);line-height:1;">{{ $s['position'] }}º</span>
                <span style="font-size:12px;color:var(--text-faint);">de {{ $s['of'] }}</span>
            </div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-top:6px;">
                {{ $s['group'] }} · {{ $s['category'] }}
            </div>
            <div style="font-size:11px;color:var(--text-faint);">
                {{ $s['tournament'] }}
                @if(!is_null($s['points']))
                · {{ $s['points'] }} pts
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Mis torneos --}}
<h2 class="pub-section-title" style="margin-bottom:12px;">Mis torneos</h2>
<div class="pub-grid" style="margin-bottom:24px;">
    @foreach($tournaments as $t)
    <a href="{{ $t['slug'] ? route('public.tournament', $t['slug']) : '#' }}" class="pub-cat-card">
        <div class="pub-cat-card__name">{{ $t['tournament'] }}</div>
        <div class="pub-cat-card__meta">
            <span>{{ collect($t['categories'])->pluck('name')->filter()->implode(', ') }}</span>
            <span class="pub-cat-card__fmt">{{ $t['played'] }}/{{ $t['total_matches'] }} partidos jugados</span>
        </div>
    </a>
    @endforeach
</div>

{{-- Historial --}}
<h2 class="pub-section-title" style="margin-bottom:12px;">Historial</h2>
@if($results->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="color:var(--text-faint);text-align:center;padding:24px;">
        Aún no hay resultados registrados.
    </div>
</div>
@else
<div style="display:flex;flex-direction:column;gap:8px;">
    @foreach($results as $m)
    <div class="tc-card" style="border-left:3px solid {{ $m['won'] ? 'var(--success)' : 'var(--border-strong)' }};">
        <div class="tc-card__body" style="display:flex;gap:14px;align-items:center;">
            <div style="min-width:52px;text-align:center;">
                @if($m['won'])
                <span class="pub-chip pp-chip--accent" style="font-size:10px;">Ganado</span>
                @else
                <span style="font-size:11px;color:var(--text-faint);font-weight:600;">Perdido</span>
                @endif
            </div>
            <div style="flex:1;">
                <div style="font-size:10px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px;">
                    {{ $m['tournament'] }}@if($m['category']) · {{ $m['category'] }}@endif
                </div>
                <div style="font-size:13.5px;">
                    <span style="font-weight:{{ $m['won'] ? '700' : '400' }};">{{ $m['mine'] }}</span>
                    @if($m['score'])<span class="pub-mono" style="color:var(--text-muted);margin:0 6px;">{{ $m['score'] }}</span>@else <span style="color:var(--text-faint);font-size:11px;"> vs </span> @endif
                    {{ $m['opponent'] }}
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@php $preds = $predictions ?? collect(); @endphp
@if($preds->isNotEmpty())
<h2 style="font-size:15px;font-weight:700;margin:26px 0 12px;">
    <i class="fa-solid fa-wand-magic-sparkles" style="color:var(--accent);"></i> Mis predicciones
</h2>
<div style="display:flex;flex-direction:column;gap:8px;">
    @foreach($preds as $p)
    @php
        $scored = $p->scored_at !== null;
        $predStr = collect($p->sets)->map(fn($s) => ($s[0] ?? 0).'-'.($s[1] ?? 0))->implode(', ');
    @endphp
    <div class="tc-card" style="border-left:3px solid {{ $scored ? ($p->correct ? 'var(--success)' : 'var(--border-strong)') : 'var(--accent)' }};">
        <div class="tc-card__body" style="display:flex;gap:14px;align-items:center;">
            <div style="min-width:60px;text-align:center;">
                @if(!$scored)
                <span class="pub-chip" style="font-size:10px;">Pendiente</span>
                @elseif($p->correct)
                <span class="pub-chip pp-chip--accent" style="font-size:10px;">¡Acierto! +1</span>
                @else
                <span style="font-size:11px;color:var(--text-faint);font-weight:600;">Fallo</span>
                @endif
            </div>
            <div style="flex:1;">
                <div style="font-size:10px;color:var(--text-faint);font-weight:600;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px;">
                    {{ $p->tournament?->name }}
                </div>
                <div style="font-size:13.5px;">
                    {{ $p->match?->pairA?->name() ?? '—' }} <span style="color:var(--text-faint);">vs</span> {{ $p->match?->pairB?->name() ?? '—' }}
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                    Tu predicción: <span class="pub-mono">{{ $predStr }}</span>
                    @if($scored && $p->match?->sets)
                    · Real: <span class="pub-mono">{{ collect($p->match->sets)->map(fn($s) => ($s[0]??0).'-'.($s[1]??0))->implode(', ') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

@endunless
@endsection