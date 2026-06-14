{{-- resources/views/layouts/app.blade.php
     Dashboard shell: sidebar + topbar + content slot.
     The data-theme attribute is server-rendered from the tc_theme cookie
     so there's no flash of the wrong theme on load. --}}
<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · PadelCup</title>
    <link rel="icon" type="image/png" href="{{asset('img/icons/favicon-96x96.png')}}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{asset('img/icons/favicon.svg')}}" />
    <link rel="shortcut icon" href="{{asset('img/icons/favicon.ico')}}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{asset('img/icons/apple-touch-icon.png')}}" />
    <meta name="apple-mobile-web-app-title" content="PadelCup" />
    <link rel="manifest" href="{{asset('img/icons/site.webmanifest')}}" />

    {{-- Font Awesome (CDN for now; can be self-hosted later) --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>
    <div class="app-shell">

        {{-- Sidebar --}}
        @include('layouts.partials.sidebar')
        <div class="sidebar-backdrop"></div>

        {{-- Main column --}}
        <div class="app-main">

            {{-- Topbar --}}
            <header class="topbar">
                <div class="d-flex align-items-center gap-2" style="flex:1;">
                    <button class="icon-btn menu-toggle" data-sidebar-toggle aria-label="Menú">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="topbar-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" placeholder="Buscar torneos, parejas, jugadores…" aria-label="Buscar">
                    </div>
                </div>

                <div class="topbar-actions">
                    <button class="icon-btn" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <button class="icon-btn" aria-label="Notificaciones">
                        <i class="fa-regular fa-bell"></i>
                    </button>
                    <div class="avatar" title="{{ auth()->user()?->name ?? 'Invitado' }}">
                        {{ \Illuminate\Support\Str::of(auth()->user()?->name ?? 'IN')->explode(' ')->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                    </div>
                </div>
            </header>

            {{-- Content --}}
            <main class="app-content">
                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>