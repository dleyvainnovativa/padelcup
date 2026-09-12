@extends('layouts.public')
@section('title', $current['title'])

@section('content')
<div class="pub-wrap docs-layout">

    {{-- Sidebar --}}
    <aside class="docs-sidebar">
        <a href="{{ route('docs.index') }}" class="docs-sidebar__home">
            <i class="fa-solid fa-book"></i> Guías
        </a>
        @foreach($sections as $section)
        <div class="docs-sidebar__group">
            <span class="docs-sidebar__title">{{ $section['title'] }}</span>
            @foreach($section['items'] as $item)
            <a href="{{ route('docs.show', $item['slug']) }}"
               class="docs-sidebar__link {{ $item['slug'] === $current['slug'] ? 'is-active' : '' }}">
                {{ $item['title'] }}
            </a>
            @endforeach
        </div>
        @endforeach
    </aside>

    {{-- Article --}}
    <article class="docs-article">
        <nav class="docs-breadcrumb">
            <a href="{{ route('docs.index') }}">Guías</a>
            <i class="fa-solid fa-chevron-right"></i>
            <span>{{ $current['title'] }}</span>
        </nav>

        <h1 class="docs-article__title">{{ $current['title'] }}</h1>

        <div class="docs-body">
            @include($partial)
        </div>

        {{-- Prev / next --}}
        <div class="docs-nav">
            @if($prev)
            <a href="{{ route('docs.show', $prev['slug']) }}" class="docs-nav__link docs-nav__link--prev">
                <span class="docs-nav__dir"><i class="fa-solid fa-arrow-left"></i> Anterior</span>
                <span class="docs-nav__title">{{ $prev['title'] }}</span>
            </a>
            @else <span></span> @endif

            @if($next)
            <a href="{{ route('docs.show', $next['slug']) }}" class="docs-nav__link docs-nav__link--next">
                <span class="docs-nav__dir">Siguiente <i class="fa-solid fa-arrow-right"></i></span>
                <span class="docs-nav__title">{{ $next['title'] }}</span>
            </a>
            @endif
        </div>
    </article>
</div>
@endsection
