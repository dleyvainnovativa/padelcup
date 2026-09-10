@extends('layouts.app')

@section('title', 'Mis solicitudes')

@section('content')
<div class="page-head">
    <div>
        <h1>Mis solicitudes de perfil</h1>
        <div class="page-sub">Estado de tus reclamos de registros de jugador.</div>
    </div>
    <a href="{{ route('player.claim.create') }}" class="btn btn-accent">Reclamar otro perfil</a>
</div>

@include('dashboard.partials.flash')

@if($claims->isEmpty())
<div class="tc-card"><div class="tc-card__body" style="text-align:center;color:var(--text-faint);padding:40px;">
    Aún no has enviado solicitudes.<br>
    <a href="{{ route('player.claim.create') }}">Reclama tu perfil de jugador</a> para ver tus torneos y partidos.
</div></div>
@else
<div style="display:flex;flex-direction:column;gap:10px;">
    @foreach($claims as $claim)
    <div class="tc-card"><div class="tc-card__body">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            <div style="font-weight:600;font-size:14px;">
                {{ $claim->players->pluck('name')->unique()->implode(', ') ?: 'Registro' }}
            </div>
            @if($claim->status === \App\Models\PlayerClaim::APPROVED)
                <x-pill variant="success" dot>Aprobada</x-pill>
            @elseif($claim->status === \App\Models\PlayerClaim::REJECTED)
                <x-pill variant="danger" dot>Rechazada</x-pill>
            @else
                <x-pill variant="warning" dot>En revisión</x-pill>
            @endif
        </div>
        <div style="font-size:12px;color:var(--text-faint);margin-top:6px;">
            Enviada {{ $claim->created_at->diffForHumans() }}
            @if($claim->reviewed_at) · revisada {{ $claim->reviewed_at->diffForHumans() }}@endif
        </div>
        @if($claim->status === \App\Models\PlayerClaim::REJECTED && $claim->review_note)
        <div style="font-size:12.5px;color:var(--danger-text);margin-top:8px;">
            <strong>Motivo:</strong> {{ $claim->review_note }}
        </div>
        @endif
    </div></div>
    @endforeach
</div>
@endif
@endsection
