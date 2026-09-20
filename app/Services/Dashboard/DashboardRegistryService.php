<?php

namespace App\Services\Dashboard;

use App\Models\ItemStock;
use App\Models\User;
use App\Models\UserDashboardPreference;
use Illuminate\Support\Collection;

class DashboardRegistryService
{
    /**
     * Get all registered shortcuts that the given user has permission to access.
     */
    public function getAccessibleShortcuts(User $user): Collection
    {
        $allShortcuts = config('dashboard.shortcuts', []);

        return collect($allShortcuts)->filter(function ($item) use ($user) {
            // If no permission is attached, available to all authenticated users
            if (empty($item['permission'])) {
                return true;
            }

            // Otherwise, check Spatie permissions or SuperAdmin/Owner role
            if ($user->hasRole('Owner') || $user->hasRole('Super Admin')) {
                return true;
            }

            return $user->can($item['permission']);
        });
    }

    /**
     * Get the active shortcuts to render on the dashboard for this user.
     * Order is preserved as saved by the user.
     */
    public function getActiveShortcutsForUser(User $user, ?int $branchId = null): Collection
    {
        $accessible = $this->getAccessibleShortcuts($user);
        $accessibleKeys = $accessible->keys()->all();

        // 1. Check user custom saved preference
        $pref = UserDashboardPreference::getForUser($user->id);
        $savedKeys = $pref?->shortcuts;

        if (is_array($savedKeys) && !empty($savedKeys)) {
            // Keep only keys that user has permission to see right now
            $validKeys = array_values(array_intersect($savedKeys, $accessibleKeys));
        } else {
            // 2. Fall back to role default preset
            $validKeys = $this->getDefaultKeysForUser($user, $accessibleKeys);
        }

        // Lazy badge loader
        $lowStockCount = null;

        return collect($validKeys)->map(function ($key) use ($accessible, $branchId, &$lowStockCount) {
            if (!$accessible->has($key)) {
                return null;
            }

            $shortcut = $accessible->get($key);

            // Populate dynamic badge if specified
            if (($shortcut['badge'] ?? null) === 'low_stock_count') {
                if ($lowStockCount === null) {
                    $lowStockCount = (int) ItemStock::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                        ->where('quantity', '<=', 5)
                        ->where('quantity', '>', 0)
                        ->distinct('item_id')
                        ->count('item_id');
                }
                $shortcut['badge_value'] = $lowStockCount;
            }

            return $shortcut;
        })->filter()->values();
    }

    /**
     * Get default shortcut keys based on user's primary role.
     */
    public function getDefaultKeysForUser(User $user, array $accessibleKeys): array
    {
        $roleDefaults = config('dashboard.role_defaults', []);

        $chosenDefaults = null;
        if (method_exists($user, 'getRoleNames')) {
            $userRoles = $user->getRoleNames()->all();
            foreach ($userRoles as $role) {
                if (isset($roleDefaults[$role])) {
                    $chosenDefaults = $roleDefaults[$role];
                    break;
                }
            }
        }

        if (!$chosenDefaults) {
            $chosenDefaults = $roleDefaults['default'] ?? array_slice($accessibleKeys, 0, 8);
        }

        // Filter against accessible keys
        return array_values(array_intersect($chosenDefaults, $accessibleKeys));
    }

    /**
     * Get active widgets configuration for the user.
     */
    public function getActiveWidgetsForUser(User $user): array
    {
        $allWidgets = config('dashboard.widgets', []);
        $pref = UserDashboardPreference::getForUser($user->id);

        if ($pref && is_array($pref->visible_widgets)) {
            $visibleKeys = $pref->visible_widgets;
            $result = [];
            foreach ($allWidgets as $key => $widget) {
                $result[$key] = in_array($key, $visibleKeys, true);
            }
            return $result;
        }

        // Defaults: Cashier gets minimal widgets by default, Owner gets all
        if ($user->hasRole('Cashier')) {
            return [
                'kpi_sales'      => true,
                'kpi_stock'      => false,
                'revenue_trend'  => false,
                'category_share' => false,
                'top_items'      => true,
                'recent_bills'   => true,
            ];
        }

        // Default all true
        return array_fill_keys(array_keys($allWidgets), true);
    }
}
