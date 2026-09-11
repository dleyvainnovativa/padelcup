@extends('layouts.public')

@section('title', 'Voleo · Gestión de torneos')

@push('head')
@vite(['resources/css/landing.css'])
@endpush

@section('content')
<div class="lp">

    {{-- ===== HERO ===== --}}
    <section class="lp-hero" data-hero>
        <span class="lp-ball lp-ball--1" data-parallax="0.10" aria-hidden="true"></span>
        <span class="lp-ball lp-ball--2" data-parallax="0.18" aria-hidden="true"></span>
        <span class="lp-ball lp-ball--3" data-parallax="0.06" aria-hidden="true"></span>
        <span class="lp-ball lp-ball--4" data-parallax="0.22" aria-hidden="true"></span>

        <div class="lp-hero__inner">
            <div class="lp-hero__lead">
                <div class="lp-hero__lead-copy">
                    <span class="lp-eyebrow" data-intro="1">Plataforma para torneos</span>
                    <p class="lp-hero__tag" data-intro="2">
                        Inscripciones, grupos, llaves, calendario y resultados <b>en vivo</b> —
                        todo en un solo lugar.
                    </p>
                </div>
                <div class="lp-hero__lead-cta" data-intro="3">
                    <a href="{{ route('public.directory') }}" class="lp-btn lp-btn--primary">
                        Ver torneos <span class="lp-btn__ico"><i class="fa-solid fa-arrow-right"></i></span>
                    </a>
                    <a href="{{ route('dashboard') }}" class="lp-btn lp-btn--ghost">Soy organizador</a>
                </div>
            </div>

            {{-- Giant rotating wordmark + athlete image that swaps in sync.
                 JS (below) cycles the words in .lp-word[data-rotator] and the
                 images in .lp-stage__img[data-img-rotator] every ~3s. --}}
            <div class="lp-stage">
                <h1 class="lp-word" data-intro="4" data-rotator aria-label="Pádel y Tennis">
                    <span class="lp-word__line lp-word__line--1">
                        <span class="lp-word__cycle">
                            <span class="lp-word__item is-active" data-word>Pádel<span class="lp-word__dot">.</span></span>
                            <span class="lp-word__item" data-word>Tennis<span class="lp-word__dot">.</span></span>
                        </span>
                    </span>
                </h1>

                <figure class="lp-stage__img" data-intro="5" data-parallax="-0.05" data-img-rotator>
                    <img class="lp-stage__pic is-active" data-img
                        src="{{ asset('img/landing/padel.png') }}"
                        alt="Jugador de pádel" width="1086" height="1448"
                        loading="eager" fetchpriority="high">
                    <img class="lp-stage__pic" data-img
                        src="{{ asset('img/landing/tennis.png') }}"
                        alt="Jugadora de tenis" width="1086" height="1448"
                        loading="eager">
                </figure>
                {{-- Stats strip --}}
                <div class="lp-stats" data-reveal data-reveal-delay="2">
                    <div class="lp-stat"><span class="lp-stat__num">8</span><span class="lp-stat__label">formatos y fases</span></div>
                    <div class="lp-stat"><span class="lp-stat__num">1 clic</span><span class="lp-stat__label">para generar todo</span></div>
                    <div class="lp-stat"><span class="lp-stat__num">En vivo</span><span class="lp-stat__label">resultados y standings</span></div>
                    <div class="lp-stat"><span class="lp-stat__num">100%</span><span class="lp-stat__label">pensado para México</span></div>
                </div>
            </div>
        </div>

        <!-- {{-- Stats strip --}}
        <div class="lp-stats" data-reveal data-reveal-delay="2">
            <div class="lp-stat"><span class="lp-stat__num">8</span><span class="lp-stat__label">formatos y fases</span></div>
            <div class="lp-stat"><span class="lp-stat__num">1 clic</span><span class="lp-stat__label">para generar todo</span></div>
            <div class="lp-stat"><span class="lp-stat__num">En vivo</span><span class="lp-stat__label">resultados y standings</span></div>
            <div class="lp-stat"><span class="lp-stat__num">100%</span><span class="lp-stat__label">pensado para México</span></div>
        </div> -->
    </section>

    {{-- ===== WELCOME / INTRO SPLIT ===== --}}
    <section class="lp-section">
        <div class="lp-welcome">
            <div class="lp-welcome__media" data-reveal="left">
                {{-- PLACEHOLDER: lifestyle photo of players on court --}}
                <img src="{{ asset('img/landing/image.png') }}" alt="Jugadores en la cancha" loading="lazy">
            </div>
            <div class="lp-welcome__copy" data-reveal="right">
                <span class="lp-eyebrow">Bienvenido a Voleo</span>
                <h2>Organiza, compite y <span class="lime">disfruta</span>.</h2>
                <p>
                    Ya seas organizador experimentado o estés armando tu primer torneo,
                    Voleo te da todo lo necesario para correrlo sin hojas de cálculo ni
                    mensajes interminables. Desde la inscripción hasta el campeón.
                </p>
                <div class="lp-numbers">
                    <div class="lp-number" data-reveal data-reveal-delay="1">
                        <div class="lp-number__txt"><strong>Genera todo en 1 clic</strong><span>grupos, llaves y calendario</span></div>
                        <span class="lp-number__n">01</span>
                    </div>
                    <div class="lp-number" data-reveal data-reveal-delay="2">
                        <div class="lp-number__txt"><strong>Resultados en vivo</strong><span>standings que se actualizan solos</span></div>
                        <span class="lp-number__n">02</span>
                    </div>
                    <div class="lp-number" data-reveal data-reveal-delay="3">
                        <div class="lp-number__txt"><strong>Página pública por torneo</strong><span>comparte y vende patrocinios</span></div>
                        <span class="lp-number__n">03</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== FEATURES ===== --}}
    <section class="lp-section">
        <div class="lp-section__head" data-reveal>
            <span class="lp-eyebrow">Funciones</span>
            <h2>Todo lo que necesitas para correr un torneo</h2>
            <p>Desde la inscripción hasta el campeón, sin caos ni improvisaciones.</p>
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
                <p>Programa por canchas y horarios, respetando descansos y disponibilidad de cada jugador.</p>
            </div>
            <div class="lp-feature" data-reveal>
                <div class="lp-feature__icon"><i class="fa-solid fa-globe"></i></div>
                <h3>Página pública en vivo</h3>
                <p>Cada torneo tiene su página: standings, calendario y resultados al instante.</p>
            </div>
            <div class="lp-feature" data-reveal data-reveal-delay="1">
                <div class="lp-feature__icon"><i class="fa-brands fa-stripe-s"></i></div>
                <h3>Cobros con Stripe</h3>
                <p>Recibe inscripciones en línea de forma segura, con el detalle de pagos a la mano.</p>
            </div>
            <div class="lp-feature" data-reveal data-reveal-delay="2">
                <div class="lp-feature__icon"><i class="fa-solid fa-share-nodes"></i></div>
                <h3>Comparte por WhatsApp</h3>
                <p>Enlaces y tarjetas de resultados listas para mandar al grupo de cada categoría.</p>
            </div>
        </div>
    </section>

    {{-- ===== THREE AUDIENCES ===== --}}
    <section class="lp-section">
        <div class="lp-section__head" data-reveal>
            <span class="lp-eyebrow">Para todos</span>
            <h2>Una sola plataforma, tres beneficiados</h2>
            <p>Voleo conecta a quienes organizan, a quienes juegan y a quienes patrocinan.</p>
        </div>
        <div class="lp-aud">
            <div class="lp-aud__card lp-aud__card--org" data-reveal>
                <div class="lp-aud__icon"><i class="fa-solid fa-gear"></i></div>
                <h3>Organizadores</h3>
                <p>Crea torneos, arma categorías, genera grupos y llaves, captura resultados y publica todo en minutos.</p>
            </div>
            <div class="lp-aud__card lp-aud__card--ply" data-reveal data-reveal-delay="1">
                <div class="lp-aud__icon"><i class="fa-solid fa-user"></i></div>
                <h3>Jugadores</h3>
                <p>Se inscriben y pagan en línea, encuentran su partido y siguen resultados en tiempo real desde el teléfono.</p>
            </div>
            <div class="lp-aud__card lp-aud__card--spo" data-reveal data-reveal-delay="2">
                <div class="lp-aud__icon"><i class="fa-solid fa-bullhorn"></i></div>
                <h3>Patrocinadores</h3>
                <p>Aparecen en páginas públicas con miles de vistas: un espacio de marca que el organizador puede vender.</p>
            </div>
        </div>
    </section>

    {{-- ===== HOW IT WORKS ===== --}}
    <section class="lp-section--alt">
        <div class="lp-inner" style="padding: clamp(64px,9vw,120px) 20px;">
            <div class="lp-section__head" data-reveal>
                <span class="lp-eyebrow">Cómo funciona</span>
                <h2>De la inscripción al campeón en 3 pasos</h2>
            </div>
            <div class="lp-steps">
                <div class="lp-step" data-reveal><span class="lp-step__n">1</span>
                    <h3>Crea tu torneo</h3>
                    <p>Define categorías, sedes y horarios. Importa parejas desde tu archivo o pégalas directo.</p>
                </div>
                <div class="lp-step" data-reveal data-reveal-delay="1"><span class="lp-step__n">2</span>
                    <h3>Genera y programa</h3>
                    <p>Grupos, llaves y calendario automáticos. Ajusta lo que quieras arrastrando y soltando.</p>
                </div>
                <div class="lp-step" data-reveal data-reveal-delay="2"><span class="lp-step__n">3</span>
                    <h3>Comparte y juega</h3>
                    <p>Publica la página del torneo y captura resultados en vivo. Tus jugadores siguen todo.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== CTA BAND ===== --}}
    <section class="lp-cta">
        <div class="lp-cta__inner" data-reveal="scale">
            <!-- <span class="lp-cta__ghost" aria-hidden="true">voleo.</span> -->

            <!-- <span class="lp-cta__ghost" aria-hidden="true">
                <svg viewBox="0 0 1365 398" xmlns="http://www.w3.org/2000/svg" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;width:auto;">
                    <g transform="matrix(1,0,0,1,-1478.153171,-1510.333408)">
                        <g id="Background" transform="matrix(1.40332,0,0,1.40332,1446,1481)">
                            <g transform="matrix(0.712596,0,0,0.712596,0,0)">
                                <path d="M204.822,101.756C213.777,23.692 298.76,13.785 322.59,48.059C342.872,77.231 326.283,96.73 338.942,95.015C449.786,80.005 533.854,29.531 538.397,30.969C541.508,31.953 547.909,59.975 527.541,86.756C495.306,129.139 372.416,140.564 331.831,146.33C324.063,147.433 325.939,150.417 313.108,174.429C308.862,182.376 192.857,419.103 190.058,421.589C188.172,423.263 101.285,423.239 100.607,422.702C97.449,420.2 61.873,237.427 49.405,207.159C36.409,175.61 32.09,177.615 32.154,174.674C32.272,169.178 74.848,162.046 99.868,180.951C147.475,216.924 136.83,337.954 148.196,362.685C153.071,373.291 161.435,351.583 173.116,327.579C259.229,150.604 262.634,148.07 258.731,147.244C233.049,141.812 207.37,143.214 204.822,101.756ZM261.751,112.171C298.597,107.184 299.702,69.953 282.82,65.111C252.667,56.464 221.138,110.547 261.751,112.171Z" style="fill:#eaf89a;"></path>
                            </g>
                        </g>
                    </g>
                </svg>
            </span> -->
            <h2>¿Listo para tu <span class="lime">próximo torneo</span>?</h2>
            <p>Explora los torneos publicados o entra como organizador y arma el tuyo hoy mismo.</p>
            <div class="lp-cta__actions">
                <a href="{{ route('public.directory') }}" class="lp-btn lp-btn--primary">Ver torneos <span class="lp-btn__ico"><i class="fa-solid fa-arrow-right"></i></span></a>
                <a href="{{ route('public.search') }}" class="lp-btn lp-btn--ghost">Buscar jugador</a>
            </div>
        </div>
    </section>
</div>

<script>
    (function() {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // First-load hero intro: add .is-ready next frame so transitions fire.
        var hero = document.querySelector('[data-hero]');
        if (hero) {
            if (reduce) {
                hero.classList.add('is-ready');
            } else {
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        hero.classList.add('is-ready');
                    });
                });
            }
        }

        // Rotating hero word + synced image cross-fade (Pádel <-> Tennis).
        (function() {
            var wordEls = document.querySelectorAll('[data-word]');
            var imgEls = document.querySelectorAll('[data-img]');
            var count = Math.min(wordEls.length, imgEls.length);
            if (count < 2 || reduce) return; // static first item if reduced motion

            var i = 0;
            var HOLD = 3000; // ~3s visible per word
            var timer = null;

            function show(next) {
                var cur = i;
                // words
                wordEls[cur].classList.remove('is-active');
                wordEls[cur].classList.add('is-leaving');
                wordEls[next].classList.remove('is-leaving');
                wordEls[next].classList.add('is-active');
                // images
                imgEls[cur].classList.remove('is-active');
                imgEls[next].classList.add('is-active');
                // cleanup leaving state after the transition
                (function(leaving) {
                    setTimeout(function() {
                        leaving.classList.remove('is-leaving');
                    }, 800);
                })(wordEls[cur]);
                i = next;
            }

            function tick() {
                show((i + 1) % count);
            }

            function start() {
                if (!timer) timer = setInterval(tick, HOLD);
            }

            function stop() {
                clearInterval(timer);
                timer = null;
            }

            // Wait for the intro wipe to finish before cycling starts.
            setTimeout(start, 1400);

            // Pause when tab hidden to save cycles.
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) stop();
                else start();
            });
        })();

        // Scroll reveal.
        var els = document.querySelectorAll('[data-reveal]');
        if (!('IntersectionObserver' in window) || reduce) {
            els.forEach(function(el) {
                el.classList.add('is-in');
            });
        } else {
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
        }

        // Lightweight parallax on hero balls + image (rAF-throttled).
        if (!reduce) {
            var pxEls = document.querySelectorAll('[data-parallax]');
            if (pxEls.length) {
                var ticking = false;
                var apply = function() {
                    var y = window.scrollY;
                    pxEls.forEach(function(el) {
                        var f = parseFloat(el.dataset.parallax) || 0;
                        el.style.setProperty('--lp-parallax', (y * f) + 'px');
                        if (!el.classList.contains('lp-stage__img')) {
                            el.style.transform = 'translateY(' + (y * f) + 'px)';
                        }
                    });
                    ticking = false;
                };
                window.addEventListener('scroll', function() {
                    if (!ticking) {
                        window.requestAnimationFrame(apply);
                        ticking = true;
                    }
                }, {
                    passive: true
                });
            }
        }
    })();
</script>
@endsection