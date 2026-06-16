@extends('layouts.public')

@section('title', $tournament->name)

@section('content')
<div class="pub-wrap">
    @if($tournament->coverImageUrl())
    <div class="pub-cover">
        <img src="{{ $tournament->coverImageUrl() }}" alt="{{ $tournament->name }}">
    </div>
    @endif
    <div class="pub-header">
        <div class="pub-status">
            <span class="pub-status__dot pub-status__dot--{{ $tournament->isLocked() ? 'live' : 'soon' }}"></span>
            {{ $tournament->phase->label() }}
        </div>
        <h1>{{ $tournament->name }}</h1>
        @if($tournament->starts_on)
        <div class="pub-sub">
            <i class="fa-regular fa-calendar"></i>
            {{ $tournament->starts_on->translatedFormat('d M Y') }}
            @if($tournament->ends_on && !$tournament->ends_on->equalTo($tournament->starts_on))
            – {{ $tournament->ends_on->translatedFormat('d M Y') }}
            @endif
        </div>
        @endif
        @if($tournament->description)
        <p class="pub-desc">{{ $tournament->description }}</p>
        @endif
    </div>

    <div class="pub-actions">
        <a href="{{ route('public.schedule', $tournament) }}" class="pub-btn pub-btn--primary">
            <i class="fa-solid fa-calendar-days"></i> Ver calendario
        </a>
        <button type="button" class="pub-btn" data-share="{{ route('public.tournament', $tournament) }}" data-share-title="{{ $tournament->name }}">
            <i class="fa-solid fa-share-nodes"></i> Compartir
        </button>
    </div>

    <h2 class="pub-section-title">Categorías</h2>
    @if($categories->isEmpty())
    <div class="pub-empty">Aún no hay categorías publicadas.</div>
    @else
    <div class="pub-grid">
        @foreach($categories as $category)
        <a href="{{ route('public.category', [$tournament, $category]) }}" class="pub-cat-card">
            <div class="pub-cat-card__name">{{ $category->name }}</div>
            <div class="pub-cat-card__meta">
                <span><i class="fa-solid fa-users"></i> {{ $category->pairs_count }} parejas</span>
                <span class="pub-cat-card__fmt">{{ $category->format->label() }}</span>
            </div>
            <span class="pub-cat-card__go"><i class="fa-solid fa-arrow-right"></i></span>
        </a>
        @endforeach
    </div>
    @endif

    @if($sponsors->isNotEmpty())
    <h2 class="pub-section-title" style="margin-top:32px;">Patrocinadores</h2>
    <div class="pub-sponsors" data-carousel>
        <div class="pub-sponsors__track">
            @foreach($sponsors as $sponsor)
            @php $inner = '<img src="'.$sponsor->imageUrl().'" alt="'.e($sponsor->name).'">'; @endphp
            @if($sponsor->link_url)
            <a href="{{ $sponsor->link_url }}" target="_blank" rel="noopener" class="pub-sponsor">{!! $inner !!}</a>
            @else
            <div class="pub-sponsor">{!! $inner !!}</div>
            @endif
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection