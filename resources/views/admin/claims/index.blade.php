@extends('layouts.app')

@section('title', 'Reclamos de perfil')

@section('content')
<div class="page-head">
    <div>
        <h1>Reclamos de perfil</h1>
        <div class="page-sub">Valida las solicitudes de jugadores para vincular sus registros.</div>
    </div>
</div>

@include('dashboard.partials.flash')

@error('claim')
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">{{ $message }}</div>
@enderror

<h2 class="pub-section-title" style="margin-bottom:12px;">Pendientes ({{ $pending->count() }})</h2>

@if($pending->isEmpty())
<div class="tc-card">
    <div class="tc-card__body" style="text-align:center;color:var(--text-faint);padding:32px;">
        No hay solicitudes pendientes.
    </div>
</div>
@else
<div style="display:flex;flex-direction:column;gap:12px;">
    @foreach($pending as $claim)
    <div class="tc-card">
        <div class="tc-card__body">
            {{-- Claimer --}}
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                <div>
                    <div style="font-weight:700;font-size:15px;">{{ $claim->user->name }}</div>
                    <div style="font-size:12.5px;color:var(--text-muted);">
                        {{ $claim->user->email }} · cuenta creada {{ $claim->user->created_at->diffForHumans() }}
                    </div>
                </div>
                <div style="font-size:12px;color:var(--text-faint);">Solicitud {{ $claim->created_at->diffForHumans() }}</div>
            </div>

            @if($claim->note)
            <div style="font-size:13px;color:var(--text);background:var(--bg-subtle);border-radius:var(--radius);padding:8px 12px;margin-top:10px;">
                <i class="fa-solid fa-quote-left" style="color:var(--text-faint);font-size:11px;"></i> {{ $claim->note }}
            </div>
            @endif

            {{-- Claimed records with full context so the admin can judge --}}
            <div style="margin-top:12px;border-top:1px solid var(--border);padding-top:10px;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-faint);margin-bottom:8px;">
                    Registros reclamados
                </div>
                @foreach($claim->items as $item)
                @php $player = $item->player; @endphp
                @if($player)
                <div style="font-size:13px;margin-bottom:8px;">
                    <span style="font-weight:600;">{{ $player->name }}</span>
                    @if($player->creator)
                    <span style="font-size:11.5px;color:var(--text-faint);"> · creado por {{ $player->creator->name }}</span>
                    @endif
                    <div style="margin-top:3px;display:flex;flex-direction:column;gap:2px;">
                        @foreach($player->pairs()->with('category.tournament','player1','player2')->get() as $pair)
                        @php $partner = $pair->player1_id === $player->id ? $pair->player2 : $pair->player1; @endphp
                        <div style="font-size:12px;color:var(--text-muted);">
                            <i class="fa-solid fa-trophy" style="color:var(--accent);width:13px;"></i>
                            {{ $pair->category?->tournament?->name }}
                            @if($pair->category) · {{ $pair->category->name }}@endif
                            @if($partner) · con {{ $partner->name }}@endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach
            </div>

            {{-- Actions --}}
            <div style="display:flex;gap:8px;margin-top:12px;">
                <form method="POST" action="{{ route('admin.claims.approve', $claim) }}"
                    data-confirm="¿Aprobar y vincular estos registros a {{ $claim->user->name }}?"
                    data-confirm-title="Aprobar reclamo"
                    data-confirm-ok="Aprobar">
                    @csrf
                    <button type="submit" class="btn btn-accent">Aprobar</button>
                </form>
                <button type="button" class="btn btn-soft" onclick="document.getElementById('reject-{{ $claim->id }}').style.display='block';">
                    Rechazar
                </button>
            </div>

            {{-- Reject form (hidden until clicked) --}}
            <form method="POST" action="{{ route('admin.claims.reject', $claim) }}" id="reject-{{ $claim->id }}"
                style="display:none;margin-top:10px;">
                @csrf
                <textarea name="review_note" rows="2" class="form-control" style="border-radius:var(--radius);"
                    placeholder="Motivo del rechazo (se envía al jugador)"></textarea>
                <button type="submit" class="btn btn-danger mt-2">Confirmar rechazo</button>
            </form>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Recently reviewed --}}
@if($recent->isNotEmpty())
<h2 class="pub-section-title" style="margin:28px 0 12px;">Revisadas recientemente</h2>
<div style="display:flex;flex-direction:column;gap:8px;">
    @foreach($recent as $claim)
    <div class="tc-card">
        <div class="tc-card__body" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <div style="font-size:13px;">
                <span style="font-weight:600;">{{ $claim->user->name }}</span>
                <span style="color:var(--text-faint);"> — {{ $claim->players->pluck('name')->unique()->implode(', ') }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
                @if($claim->status === \App\Models\PlayerClaim::APPROVED)
                <x-pill variant="success">Aprobada</x-pill>
                @else
                <x-pill variant="danger">Rechazada</x-pill>
                @endif
                <span style="font-size:11px;color:var(--text-faint);">{{ $claim->reviewer?->name }} · {{ $claim->reviewed_at?->diffForHumans() }}</span>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
@endsection