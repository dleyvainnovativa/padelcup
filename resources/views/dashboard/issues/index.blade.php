@extends('layouts.app')

@section('title', 'Casos pendientes')

@section('content')
<div class="page-head">
    <div>
        <h1>Casos pendientes</h1>
        <div class="page-sub">Inscripciones que requieren tu atención (pago incompleto o vencido).</div>
    </div>
</div>

@include('dashboard.partials.flash')

<div class="tc-card">
    <div class="tc-table-wrap">
        <table class="tc-table">
            <thead>
                <tr>
                    <th>Pareja</th>
                    <th>Categoría</th>
                    <th>Situación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($issues as $reg)
                <tr>
                    <td>{{ $reg->pair->name() }}</td>
                    <td>{{ $reg->category->name }}</td>
                    <td>
                        @if($reg->invitation && $reg->invitation->status->value === 'expired')
                        <span style="font-size:12px;color:var(--text-muted);">Compañero/a no completó (invitación expirada)</span>
                        @else
                        <span style="font-size:12px;color:var(--text-muted);">Hold vencido sin pago completo</span>
                        @endif
                    </td>
                    <td style="text-align:right;">
                        <div class="d-inline-flex gap-2">
                            <form method="POST" action="{{ route('issues.resolve', $reg) }}">
                                @csrf
                                <input type="hidden" name="action" value="extend">
                                <button class="btn btn-soft btn-sm" title="Extender 48h">Extender</button>
                            </form>
                            <form method="POST" action="{{ route('issues.resolve', $reg) }}"
                                data-confirm="¿Reembolsar lo pagado y cancelar?" data-confirm-title="Reembolsar y cancelar" data-confirm-variant="danger" data-confirm-ok="Sí, reembolsar">
                                @csrf
                                <input type="hidden" name="action" value="refund">
                                <button class="btn btn-soft btn-sm" style="color:var(--danger-text);">Reembolsar y cancelar</button>
                            </form>
                            <form method="POST" action="{{ route('issues.resolve', $reg) }}"
                                data-confirm="¿Cancelar la inscripción sin reembolso?" data-confirm-title="Cancelar" data-confirm-variant="danger" data-confirm-ok="Sí, cancelar">
                                @csrf
                                <input type="hidden" name="action" value="cancel">
                                <button class="btn btn-soft btn-sm">Cancelar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="color:var(--text-muted);">No hay casos pendientes. Todo en orden.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $issues->links() }}</div>
@endsection