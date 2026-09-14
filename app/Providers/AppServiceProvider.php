<?php

namespace App\Providers;

use App\Models\Branch;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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

        $this->registerBranchNavbarItem();
    }

    /**
     * A null-branch_id user (e.g. Owner) gets a real branch-switcher dropdown; a
     * single-branch user just sees their branch name — no picker, since there's
     * nothing for them to switch to. See App\Http\Controllers\SwitchBranchController
     * and App\Http\Middleware\EnsureBranchAccess for the rest of this feature.
     */
    private function registerBranchNavbarItem(): void
    {
        Event::listen(BuildingMenu::class, function (BuildingMenu $event) {
            $user = Auth::user();
            if (! $user) {
                return;
            }

            if ($user->branch_id !== null) {
                $event->menu->add([
                    'text' => 'Branch: '.($user->branch?->name ?? '—'),
                    'url' => '#',
                    'icon' => 'fas fa-store-alt',
                    'topnav_right' => true,
                ]);

                return;
            }

            $activeBranchId = session('active_branch_id');
            $activeBranchName = $activeBranchId
                ? Branch::find($activeBranchId)?->name
                : null;

            $event->menu->add([
                'text' => 'Branch: '.($activeBranchName ?? 'All'),
                'icon' => 'fas fa-store-alt',
                'topnav_right' => true,
                'submenu' => Branch::orderBy('name')->pluck('name', 'id')
                    ->map(fn ($name, $id) => ['text' => $name, 'url' => 'switch-branch/'.$id])
                    ->values()
                    ->all(),
            ]);
        });
    }
}
