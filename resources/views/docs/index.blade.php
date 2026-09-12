@extends('layouts.public')
@section('title', 'Guías y tutoriales')

@section('content')
<div class="docs-hero">
    <div class="pub-wrap">
        <span class="docs-eyebrow">Centro de ayuda</span>
        <h1>Guías y tutoriales</h1>
        <p>Aprende a organizar tu torneo en Voleo, paso a paso — desde crearlo hasta compartir la página pública y cobrar inscripciones.</p>
    </div>
</div>

<div class="pub-wrap docs-index">
    @foreach($sections as $section)
    <section class="docs-group">
        <h2 class="docs-group__title">{{ $section['title'] }}</h2>
        <div class="docs-cards">
            @foreach($section['items'] as $item)
            <a href="{{ route('docs.show', $item['slug']) }}" class="docs-card">
                <span class="docs-card__icon"><i class="fa-solid {{ $item['icon'] }}"></i></span>
                <span class="docs-card__body">
                    <span class="docs-card__title">{{ $item['title'] }}</span>
                    <span class="docs-card__blurb">{{ $item['blurb'] }}</span>
                </span>
                <i class="fa-solid fa-arrow-right docs-card__go"></i>
            </a>
            @endforeach
        </div>
    </section>
    @endforeach

    <div class="docs-help">
        <div>
            <strong>¿No encuentras lo que buscas?</strong>
            <span>Escríbenos y con gusto te ayudamos.</span>
        </div>
        <a href="mailto:contacto@voleo.mx" class="lp-btn lp-btn--primary">Contactar soporte</a>
    </div>
</div>
@endsection
