{{-- resources/views/dashboard/index.blade.php
     Sample dashboard page. Demonstrates the layout + components.
     Real data wiring comes in later phases; values here are placeholders. --}}
@extends('layouts.app')

@section('title', 'Panel')

@section('content')
<div class="page-head">
    <div>
        <h1>Torneo Apertura 2026</h1>
        <div class="page-sub">
            Club Raqueta CDMX · 14 jun – 22 jun ·
            <x-pill variant="accent" dot>En curso</x-pill>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="#" class="btn btn-soft"><i class="fa-solid fa-eye me-1"></i> Página pública</a>
        <a href="#" class="btn btn-accent"><i class="fa-solid fa-plus me-1"></i> Nueva categoría</a>
    </div>
</div>

{{-- Stat tiles --}}
<div class="row g-3 mb-2">
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-people-group" label="Parejas inscritas"
            value="64" delta="8 esta semana" trend="up" accent />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-circle-check" label="Pagadas completas"
            value="51" delta="80% de parejas" trend="up" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-clock" label="Pago pendiente"
            value="9" delta="3 por vencer" trend="down" />
    </div>
    <div class="col-6 col-lg-3">
        <x-stat-tile icon="fa-money-bill-trend-up" label="Recaudado"
            value="$38.2k" delta="neto de comisiones" trend="none" />
    </div>
</div>

{{-- Recent registrations --}}
<div class="section-title">Inscripciones recientes</div>
<div class="tc-card">
    <div class="tc-card__head">
        <h3>Últimas parejas</h3>
        <a href="#" class="btn btn-soft btn-sm">Ver todas</a>
    </div>
    <div class="tc-table-wrap">
        <table class="tc-table">
            <thead>
                <tr>
                    <th>Pareja</th>
                    <th>Categoría</th>
                    <th>Origen</th>
                    <th>Pago</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ana Morales / Lucía Ríos</td>
                    <td>5ta Femenil</td>
                    <td><x-pill variant="neutral">Autoinscripción</x-pill></td>
                    <td class="font-mono">$1,200</td>
                    <td><x-pill variant="ok" dot>Confirmada</x-pill></td>
                </tr>
                <tr>
                    <td>Carlos Gómez / Diego Paz</td>
                    <td>5ta Varonil</td>
                    <td><x-pill variant="neutral">Autoinscripción</x-pill></td>
                    <td class="font-mono">$600 / $1,200</td>
                    <td><x-pill variant="warn" dot>Falta compañero</x-pill></td>
                </tr>
                <tr>
                    <td>Rosa Vega / Marta Sol</td>
                    <td>Abierto Femenil</td>
                    <td><x-pill variant="neutral">Manual</x-pill></td>
                    <td class="font-mono">$0 / $1,200</td>
                    <td><x-pill variant="bad" dot>Vencida</x-pill></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection