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
        date_default_timezone_set('Asia/Kolkata');
        Paginator::useBootstrapFour();

        // Super-admin bypass is limited to explicit super-user roles (and the seeded user id 1).
        // Manager is deliberately NOT here: it must go through the seeded permission set so
        // Owner-only actions (users, financial years, item price edits) stay blocked.
        // Never match on name/email substrings - any user could otherwise self-elevate.
        Gate::before(function ($user, string $ability) {
            if ($user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator'])) {
                return true;
            }
            return null;
        });

        // Gate for management tools (backups, health, validations)
        Gate::define('manage-tools', function ($user) {
            return $user->hasRole(['Owner', 'Admin', 'Super Admin', 'Administrator', 'Manager']);
        });

        // Ensure URLs, assets and routes use HTTPS when served over HTTPS or behind an SSL reverse proxy
        $isHttps = str_starts_with(config('app.url'), 'https://')
            || app()->environment('production')
            || (!app()->runningInConsole() && app()->bound('request') && (request()->server('HTTP_X_FORWARDED_PROTO') === 'https' || request()->isSecure()));
        if ($isHttps) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}

