@extends('layouts.app')

@section('title', 'Reclamar mi perfil')

@section('content')
<div class="page-head">
    <div>
        <h1>Reclamar mi perfil de jugador</h1>
        <div class="page-sub">Busca tu nombre para vincular los torneos en los que has jugado.</div>
    </div>
</div>

@include('dashboard.partials.flash')

@error('players')
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    {{ $message }}
</div>
@enderror

<form method="GET" action="{{ route('player.claim.create') }}" class="mb-4" style="max-width:520px;">
    <div style="display:flex;gap:8px;">
        <input type="text" name="q" value="{{ $q }}" placeholder="Tu nombre completo…" autofocus
            class="form-control" style="border-radius:var(--radius);">
        <button type="submit" class="btn btn-accent">Buscar</button>
    </div>
</form>

@if($q !== '' && $results->isEmpty())
<div class="tc-card"><div class="tc-card__body" style="text-align:center;color:var(--text-faint);padding:32px;">
    No encontramos registros disponibles con ese nombre.<br>
    <span style="font-size:13px;">Puede que ya estén reclamados, o que el organizador aún no te haya agregado.</span>
</div></div>
@endif

@if($results->isNotEmpty())
<form method="POST" action="{{ route('player.claim.store') }}">
    @csrf
    <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px;">
        Marca los registros que <strong>son tuyos</strong>. Un administrador los revisará antes de vincularlos.
    </p>

    <div style="display:flex;flex-direction:column;gap:10px;">
        @foreach($results as $group)
        @php $gid = 'g'.$loop->index; @endphp
        <div class="tc-card" data-group>
            <div class="tc-card__body" style="display:flex;gap:12px;align-items:flex-start;">
                <input type="checkbox" id="{{ $gid }}" class="form-check-input mt-1"
                    onchange="
                        document.querySelectorAll('[data-ids-{{ $gid }}]').forEach(function(i){ i.disabled = !event.target.checked; });
                        this.closest('[data-group]').style.borderColor = this.checked ? 'var(--accent)' : '';
                    ">
                <label for="{{ $gid }}" style="flex:1;cursor:pointer;margin:0;">
                    <div style="font-weight:700;font-size:15px;margin-bottom:6px;">{{ $group['display_name'] }}</div>
                    @if(!empty($group['contexts']))
                    <div style="display:flex;flex-direction:column;gap:3px;">
                        @foreach($group['contexts'] as $ctx)
                        <div style="font-size:12.5px;color:var(--text-muted);">
                            <i class="fa-solid fa-trophy" style="color:var(--accent);width:14px;"></i>
                            {{ $ctx['tournament'] }}
                            @if($ctx['category']) · <span style="color:var(--text-faint);">{{ $ctx['category'] }}</span>@endif
                            @if($ctx['partner']) · con {{ $ctx['partner'] }}@endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </label>
            </div>
            @foreach($group['player_ids'] as $pid)
            <input type="hidden" name="player_ids[]" value="{{ $pid }}" data-ids-{{ $gid }} disabled>
            @endforeach
        </div>
        @endforeach
    </div>

    <div class="mt-3" style="max-width:520px;">
        <label class="form-label" style="font-size:13px;font-weight:500;">Nota para el administrador (opcional)</label>
        <textarea name="note" rows="2" class="form-control" style="border-radius:var(--radius);"
            placeholder="Ej. Jugué la 5ta femenil en Copa Verano con Lucía Paz.">{{ old('note') }}</textarea>
    </div>

    <button type="submit" class="btn btn-accent mt-3">Enviar solicitud</button>
</form>
@endif

<p class="mt-4"><a href="{{ route('player.claims') }}" style="font-size:13px;">Ver mis solicitudes</a></p>
@endsection
