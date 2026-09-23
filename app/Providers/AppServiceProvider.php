<?php

namespace App\Providers;

use App\Models\Branch;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use JeroenNoten\LaravelAdminLte\Events\BuildingMenu;

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
        Paginator::useBootstrapFour();

        // Super-admin full access: Owner role or admin@urbanpos.com bypasses all permission and gate checks
        Gate::before(function ($user, string $ability) {
            return ($user->hasRole('Owner') || $user->email === 'admin@urbanpos.com') ? true : null;
        });

        // Ensure URLs, assets and routes use HTTPS when served over HTTPS or behind an SSL reverse proxy
        if (request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || request()->isSecure() || str_starts_with(config('app.url'), 'https://') || app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}

