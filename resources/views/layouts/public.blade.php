{{-- resources/views/layouts/public.blade.php
     Player-facing shell: no sidebar, no admin nav. Used for the landing page,
     public tournament pages, search, legal pages, self-service payment, and
     quick-register. data-theme is server-rendered from the cookie. --}}
<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PadelCup') · PadelCup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="{{asset('img/icons/favicon-96x96.png')}}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{asset('img/icons/favicon.svg')}}" />
    <link rel="shortcut icon" href="{{asset('img/icons/favicon.ico')}}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{asset('img/icons/apple-touch-icon.png')}}" />
    <meta name="apple-mobile-web-app-title" content="PadelCup" />
    <link rel="manifest" href="{{asset('img/icons/site.webmanifest')}}" />
    @vite(['resources/css/app.css','resources/css/landing.css', 'resources/js/app.js'])
    {{-- Shared public/landing styles (footer + any page that opts in via @push reuses the same file) --}}
    @stack('head')
</head>

<body>
    <div class="public-shell">
        <header class="ph" data-ph>
            <div class="ph__inner">
                <a href="/" class="ph__brand">
                    <span class="ph__logo"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
                    PadelCup
                </a>

                {{-- Desktop nav --}}
                <nav class="ph__nav">
                    <a href="{{ route('public.directory') }}" class="ph__link">Torneos</a>
                    <a href="{{ route('public.search') }}" class="ph__link">Buscar</a>
                    <button class="ph__theme icon-btn" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <a href="{{ route('dashboard') }}" class="ph__cta">Soy organizador</a>
                </nav>

                {{-- Mobile hamburger --}}
                <button class="ph__burger" data-ph-open aria-label="Abrir menú" aria-expanded="false">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </header>

        {{-- Offcanvas (mobile) --}}
        <div class="ph-oc" data-ph-oc aria-hidden="true">
            <div class="ph-oc__backdrop" data-ph-close></div>
            <aside class="ph-oc__panel" role="dialog" aria-modal="true" aria-label="Menú">
                <div class="ph-oc__head">
                    <a href="/" class="ph__brand">
                        <span class="ph__logo"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
                        PadelCup
                    </a>
                    <button class="ph-oc__close" data-ph-close aria-label="Cerrar menú"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <nav class="ph-oc__nav">
                    <a href="{{ route('public.directory') }}" class="ph-oc__link"><i class="fa-solid fa-trophy"></i> Torneos</a>
                    <a href="{{ route('public.search') }}" class="ph-oc__link"><i class="fa-solid fa-magnifying-glass"></i> Buscar</a>
                    <button class="ph-oc__link ph-oc__link--btn" data-theme-toggle>
                        <i class="fa-solid fa-moon"></i> Cambiar tema
                    </button>
                </nav>
                <a href="{{ route('dashboard') }}" class="ph-oc__cta">Soy organizador</a>
            </aside>
        </div>

        <main class="public-main">
            @yield('content')
        </main>

        <footer class="pf">
            <div class="pf__inner">
                <div class="pf__brand-col">
                    <a href="/" class="pf__brand">
                        <span class="pf__logo"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
                        PadelCup
                    </a>
                    <p class="pf__tagline">Organiza torneos de pádel sin el caos: inscripciones, llaves, calendario y resultados en vivo.</p>
                </div>
                <div class="pf__col">
                    <h4>Plataforma</h4>
                    <a href="{{ route('public.directory') }}">Torneos</a>
                    <a href="{{ route('public.search') }}">Buscar</a>
                    <a href="{{ route('dashboard') }}">Soy organizador</a>
                </div>
                <div class="pf__col">
                    <h4>Legal</h4>
                    <a href="{{ route('legal.terminos') }}">Términos</a>
                    <a href="{{ route('legal.privacidad') }}">Privacidad</a>
                    <a href="{{ route('legal.aviso') }}">Aviso de Privacidad</a>
                    <a href="{{ route('legal.reembolsos') }}">Reembolsos</a>
                </div>
                <div class="pf__col">
                    <h4>Contacto</h4>
                    <a href="mailto:contacto@padelcup.mx">contacto@padelcup.mx</a>
                </div>
            </div>
            <div class="pf__bottom">
                <span class="pf__copy">© {{ date('Y') }} PadelCup. Todos los derechos reservados.</span>
                <span class="pf__made">Hecho con <i class="fa-solid fa-heart"></i> para el pádel mexicano</span>
            </div>
        </footer>
    </div>
    <script>
        (function() {
            var oc = document.querySelector('[data-ph-oc]');
            var openBtn = document.querySelector('[data-ph-open]');
            if (!oc || !openBtn) return;
            var closeEls = oc.querySelectorAll('[data-ph-close]');

            function open() {
                oc.classList.add('is-open');
                oc.setAttribute('aria-hidden', 'false');
                openBtn.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function close() {
                oc.classList.remove('is-open');
                oc.setAttribute('aria-hidden', 'true');
                openBtn.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            openBtn.addEventListener('click', open);
            closeEls.forEach(function(el) {
                el.addEventListener('click', close);
            });
            // Close when a nav link is tapped (but not the theme toggle).
            oc.querySelectorAll('a.ph-oc__link, .ph-oc__cta').forEach(function(a) {
                a.addEventListener('click', close);
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && oc.classList.contains('is-open')) close();
            });

            // Sticky header: add a class once scrolled, for the blur/shadow.
            var header = document.querySelector('[data-ph]');
            if (header) {
                var onScroll = function() {
                    header.classList.toggle('is-scrolled', window.scrollY > 8);
                };
                onScroll();
                window.addEventListener('scroll', onScroll, {
                    passive: true
                });
            }
        })();
    </script>
</body>

</html>