{{-- resources/views/layouts/guest.blade.php
     Split-screen auth shell.
       - Desktop: left brand panel (forest green + lime dots), right form column.
       - Mobile: brand panel hides; a slim logo header + full-width form.
     Pages provide @section('content') for the right column, and may set
     @section('brand-tagline') to override the panel headline. --}}
<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acceder') · Voleo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/png" href="{{asset('img/icons/favicon-96x96.png')}}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{asset('img/icons/favicon.svg')}}" />
    <link rel="shortcut icon" href="{{asset('img/icons/favicon.ico')}}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{asset('img/icons/apple-touch-icon.png')}}" />
    <meta name="apple-mobile-web-app-title" content="Voleo" />
    <link rel="manifest" href="{{asset('img/icons/site.webmanifest')}}" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>
    {{-- Theme toggle, top-right. app.js's initTheme() wires [data-theme-toggle]. --}}
    <button class="icon-btn auth-theme" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
        <i class="fa-solid {{ request()->cookie('tc_theme', 'light') === 'dark' ? 'fa-sun' : 'fa-moon' }}"></i>
    </button>

    <div class="auth-split">
        {{-- Brand panel (desktop only) --}}
        <aside class="auth-brand-panel" aria-hidden="true">
            <span class="auth-dot auth-dot--1"></span>
            <span class="auth-dot auth-dot--2"></span>
            <span class="auth-dot auth-dot--3"></span>
            <span class="auth-dot auth-dot--4"></span>

            <div class="auth-brand-panel__top">
                <a href="/" class="auth-brand-panel__logo">
                    <x-logo :height="34" />
                </a>
            </div>

            <div class="auth-brand-panel__body">
                <h2 class="auth-brand-panel__headline">
                    @yield('brand-tagline', 'Tu torneo, punto a punto.')
                </h2>
                <p class="auth-brand-panel__sub">
                    Organiza, compite y disfruta. La plataforma para conectar
                    jugadores, encuentros y resultados.
                </p>
            </div>

            <div class="auth-brand-panel__foot">
                voleo.app &copy; {{ date('Y') }}
            </div>
        </aside>

        {{-- Form column --}}
        <main class="auth-form-col">
            <div class="auth-card">
                {{-- Mobile logo header (hidden on desktop; panel carries it there) --}}
                <a href="/" class="auth-brand auth-brand--mobile">
                    <x-logo :height="30" />
                </a>
                @yield('content')
            </div>
        </main>
    </div>
</body>

</html>