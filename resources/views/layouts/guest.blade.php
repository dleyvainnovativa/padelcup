{{-- resources/views/layouts/guest.blade.php
     Minimal centered shell for auth screens and the quick-register link. --}}
<!DOCTYPE html>
<html lang="es-MX" data-theme="{{ request()->cookie('tc_theme', 'light') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Acceder') · PadelCup</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="sidebar-brand">
                <span class="logo"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
                PadelCup
            </div>
            @yield('content')
        </div>
    </div>
</body>

</html>