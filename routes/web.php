<?php

use App\Http\Controllers\Admin\ManagerController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\PairController;
use App\Http\Controllers\Dashboard\PlayerImportController;
use App\Http\Controllers\Dashboard\TournamentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// --- Social login (Socialite) ---
Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

// --- Authenticated app ---
Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [\App\Http\Controllers\Dashboard\DashboardController::class, 'index'])->name('dashboard');

    // Tournaments (manager CRUD)
    Route::resource('tournaments', TournamentController::class);

    // Categories (nested under tournament)
    Route::prefix('tournaments/{tournament}')->group(function () {
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Pairs (nested under category)
        Route::prefix('categories/{category}')->group(function () {
            Route::post('pairs', [PairController::class, 'store'])->name('pairs.store');
            Route::patch('pairs/{pair}/payment', [PairController::class, 'setPayment'])->name('pairs.payment');
            Route::delete('pairs/{pair}', [PairController::class, 'destroy'])->name('pairs.destroy');

            // CSV import
            Route::get('import', [PlayerImportController::class, 'form'])->name('pairs.import.form');
            Route::post('import/preview', [PlayerImportController::class, 'preview'])->name('pairs.import.preview');
            Route::post('import/commit', [PlayerImportController::class, 'commit'])->name('pairs.import.commit');

            // Draw: groups + bracket (Phase 5 engines)
            Route::get('grupos/preview', [\App\Http\Controllers\Dashboard\DrawController::class, 'previewGroups'])->name('draw.groups.preview');
            Route::post('grupos/generar', [\App\Http\Controllers\Dashboard\DrawController::class, 'generateGroups'])->name('draw.groups.generate');
            Route::get('grupos', [\App\Http\Controllers\Dashboard\DrawController::class, 'groups'])->name('draw.groups');
            Route::post('grupos/mover', [\App\Http\Controllers\Dashboard\DrawController::class, 'movePair'])->name('draw.groups.move');
            Route::post('llave/generar', [\App\Http\Controllers\Dashboard\DrawController::class, 'buildBracket'])->name('draw.bracket.build');
            Route::post('llave/intercambiar', [\App\Http\Controllers\Dashboard\DrawController::class, 'swapBracket'])->name('draw.bracket.swap');
            Route::get('llave', [\App\Http\Controllers\Dashboard\DrawController::class, 'bracket'])->name('draw.bracket');

            // Results (Phase 6)
            Route::get('resultados', [\App\Http\Controllers\Dashboard\ResultController::class, 'index'])->name('results.index');
            Route::post('partidos/{match}/confirmar', [\App\Http\Controllers\Dashboard\ResultController::class, 'confirm'])->name('results.confirm');
            Route::post('partidos/{match}/editar', [\App\Http\Controllers\Dashboard\ResultController::class, 'edit'])->name('results.edit');
            Route::post('partidos/{match}/especial', [\App\Http\Controllers\Dashboard\ResultController::class, 'special'])->name('results.special');
        });

        // Venues / courts / availability (Phase 7) — tournament-level
        Route::get('sedes', [\App\Http\Controllers\Dashboard\VenueController::class, 'index'])->name('venues.index');
        Route::post('sedes', [\App\Http\Controllers\Dashboard\VenueController::class, 'storeVenue'])->name('venues.store');
        Route::post('sedes/{venue}/canchas', [\App\Http\Controllers\Dashboard\VenueController::class, 'storeCourt'])->name('courts.store');
        Route::post('sedes/{venue}/canchas/generar', [\App\Http\Controllers\Dashboard\VenueController::class, 'generateCourts'])->name('courts.generate');
        Route::delete('canchas/{court}', [\App\Http\Controllers\Dashboard\VenueController::class, 'destroyCourt'])->name('courts.destroy');
        Route::patch('canchas/{court}', [\App\Http\Controllers\Dashboard\VenueController::class, 'updateCourt'])->name('courts.update');



        Route::post('horarios/resync', [\App\Http\Controllers\Dashboard\VenueController::class, 'resyncAvailability'])->name('availability.resync');
        Route::post('canchas/{court}/horarios', [\App\Http\Controllers\Dashboard\VenueController::class, 'storeAvailability'])->name('availability.store');
        Route::delete('horarios/{availability}', [\App\Http\Controllers\Dashboard\VenueController::class, 'destroyAvailability'])->name('availability.destroy');

        // Schedule / calendar (Phase 7)
        Route::get('calendario', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('calendario/auto', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'auto'])->name('schedule.auto');
        Route::post('calendario/colocar', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'place'])->name('schedule.place');
        Route::post('calendario/quitar', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'unplace'])->name('schedule.unplace');
        Route::post('calendario/limpiar', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'clearAll'])->name('schedule.clear');
        Route::post('calendario/conflictos', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'conflicts'])->name('schedule.conflicts');
        Route::get('calendario/pdf', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'exportPdf'])->name('schedule.pdf');
        Route::post('calendario/fases', [\App\Http\Controllers\Dashboard\ScheduleController::class, 'savePhaseWindows'])->name('schedule.phases');

        // Resumen (tournament summary / leaderboard)
        Route::get('resumen', [\App\Http\Controllers\Dashboard\SummaryController::class, 'show'])->name('tournaments.summary');

        // Sponsors / partners carousel (manager-managed)
        Route::get('patrocinadores', [\App\Http\Controllers\Dashboard\SponsorController::class, 'index'])->name('sponsors.index');
        Route::post('patrocinadores', [\App\Http\Controllers\Dashboard\SponsorController::class, 'store'])->name('sponsors.store');
        Route::post('patrocinadores/{sponsor}', [\App\Http\Controllers\Dashboard\SponsorController::class, 'update'])->name('sponsors.update');
        Route::delete('patrocinadores/{sponsor}', [\App\Http\Controllers\Dashboard\SponsorController::class, 'destroy'])->name('sponsors.destroy');

        // Bulk import (CSV/XLSX/paste) — create categories + players/pairs
        Route::get('importar', [\App\Http\Controllers\Dashboard\TournamentImportController::class, 'form'])->name('tournaments.import.form');
        Route::post('importar/previsualizar', [\App\Http\Controllers\Dashboard\TournamentImportController::class, 'preview'])->name('tournaments.import.preview');
        Route::post('importar/confirmar', [\App\Http\Controllers\Dashboard\TournamentImportController::class, 'commit'])->name('tournaments.import.commit');

        // Player availability (manager-entered, per player per tournament)
        Route::get('disponibilidad', [\App\Http\Controllers\Dashboard\PlayerAvailabilityController::class, 'index'])->name('availability.player.index');
        Route::post('disponibilidad', [\App\Http\Controllers\Dashboard\PlayerAvailabilityController::class, 'store'])->name('availability.player.store');
    });

    // Stripe Connect onboarding (managers)
    Route::get('/connect', [\App\Http\Controllers\Dashboard\ConnectController::class, 'index'])->name('connect.index');
    Route::post('/connect/start', [\App\Http\Controllers\Dashboard\ConnectController::class, 'start'])->name('connect.start');
    Route::get('/connect/return', [\App\Http\Controllers\Dashboard\ConnectController::class, 'return'])->name('connect.return');
    Route::get('/connect/refresh', [\App\Http\Controllers\Dashboard\ConnectController::class, 'refresh'])->name('connect.refresh');

    // Payments
    Route::get('/payments', [\App\Http\Controllers\Dashboard\PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/refund', [\App\Http\Controllers\Dashboard\PaymentController::class, 'refund'])->name('payments.refund');

    // Issues queue (stuck self-registrations)
    Route::get('/issues', [\App\Http\Controllers\Dashboard\IssueController::class, 'index'])->name('issues.index');
    Route::post('/issues/{registration}/resolve', [\App\Http\Controllers\Dashboard\IssueController::class, 'resolve'])->name('issues.resolve');

    // Self-registration (player registers themselves into a category)
    Route::get('/inscribir/{category}', [\App\Http\Controllers\Registration\SelfRegistrationController::class, 'create'])->name('registration.create');
    Route::post('/inscribir/{category}', [\App\Http\Controllers\Registration\SelfRegistrationController::class, 'store'])->name('registration.store');
    Route::get('/inscripcion/{registration}/pago', [\App\Http\Controllers\Registration\SelfRegistrationController::class, 'pay'])->name('registration.pay');
    Route::get('/inscripcion/{registration}/estado', [\App\Http\Controllers\Registration\SelfRegistrationController::class, 'confirmation'])->name('registration.confirmation');

    // Sidebar stubs still pending real controllers (Phase 5-8)
    Route::view('/brackets', 'dashboard.index')->name('brackets.index');
    Route::view('/settings', 'dashboard.index')->name('settings.index');

    // Aliases used by the sidebar links
    Route::get('/categories', fn() => redirect()->route('tournaments.index'))->name('categories.index');
    Route::get('/pairs', fn() => redirect()->route('tournaments.index'))->name('pairs.index');
});

// --- Admin-only ---
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/managers', [ManagerController::class, 'index'])->name('managers.index');
    Route::get('/managers/create', [ManagerController::class, 'create'])->name('managers.create');
    Route::post('/managers', [ManagerController::class, 'store'])->name('managers.store');

    // Platform ads (16:9 carousel on public pages)
    Route::get('/anuncios', [AdController::class, 'index'])->name('ads.index');
    Route::post('/anuncios', [AdController::class, 'store'])->name('ads.store');
    Route::post('/anuncios/{ad}', [AdController::class, 'update'])->name('ads.update');
    Route::delete('/anuncios/{ad}', [AdController::class, 'destroy'])->name('ads.destroy');

    // Platform sponsors (admin: global or per-tournament)
    Route::get('/patrocinadores', [\App\Http\Controllers\Admin\SponsorController::class, 'index'])->name('sponsors.index');
    Route::post('/patrocinadores', [\App\Http\Controllers\Admin\SponsorController::class, 'store'])->name('sponsors.store');
    Route::post('/patrocinadores/{sponsor}', [\App\Http\Controllers\Admin\SponsorController::class, 'update'])->name('sponsors.update');
    Route::delete('/patrocinadores/{sponsor}', [\App\Http\Controllers\Admin\SponsorController::class, 'destroy'])->name('sponsors.destroy');
});

Route::get('/', fn() => redirect()->route('dashboard'));

// Public, read-only tournament pages (Phase 8) — no auth.
Route::get('/torneos', [\App\Http\Controllers\PublicTournamentController::class, 'directory'])->name('public.directory');
Route::get('/anuncio/{ad}/clic', [\App\Http\Controllers\Admin\AdController::class, 'click'])->name('ads.click');
Route::get('/t/{tournament}', [\App\Http\Controllers\PublicTournamentController::class, 'show'])->name('public.tournament');
Route::get('/t/{tournament}/calendario', [\App\Http\Controllers\PublicTournamentController::class, 'schedule'])->name('public.schedule');
Route::get('/t/{tournament}/jugador/{player}', [\App\Http\Controllers\PublicTournamentController::class, 'player'])->name('public.player');
Route::get('/t/{tournament}/{category:slug}', [\App\Http\Controllers\PublicTournamentController::class, 'category'])
    ->scopeBindings()
    ->name('public.category');

// Quick-register (partner accepts invitation via token — PUBLIC, no auth)
Route::get('/invitacion/{invitation}', [\App\Http\Controllers\Registration\QuickRegistrationController::class, 'show'])->name('quick.show');
Route::post('/invitacion/{invitation}', [\App\Http\Controllers\Registration\QuickRegistrationController::class, 'store'])->name('quick.store');

// Webhooks (CSRF-exempt; see routes/webhooks.php for bootstrap notes)
require __DIR__ . '/webhooks.php';
