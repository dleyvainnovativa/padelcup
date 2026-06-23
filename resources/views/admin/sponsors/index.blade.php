 @extends('layouts.app')

 @section('title', 'Patrocinadores (admin)')

 @section('content')
 <div class="page-head">
     <div>
         <h1>Patrocinadores</h1>
         <div class="page-sub">Patrocinadores de plataforma — globales o por torneo</div>
     </div>
 </div>

 @include('dashboard.partials.flash')

 <div class="row g-3">
     <div class="col-12 col-lg-5">
         <div class="section-title">Nuevo patrocinador</div>
         <div class="tc-card">
             <div class="tc-card__body">
                 <form method="POST" action="{{ route('admin.sponsors.store') }}" enctype="multipart/form-data" x-data="{ scope: 'global' }">
                     @csrf
                     <div class="mb-3">
                         <label class="form-label" style="font-size:13px;font-weight:500;">Nombre (opcional)</label>
                         <input type="text" name="name" value="{{ old('name') }}" class="form-control" style="border-radius:var(--radius);">
                     </div>
                     <div class="mb-3">
                         <label class="form-label" style="font-size:13px;font-weight:500;">Enlace (opcional)</label>
                         <input type="url" name="link_url" value="{{ old('link_url') }}" placeholder="https://…" class="form-control" style="border-radius:var(--radius);">
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
                         <label class="form-label" style="font-size:13px;font-weight:500;">Logo / imagen</label>
                         <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="form-control @error('image') is-invalid @enderror" style="border-radius:var(--radius);">
                         <div style="font-size:11px;color:var(--text-faint);margin-top:4px;">JPG, PNG o WEBP, máx 4 MB.</div>
                         @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                     </div>
                     <button type="submit" class="btn btn-accent btn-sm"><i class="fa-solid fa-plus me-1"></i> Crear</button>
                 </form>
             </div>
         </div>
     </div>

     <div class="col-12 col-lg-7">
         <div class="section-title">Patrocinadores de plataforma ({{ $sponsors->count() }})</div>
         @if($sponsors->isEmpty())
         <div class="tc-card">
             <div class="tc-card__body" style="color:var(--text-muted);font-size:14px;">
                 Aún no hay patrocinadores de plataforma. Los globales aparecen en todos los torneos; los específicos solo en su torneo. (Los patrocinadores que agregan los managers se gestionan dentro de cada torneo.)
             </div>
         </div>
         @else
         <div class="tc-card">
             <div class="tc-card__body" style="display:flex;flex-direction:column;gap:10px;">
                 @foreach($sponsors as $sponsor)
                 <div class="ad-item">
                     <img src="{{ $sponsor->imageUrl() }}" alt="{{ $sponsor->name }}" class="sponsor-item__img">
                     <div class="ad-item__info">
                         <div class="ad-item__title">
                             {{ $sponsor->name ?: 'Sin nombre' }}
                             @if($sponsor->isGlobal())
                             <x-pill variant="accent" dot>Global</x-pill>
                             @else
                             <x-pill variant="neutral" dot>{{ $sponsor->tournament?->name ?? 'Torneo' }}</x-pill>
                             @endif
                             @unless($sponsor->is_active)<span style="font-size:11px;color:var(--text-faint);">(oculto)</span>@endunless
                         </div>
                         @if($sponsor->link_url)
                         <div class="ad-item__meta"><a href="{{ $sponsor->link_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($sponsor->link_url, 36) }}</a></div>
                         @endif
                     </div>
                     <div class="ad-item__actions">
                         <form method="POST" action="{{ route('admin.sponsors.update', $sponsor) }}">
                             @csrf
                             <input type="hidden" name="is_active" value="{{ $sponsor->is_active ? 0 : 1 }}">
                             <button type="submit" class="btn btn-soft btn-sm" title="{{ $sponsor->is_active ? 'Ocultar' : 'Mostrar' }}">
                                 <i class="fa-solid {{ $sponsor->is_active ? 'fa-eye' : 'fa-eye-slash' }}"></i>
                             </button>
                         </form>
                         <form method="POST" action="{{ route('admin.sponsors.destroy', $sponsor) }}"
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