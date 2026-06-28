@extends('layouts.public')

@section('title', 'PadelCup · Gestión de torneos de pádel')

<!-- @push('head')
<link rel="stylesheet" href="{{ asset('css/landing.css') }}">
@endpush -->

@vite(["resources/css/landing.css"])

@section('content')
<div class="lp">

    {{-- ===== HERO ===== --}}
    <section class="lp-hero">
        <div class="lp-aurora" aria-hidden="true">
            <span class="lp-aurora__blob lp-aurora__blob--1"></span>
            <span class="lp-aurora__blob lp-aurora__blob--2"></span>
            <span class="lp-aurora__blob lp-aurora__blob--3"></span>
        </div>

        <div class="lp-hero__inner">
            <div class="lp-hero__copy" data-reveal>
                <span class="lp-badge"><i class="fa-solid fa-table-tennis-paddle-ball"></i> Plataforma para torneos de pádel</span>
                <h1 class="lp-hero__title">Organiza torneos de pádel <span class="lp-grad-text">sin el caos</span>.</h1>
                <p class="lp-hero__sub">
                    Inscripciones, grupos, llaves, calendario y resultados en vivo — todo en un solo lugar.
                    Comparte la página pública y deja que tus jugadores sigan cada partido.
                </p>
                <div class="lp-hero__cta">
                    <a href="{{ route('public.directory') }}" class="lp-btn lp-btn--primary">
                        <i class="fa-solid fa-trophy"></i> Ver torneos
                    </a>
                    <a href="{{ route('dashboard') }}" class="lp-btn lp-btn--ghost">
                        Soy organizador <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>
                <div class="lp-hero__trust">
                    <i class="fa-solid fa-bolt"></i> Genera grupos, llaves y calendario con un clic
                </div>
            </div>

            {{-- Product mockup: a stylized live bracket --}}
            <div class="lp-hero__visual" data-reveal data-reveal-delay="1">
                @include('public.partials.landing-mockup')
            </div>
        </div>
    </section>

    {{-- ===== STATS BAND ===== --}}
    <section class="lp-stats" data-reveal>
        <div class="lp-stat">
            <span class="lp-stat__num">8</span>
            <span class="lp-stat__label">formatos y fases</span>
        </div>
        <div class="lp-stat">
            <span class="lp-stat__num">1 clic</span>
            <span class="lp-stat__label">para generar todo</span>
        </div>
        <div class="lp-stat">
            <span class="lp-stat__num">En vivo</span>
            <span class="lp-stat__label">resultados y standings</span>
        </div>
        <div class="lp-stat">
            <span class="lp-stat__num">100%</span>
            <span class="lp-stat__label">pensado para México</span>
        </div>
    </section>

    {{-- ===== FEATURES ===== --}}
    <section class="lp-section">
        <div class="lp-section__head" data-reveal>
            <h2>Todo lo que necesitas para correr un torneo</h2>
            <p>Desde la inscripción hasta el campeón, sin hojas de cálculo ni mensajes interminables.</p>
        </div>
        <div class="lp-features">
            <div class="lp-feature" data-reveal>
                <div class="lp-feature__icon"><i class="fa-solid fa-people-group"></i></div>
                <h3>Inscripciones y parejas</h3>
                <p>Importa parejas desde Excel, detecta duplicados y arma tus categorías en minutos.</p>
            </div>
            <div class="lp-feature" data-reveal data-reveal-delay="1">
                <div class="lp-feature__icon"><i class="fa-solid fa-sitemap"></i></div>
                <h3>Grupos y llaves automáticos</h3>
                <p>Genera grupos, llaves de eliminación y la siembra completa con un solo clic.</p>
            </div>
            <div class="lp-feature" data-reveal data-reveal-delay="2">
                <div class="lp-feature__icon"><i class="fa-solid fa-calendar-days"></i></div>
                <h3>Calendario inteligente</h3>
                <p>Programa por canchas y horarios, respetando descansos y la disponibilidad de cada jugador.</p>
            </div>
            <div class="lp-feature" data-reveal>
                <div class="lp-feature__icon"><i class="fa-solid fa-globe"></i></div>
                <h3>Página pública en vivo</h3>
                <p>Cada torneo tiene su página: standings, calendario y resultados que se actualizan al instante.</p>
            </div>
            <div class="lp-feature" data-reveal data-reveal-delay="1">
                <div class="lp-feature__icon"><i class="fa-brands fa-stripe-s"></i></div>
                <h3>Cobros con Stripe</h3>
                <p>Recibe inscripciones en línea de forma segura, con todo el detalle de pagos a la mano.</p>
            </div>
            <div class="lp-feature" data-reveal data-reveal-delay="2">
                <div class="lp-feature__icon"><i class="fa-solid fa-share-nodes"></i></div>
                <h3>Comparte por WhatsApp</h3>
                <p>Enlaces y tarjetas de resultados listas para mandar al grupo de cada categoría.</p>
            </div>
        </div>
    </section>

    {{-- ===== HOW IT WORKS ===== --}}
    <section class="lp-section lp-section--alt">
        <div class="lp-section__head" data-reveal>
            <h2>De la inscripción al campeón en 3 pasos</h2>
        </div>
        <div class="lp-steps">
            <div class="lp-step" data-reveal>
                <span class="lp-step__n">1</span>
                <h3>Crea tu torneo</h3>
                <p>Define categorías, sedes y horarios. Importa parejas desde tu archivo o pégalas directo.</p>
            </div>
            <div class="lp-step" data-reveal data-reveal-delay="1">
                <span class="lp-step__n">2</span>
                <h3>Genera y programa</h3>
                <p>Grupos, llaves y calendario automáticos. Ajusta lo que quieras con arrastrar y soltar.</p>
            </div>
            <div class="lp-step" data-reveal data-reveal-delay="2">
                <span class="lp-step__n">3</span>
                <h3>Comparte y juega</h3>
                <p>Publica la página del torneo y captura resultados en vivo. Tus jugadores siguen todo.</p>
            </div>
        </div>
    </section>

    {{-- ===== CTA BAND ===== --}}
    <section class="lp-cta" data-reveal>
        <div class="lp-cta__inner">
            <h2>¿Listo para tu próximo torneo?</h2>
            <p>Explora los torneos publicados o entra como organizador y arma el tuyo hoy.</p>
            <div class="lp-hero__cta">
                <a href="{{ route('public.directory') }}" class="lp-btn lp-btn--primary"><i class="fa-solid fa-trophy"></i> Ver torneos</a>
                <a href="{{ route('public.search') }}" class="lp-btn lp-btn--ghost">Buscar jugador</a>
            </div>
        </div>
    </section>
</div>

<script>
    (function() {
        var els = document.querySelectorAll('[data-reveal]');
        if (!els.length) return;
        if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            els.forEach(function(el) {
                el.classList.add('is-in');
            });
            return;
        }
        var io = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) {
                    e.target.classList.add('is-in');
                    io.unobserve(e.target);
                }
            });
        }, {
            threshold: 0.12
        });
        els.forEach(function(el) {
            io.observe(el);
        });
    })();
</script>
@endsection