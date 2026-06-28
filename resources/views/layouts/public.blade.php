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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>
    <div class="public-shell">
        <header class="public-topbar">
            <a href="/" class="public-brand">
                <span class="logo"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
                PadelCup
            </a>
            <nav class="public-nav">
                <a href="{{ route('public.directory') }}" class="public-nav__link">Torneos</a>
                <a href="{{ route('public.search') }}" class="public-nav__link">Buscar</a>
                <button class="icon-btn" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </nav>
        </header>

        <main class="public-main">
            @yield('content')
        </main>

        <footer class="public-footer">
            <a href="{{ route('public.directory') }}" style="color:inherit;text-decoration:none;">PadelCup · Torneos de pádel</a>
            <div class="public-footer__links">
                <a href="{{ route('public.directory') }}">Torneos</a>
                <a href="{{ route('public.search') }}">Buscar</a>
                <a href="{{ route('legal.terminos') }}">Términos</a>
                <a href="{{ route('legal.privacidad') }}">Privacidad</a>
                <a href="{{ route('legal.aviso') }}">Aviso de Privacidad</a>
                <a href="{{ route('legal.reembolsos') }}">Reembolsos</a>
            </div>
        </footer>
    </div>
</body>

</html>