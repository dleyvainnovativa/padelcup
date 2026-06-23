@extends('layouts.app')

@section('title', 'Anuncios')

@section('content')
    <div class="page-head">
        <div>
            <h1>Anuncios</h1>
            <div class="page-sub">Publicidad 16:9 en las páginas públicas de torneos</div>
        </div>
    </div>

    @include('dashboard.partials.flash')

    <div class="row g-3">
        {{-- Add form --}}
        <div class="col-12 col-lg-5">
            <div class="section-title">Nuevo anuncio</div>
            <div class="tc-card">
                <div class="tc-card__body">
                    <form method="POST" action="{{ route('admin.ads.store') }}" enctype="multipart/form-data"
                          x-data="{ scope: 'global' }">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;">Título (opcional)</label>
                            <input type="text" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" style="border-radius:var(--radius);">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;">Enlace (opcional)</label>
                            <input type="url" name="link_url" value="{{ old('link_url') }}" placeholder="https://…" class="form-control @error('link_url') is-invalid @enderror" style="border-radius:var(--radius);">
                            @error('link_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;">Alcance</label>
                            <select name="scope" x-model="scope" class="form-select" style="border-radius:var(--radius);">
                                <option value="global">Global (todos los torneos)</option>
                                <option value="tournament">Un torneo específico</option>
                            </select>
                        </div>
                        <div class="mb-3" x-show="scope === 'tournament'" x-cloak>
                            <label class="form-label" style="font-size:13px;font-weight:500;">Torneo</label>
                            <select name="tournament_id" class="form-select" style="border-radius:var(--radius);">
                                <option value="">— Selecciona —</option>
                                @foreach($tournaments as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            @error('tournament_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:13px;font-weight:500;">Imagen 16:9</label>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control @error('image') is-invalid @enderror" style="border-radius:var(--radius);">
                            <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">Recomendado 1200×675 (16:9). JPG, PNG o WEBP, máx 4 MB.</div>
                            @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-accent btn-sm"><i class="fa-solid fa-plus me-1"></i> Crear anuncio</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- List --}}
        <div class="col-12 col-lg-7">
            <div class="section-title">Anuncios ({{ $ads->count() }})</div>
            @if($ads->isEmpty())
                <div class="tc-card"><div class="tc-card__body" style="color:var(--text-muted);font-size:14px;">
                    Aún no hay anuncios. Los globales aparecen en todos los torneos; los específicos solo en su torneo.
                </div></div>
            @else
                <div class="tc-card">
                    <div class="tc-card__body" style="display:flex;flex-direction:column;gap:10px;">
                        @foreach($ads as $ad)
                            <div class="ad-item">
                                <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->title }}" class="ad-item__img">
                                <div class="ad-item__info">
                                    <div class="ad-item__title">
                                        {{ $ad->title ?: 'Sin título' }}
                                        @if($ad->isGlobal())
                                            <x-pill variant="accent" dot>Global</x-pill>
                                        @else
                                            <x-pill variant="neutral" dot>{{ $ad->tournament?->name ?? 'Torneo' }}</x-pill>
                                        @endif
                                        @unless($ad->is_active)<span style="font-size:11px;color:var(--text-faint);">(oculto)</span>@endunless
                                    </div>
                                    <div class="ad-item__meta">
                                        <i class="fa-solid fa-arrow-pointer"></i> {{ $ad->clicks }} clics
                                        @if($ad->link_url) · <a href="{{ $ad->link_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($ad->link_url, 30) }}</a>@endif
                                    </div>
                                </div>
                                <div class="ad-item__actions">
                                    <form method="POST" action="{{ route('admin.ads.update', $ad) }}">
                                        @csrf
                                        <input type="hidden" name="is_active" value="{{ $ad->is_active ? 0 : 1 }}">
                                        <button type="submit" class="btn btn-soft btn-sm" title="{{ $ad->is_active ? 'Ocultar' : 'Mostrar' }}">
                                            <i class="fa-solid {{ $ad->is_active ? 'fa-eye' : 'fa-eye-slash' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.ads.destroy', $ad) }}"
                                          data-confirm="¿Eliminar este anuncio?" data-confirm-title="Eliminar" data-confirm-ok="Eliminar" data-confirm-variant="danger">
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
