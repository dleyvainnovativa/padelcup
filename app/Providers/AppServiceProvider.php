<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 markup for all paginators (the app is Bootstrap-based;
        // Laravel's default paginator view is Tailwind, which renders unstyled).
        Paginator::useBootstrapFive();

        // Prefer the themed Voleo paginator for every ->links() call. Comment
        // this out if you want plain Bootstrap 5 styling instead.
        Paginator::defaultView('vendor.pagination.voleo');
    }
}
