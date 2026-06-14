@extends('layouts.app')

@section('title', 'Importar parejas')

@section('content')
<div class="page-head">
    <div>
        <h1>Importar parejas</h1>
        <div class="page-sub">{{ $category->name }} · {{ $tournament->name }}</div>
    </div>
</div>

<div class="tc-card" style="max-width:600px;">
    <div class="tc-card__body">
        <p style="font-size:13px;color:var(--text-muted);">
            Sube un CSV con <strong>una pareja por fila</strong>. Columnas:
        </p>
        <pre style="font-size:12px;background:var(--bg-subtle);padding:10px 12px;border-radius:var(--radius);overflow:auto;color:var(--text-muted);">player1_name,player1_email,player1_phone,player2_name,player2_email,player2_phone</pre>
        <p style="font-size:12px;color:var(--text-faint);">
            Solo los nombres son obligatorios. El correo y teléfono son opcionales.
            Revisarás posibles duplicados antes de confirmar.
        </p>

        <form method="POST" action="{{ route('pairs.import.preview', [$tournament, $category]) }}" enctype="multipart/form-data" class="mt-3">
            @csrf
            <div class="mb-3">
                <input type="file" name="file" accept=".csv,text/csv" required
                    class="form-control @error('file') is-invalid @enderror" style="border-radius:var(--radius);">
                @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-accent">Previsualizar</button>
                <a href="{{ route('categories.show', [$tournament, $category]) }}" class="btn btn-soft">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection