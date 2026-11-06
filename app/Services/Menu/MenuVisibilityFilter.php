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

        // 1. If item has URL or route, check direct and alias menu keys
        if ($url) {
            $key = MenuCatalogService::resolveMenuKey($item);
            if ($key && !MenuVisibilitySetting::isVisibleForUser($key, $user)) {
                $item['restricted'] = true;
                return $item;
            }

            // Also check raw url with dots e.g. "sales.pos" or "tools.backups"
            $altKey = str_replace('/', '.', trim($url, '/'));
            if ($altKey !== $key && !MenuVisibilitySetting::isVisibleForUser($altKey, $user)) {
                $item['restricted'] = true;
                return $item;
            }
        } else {
            // 2. Parent Category or Submenu without direct URL (e.g. Sales, Purchase, Master, Till, Inventory)
            if (isset($item['text'])) {
                $catKey = MenuCatalogService::resolveMenuKey($item);
                if ($catKey && !MenuVisibilitySetting::isVisibleForUser($catKey, $user)) {
                    $item['restricted'] = true;
                    return $item;
                }
            }
        }

        return $item;
    }
}
