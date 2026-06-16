@extends('layouts.app')

@section('title', 'Patrocinadores · '.$tournament->name)

@section('content')
<div class="page-head">
    <div>
        <h1>Patrocinadores</h1>
        <div class="page-sub">{{ $tournament->name }}</div>
    </div>
    <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-soft"><i class="fa-solid fa-arrow-left me-1"></i> Volver</a>
</div>

@include('dashboard.partials.flash')

<div class="row g-3">
    {{-- Add form --}}
    <div class="col-12 col-lg-5">
        <div class="section-title">Agregar patrocinador</div>
        <div class="tc-card">
            <div class="tc-card__body">
                <form method="POST" action="{{ route('sponsors.store', $tournament) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:500;">Nombre (opcional)</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" style="border-radius:var(--radius);">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:500;">Enlace (opcional)</label>
                        <input type="url" name="link_url" value="{{ old('link_url') }}" placeholder="https://…" class="form-control @error('link_url') is-invalid @enderror" style="border-radius:var(--radius);">
                        @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:13px;font-weight:500;">Imagen / logo</label>
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control @error('image') is-invalid @enderror" style="border-radius:var(--radius);">
                        <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">JPG, PNG o WEBP, máx 4 MB. Se muestra en el carrusel de la página pública.</div>
                        @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-accent btn-sm"><i class="fa-solid fa-plus me-1"></i> Agregar</button>
                </form>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="col-12 col-lg-7">
        <div class="section-title">Patrocinadores ({{ $sponsors->count() }})</div>
        @if($sponsors->isEmpty())
        <div class="tc-card">
            <div class="tc-card__body" style="color:var(--text-muted);font-size:14px;">
                Aún no hay patrocinadores. Los que agregues aparecerán en un carrusel en la página pública del torneo.
            </div>
        </div>
        @else
        <div class="tc-card">
            <div class="tc-card__body" style="display:flex;flex-direction:column;gap:10px;">
                @foreach($sponsors as $sponsor)
                <div class="sponsor-item">
                    <img src="{{ $sponsor->imageUrl() }}" alt="{{ $sponsor->name }}" class="sponsor-item__img">
                    <div class="sponsor-item__info">
                        <div class="sponsor-item__name">{{ $sponsor->name ?: 'Sin nombre' }}</div>
                        @if($sponsor->link_url)
                        <a href="{{ $sponsor->link_url }}" target="_blank" class="sponsor-item__link">{{ \Illuminate\Support\Str::limit($sponsor->link_url, 36) }}</a>
                        @endif
                    </div>
                    <div class="sponsor-item__actions">
                        <form method="POST" action="{{ route('sponsors.update', [$tournament, $sponsor]) }}">
                            @csrf
                            <input type="hidden" name="is_active" value="{{ $sponsor->is_active ? 0 : 1 }}">
                            <button type="submit" class="btn btn-soft btn-sm" title="{{ $sponsor->is_active ? 'Ocultar' : 'Mostrar' }}">
                                <i class="fa-solid {{ $sponsor->is_active ? 'fa-eye' : 'fa-eye-slash' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('sponsors.destroy', [$tournament, $sponsor]) }}"
                            data-confirm="¿Eliminar este patrocinador?" data-confirm-title="Eliminar" data-confirm-ok="Eliminar" data-confirm-variant="danger">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-soft btn-sm"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection