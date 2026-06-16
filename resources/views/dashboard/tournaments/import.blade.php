@extends('layouts.app')

@section('title', 'Importar · '.$tournament->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Importar parejas</h1>
            <div class="page-sub">{{ $tournament->name }}</div>
        </div>
        <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a>
    </div>

    @include('dashboard.partials.flash')
    @if($errors->any())
        <div class="alert py-2 px-3 mb-3" style="font-size:13px;border-radius:var(--radius);background:var(--danger-soft);color:var(--danger-text);">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="tc-card mb-3">
        <div class="tc-card__body">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:6px;">
                Sube un archivo <strong>CSV o XLSX</strong>, o pega los datos. Cada fila es una pareja. La columna <code>category</code> agrupa las parejas; las categorías que no existan se crearán con valores por defecto (las puedes editar después).
            </p>
            <div style="background:var(--bg-subtle);border-radius:var(--radius);padding:10px 12px;font-family:var(--font-mono,monospace);font-size:11px;overflow-x:auto;white-space:nowrap;color:var(--text-muted);">
                category,player1_name,player1_email,player1_phone,player2_name,player2_email,player2_phone
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('tournaments.import.preview', $tournament) }}" enctype="multipart/form-data"
          x-data="{ mode: 'file' }">
        @csrf

        <div class="tc-tabs mb-3" style="width:fit-content;">
            <button type="button" class="tc-tab" :class="{ 'is-active': mode === 'file' }" @click="mode = 'file'">Subir archivo</button>
            <button type="button" class="tc-tab" :class="{ 'is-active': mode === 'paste' }" @click="mode = 'paste'">Pegar datos</button>
        </div>

        <div class="tc-card mb-3">
            <div class="tc-card__body">
                <div x-show="mode === 'file'">
                    <label class="form-label" style="font-size:13px;font-weight:500;">Archivo CSV o XLSX</label>
                    <input type="file" name="file" accept=".csv,.txt,.xlsx,.xls" class="form-control" style="border-radius:var(--radius);">
                </div>
                <div x-show="mode === 'paste'" x-cloak>
                    <label class="form-label" style="font-size:13px;font-weight:500;">Pega aquí (incluye el encabezado)</label>
                    <textarea name="pasted" rows="10" class="form-control" style="border-radius:var(--radius);font-family:var(--font-mono,monospace);font-size:12px;"
                              placeholder="category,player1_name,player1_email,player1_phone,player2_name,player2_email,player2_phone&#10;5ta Femenil,Ana Ríos,ana@mail.com,5551234567,Lucía Paz,,">{{ old('pasted') }}</textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-accent"><i class="fa-solid fa-eye me-1"></i> Previsualizar</button>
    </form>
@endsection
