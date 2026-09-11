@extends('layouts.app')

@section('title', 'Importar torneo completo')

@section('content')
<div class="page-head">
    <div>
        <h1>Importar torneo completo</h1>
        <div class="page-sub">Crea un torneo nuevo a partir de un archivo <code>.json</code> exportado de Voleo.</div>
    </div>
    <a href="{{ route('tournaments.index') }}" class="btn btn-soft"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a>
</div>

@include('dashboard.partials.flash')
@if($errors->any())
<div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

<div class="tc-card" style="max-width:560px;">
    <div class="tc-card__body">
        <form method="POST" action="{{ route('tournaments.transfer.import') }}" enctype="multipart/form-data">
            @csrf
            <label class="form-label" style="font-size:13px;font-weight:600;">Archivo del torneo (.json)</label>
            <input type="file" name="file" accept="application/json,.json" required
                   class="form-control" style="border-radius:var(--radius);">
            <p style="font-size:12.5px;color:var(--text-muted);margin:12px 0 0;line-height:1.5;">
                Se creará un <b>torneo nuevo</b> en modo borrador. No se modifica ningún torneo existente.
                Las imágenes (logo, portada, patrocinadores) deberán volver a subirse, y los datos de
                pago se importan como historial sin vínculo a Stripe.
            </p>
            <button type="submit" class="btn btn-accent mt-3">
                <i class="fa-solid fa-file-import me-1"></i> Importar torneo
            </button>
        </form>
    </div>
</div>
@endsection
