<?php

namespace App\Services\Menu;

use App\Models\MenuVisibilitySetting;
use Illuminate\Support\Facades\Auth;
use JeroenNoten\LaravelAdminLte\Menu\Filters\FilterInterface;

class MenuVisibilityFilter implements FilterInterface
{
    /**
     * Transforms a menu item by setting 'restricted' => true if disabled by Admin.
     */
    public function transform($item)
    {
        // Don't filter if already restricted by GateFilter
        if (!empty($item['restricted'])) {
            return $item;
        }

        $user = Auth::user();
        if (!$user) {
            return $item;
        }

        $url = $item['url'] ?? ($item['route'] ?? null);

        // Never restrict the visibility manager itself to avoid lockout
        if ($url && str_contains($url, 'module-integrations')) {
            return $item;
        }

        $roleId = $user->roles->first()?->id;

        // 1. Check direct URL match
        if ($url) {
            $key = MenuCatalogService::resolveMenuKey($item);
            if ($key && !MenuVisibilitySetting::isVisible($key, $roleId)) {
                $item['restricted'] = true;
                return $item;
            }

            // Also check raw url with dots e.g. "sales.pos" or "tools.backups"
            $altKey = str_replace('/', '.', trim($url, '/'));
            if ($altKey !== $key && !MenuVisibilitySetting::isVisible($altKey, $roleId)) {
                $item['restricted'] = true;
                return $item;
            }
        }

        return $item;
    }
}
