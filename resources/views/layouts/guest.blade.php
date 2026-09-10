{{-- resources/views/layouts/guest.blade.php
     Minimal centered shell for auth screens and the quick-register link. --}}
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
    {{-- Theme toggle, top-right. app.js's initTheme() wires [data-theme-toggle]
         automatically, so no inline script is needed here. --}}
    <button class="icon-btn auth-theme" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
        <i class="fa-solid {{ request()->cookie('tc_theme', 'light') === 'dark' ? 'fa-sun' : 'fa-moon' }}"></i>
    </button>

    <div class="auth-wrap">
        <div class="auth-card">
            <a href="/" class="auth-brand">
                <x-logo :height="30" />
            </a>
            @yield('content')
        </div>
    </div>
</body>

</html>
