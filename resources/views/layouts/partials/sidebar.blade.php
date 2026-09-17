{{-- resources/views/layouts/partials/sidebar.blade.php
     Role-aware, context-aware sidebar.

     Roles:
       • player  → only "Mi perfil" (their own area). No manager nav.
       • manager → global manager nav + tournament-context nav.
       • admin   → everything a manager sees + an "Administración" section.

     Contexts (manager/admin only):
       • GLOBAL      → top-level nav (panel, torneos, operación, config).
       • TOURNAMENT  → inside /tournaments/{tournament}/… swaps to that
                       tournament's working nav, resolved to THIS tournament.
--}}
@php
$isActive = fn (string $pattern) => request()->routeIs($pattern) ? 'active' : '';
$to = fn (string $name, $params = []) => \Route::has($name) ? route($name, $params) : '#';

$user = auth()->user();
$isAdmin = $user && $user->isAdmin();
$isManager = $user && $user->isManager();
$isPlayer = $user && $user->isPlayer();
$isStaff = $isAdmin || $isManager; // sees the manager working area

// Tournament context only matters for staff.
$ctxTournament = request()->route('tournament');
if (is_string($ctxTournament)) {
$ctxTournament = \App\Models\Tournament::where('slug', $ctxTournament)->first();
}
$inTournament = $isStaff && $ctxTournament instanceof \App\Models\Tournament;
@endphp

<aside class="sidebar" id="appSidebar">
    <a href="{{ $isStaff ? $to('dashboard') : $to('player.dashboard') }}" class="sidebar-brand">
        <x-logo :height="26" />
    </a>

    @if($isPlayer)
    {{-- ===================== PLAYER ===================== --}}
    <a href="{{ $to('player.dashboard') }}" class="nav-item {{ $isActive('player.dashboard') }}">
        <i class="fa-solid fa-gauge-high"></i> Mi perfil
    </a>
    <a href="{{ $to('player.claims') }}" class="nav-item {{ $isActive('player.claims') }}">
        <i class="fa-solid fa-clipboard-check"></i> Mis solicitudes
    </a>
    <a href="{{ $to('player.claim.create') }}" class="nav-item {{ $isActive('player.claim.*') }}">
        <i class="fa-solid fa-user-plus"></i> Reclamar perfil
    </a>
    <a href="{{ $to('player.stats') }}" class="nav-item {{ $isActive('player.stats') }}">
        <i class="fa-solid fa-chart-line"></i> Estadísticas
    </a>

    <div class="nav-label">Explorar</div>
    <a href="{{ $to('public.directory') }}" class="nav-item" target="_blank" rel="noopener">
        <i class="fa-solid fa-magnifying-glass"></i> Ver torneos
    </a>

    @elseif($inTournament)
    {{-- ============== STAFF · TOURNAMENT CONTEXT ============== --}}
    <a href="{{ $to('tournaments.index') }}" class="nav-item nav-back">
        <i class="fa-solid fa-arrow-left"></i> Todos los torneos
    </a>

    <div class="nav-tournament" title="{{ $ctxTournament->name }}">
        <i class="fa-solid fa-trophy"></i>
        <span>{{ \Illuminate\Support\Str::limit($ctxTournament->name, 22) }}</span>
    </div>

    <a href="{{ $to('tournaments.show', $ctxTournament) }}" class="nav-item {{ $isActive('tournaments.show') }}">
        <i class="fa-solid fa-circle-info"></i> Resumen
    </a>
    <a href="{{ $to('categories.create', $ctxTournament) }}" class="nav-item {{ $isActive('categories.*') }}">
        <i class="fa-solid fa-layer-group"></i> Categorías
    </a>

    <div class="nav-label">Operación</div>
    <a href="{{ $to('schedule.index', $ctxTournament) }}" class="nav-item {{ $isActive('schedule.*') }}">
        <i class="fa-solid fa-calendar-days"></i> Calendario
    </a>
    <a href="{{ $to('venues.index', $ctxTournament) }}" class="nav-item {{ $isActive('venues.*') }}">
        <i class="fa-solid fa-location-dot"></i> Sedes y canchas
    </a>
    <a href="{{ $to('tournament.players', $ctxTournament) }}" class="nav-item {{ $isActive('tournament.players') }}">
        <i class="fa-solid fa-users"></i> Jugadores
    </a>
    <a href="{{ $to('tournaments.summary', $ctxTournament) }}" class="nav-item {{ $isActive('tournaments.summary') }}">
        <i class="fa-solid fa-chart-simple"></i> Resumen general
    </a>
    <a href="{{ $to('availability.player.index', $ctxTournament) }}" class="nav-item {{ $isActive('availability.player.*') }}">
        <i class="fa-solid fa-user-clock"></i> Disponibilidad
    </a>

    <div class="nav-label">Promoción</div>
    <a href="{{ $to('sponsors.index', $ctxTournament) }}" class="nav-item {{ $isActive('sponsors.*') }}">
        <i class="fa-solid fa-handshake"></i> Patrocinadores
    </a>
    <a href="{{ $to('tournaments.import.form', $ctxTournament) }}" class="nav-item {{ $isActive('tournaments.import.*') }}">
        <i class="fa-solid fa-file-import"></i> Importar
    </a>

    <div class="nav-label">Vista pública</div>
    <a href="{{ $to('public.tournament', $ctxTournament) }}" class="nav-item" target="_blank" rel="noopener">
        <i class="fa-solid fa-up-right-from-square"></i> Ver página pública
    </a>

    @elseif($isStaff)
    {{-- ============== STAFF · GLOBAL CONTEXT ============== --}}
    <a href="{{ $to('dashboard') }}" class="nav-item {{ $isActive('dashboard') }}">
        <i class="fa-solid fa-gauge-high"></i> Panel
    </a>
    <a href="{{ $to('tournaments.index') }}" class="nav-item {{ $isActive('tournaments.*') }}">
        <i class="fa-solid fa-trophy"></i> Torneos
    </a>

    <div class="nav-label">Operación</div>
    <a href="{{ $to('ranking-systems.index') }}" class="nav-item {{ $isActive('ranking-systems.*') }}">
        <i class="fa-solid fa-ranking-star"></i> Rankings
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

    @if($isAdmin)
    @php $pendingClaims = \Illuminate\Support\Facades\Cache::remember('admin.pending_claims_count', 60, fn() => \App\Models\PlayerClaim::where('status','pending')->count()); @endphp
    <div class="nav-label">Administración</div>
    <a href="{{ $to('admin.managers.index') }}" class="nav-item {{ $isActive('admin.managers.*') }}">
        <i class="fa-solid fa-user-shield"></i> Managers
    </a>
    <a href="{{ $to('admin.ads.index') }}" class="nav-item {{ $isActive('admin.ads.*') }}">
        <i class="fa-solid fa-rectangle-ad"></i> Anuncios
    </a>
    <a href="{{ $to('admin.sponsors.index') }}" class="nav-item {{ $isActive('admin.sponsors.*') }}">
        <i class="fa-solid fa-handshake-angle"></i> Patrocinadores
    </a>
    <a href="{{ $to('admin.claims.index') }}" class="nav-item {{ $isActive('admin.claims.*') }}">
        <i class="fa-solid fa-user-check"></i> Reclamos
        @if($pendingClaims > 0)
        <span class="sidebar-badge">{{ $pendingClaims }}</span>
        @endif
    </a>
    @endif
    @endif

    <div class="mt-auto pt-3">
        <form method="POST" action="{{ $to('logout') }}">
            @csrf
            <button type="submit" class="nav-item w-100 text-start border-0 bg-transparent" style="cursor:pointer;">
                <i class="fa-solid fa-arrow-right-from-bracket"></i> Cerrar sesión
            </button>
        </form>
    </div>
</aside>