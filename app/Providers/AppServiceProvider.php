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

        // Super-admin full access: Owner/Admin role, User ID 1, or any admin/owner email/name bypasses all permission and gate checks
        Gate::before(function ($user, string $ability) {
            if ($user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator', 'Manager'])
                || (int)$user->id === 1
                || str_contains(strtolower($user->email ?? ''), 'admin')
                || str_contains(strtolower($user->name ?? ''), 'admin')
                || str_contains(strtolower($user->email ?? ''), 'owner')) {
                return true;
            }
            return null;
        });

        // Ensure URLs, assets and routes use HTTPS when served over HTTPS or behind an SSL reverse proxy
        if (request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || request()->isSecure() || str_starts_with(config('app.url'), 'https://') || app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}

