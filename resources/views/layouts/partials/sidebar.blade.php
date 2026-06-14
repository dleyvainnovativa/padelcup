{{-- resources/views/layouts/partials/sidebar.blade.php
     Sidebar nav. Uses request()->routeIs() to highlight the active item.
     Routes referenced here are placeholders for later phases; using
     Route::has() guards so the layout renders before routes exist. --}}
@php
// Helper to mark a nav item active by route name pattern.
$isActive = fn (string $pattern) => request()->routeIs($pattern) ? 'active' : '';
// Safe URL helper: returns '#' until the named route exists.
$to = fn (string $name) => \Route::has($name) ? route($name) : '#';
@endphp

<aside class="sidebar" id="appSidebar">
    <a href="{{ $to('dashboard') }}" class="sidebar-brand">
        <span class="logo"><i class="fa-solid fa-table-tennis-paddle-ball"></i></span>
        PadelCup
    </a>

    <a href="{{ $to('dashboard') }}" class="nav-item {{ $isActive('dashboard') }}">
        <i class="fa-solid fa-gauge-high"></i> Panel
    </a>
    <a href="{{ $to('tournaments.index') }}" class="nav-item {{ $isActive('tournaments.*') }}">
        <i class="fa-solid fa-trophy"></i> Torneos
    </a>
    <a href="{{ $to('categories.index') }}" class="nav-item {{ $isActive('categories.*') }}">
        <i class="fa-solid fa-layer-group"></i> Categorías
    </a>
    <a href="{{ $to('pairs.index') }}" class="nav-item {{ $isActive('pairs.*') }}">
        <i class="fa-solid fa-people-group"></i> Parejas
    </a>

    <div class="nav-label">Operación</div>
    <a href="{{ $to('tournaments.index') }}" class="nav-item {{ $isActive('schedule.*') }}">
        <i class="fa-solid fa-calendar-days"></i> Calendario
    </a>
    <a href="{{ $to('brackets.index') }}" class="nav-item {{ $isActive('brackets.*') }}">
        <i class="fa-solid fa-sitemap"></i> Llaves
    </a>
    <a href="{{ $to('tournaments.index') }}" class="nav-item {{ $isActive('results.*') }}">
        <i class="fa-solid fa-flag-checkered"></i> Resultados
    </a>
    <a href="{{ $to('payments.index') }}" class="nav-item {{ $isActive('payments.*') }}">
        <i class="fa-solid fa-credit-card"></i> Pagos
    </a>
    <a href="{{ $to('issues.index') }}" class="nav-item {{ $isActive('issues.*') }}">
        <i class="fa-solid fa-circle-exclamation"></i> Casos
    </a>

    <div class="nav-label">Configuración</div>
    <a href="{{ $to('connect.index') }}" class="nav-item {{ $isActive('connect.*') }}">
        <i class="fa-brands fa-stripe-s"></i> Cobros
    </a>
    <a href="{{ $to('tournaments.index') }}" class="nav-item {{ $isActive('venues.*') }}">
        <i class="fa-solid fa-location-dot"></i> Sedes y canchas
    </a>
    <a href="{{ $to('settings.index') }}" class="nav-item {{ $isActive('settings.*') }}">
        <i class="fa-solid fa-gear"></i> Ajustes
    </a>

    <div class="mt-auto pt-3">
        <form method="POST" action="{{ $to('logout') }}">
            @csrf
            <button type="submit" class="nav-item w-100 text-start border-0 bg-transparent" style="cursor:pointer;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión
            </button>
        </form>
    </div>
</aside>