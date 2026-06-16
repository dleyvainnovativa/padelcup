{{-- resources/views/layouts/public.blade.php
     Player-facing shell: no sidebar, no admin nav. Used for self-service
     payment confirmation, quick-register, and (Phase 8) public tournament
     pages. data-theme is server-rendered from the cookie. --}}
<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PadelCup') · PadelCup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        <button class="icon-btn" data-theme-toggle aria-label="Cambiar tema" title="Cambiar tema">
            <i class="fa-solid fa-moon"></i>
        </button>
    </header>

    <main class="public-main">
        @yield('content')
    </main>

    <footer class="public-footer">
        <a href="{{ route('public.directory') }}" style="color:inherit;text-decoration:none;">PadelCup · Torneos de pádel</a>
    </footer>
</div>
</body>
</html>
