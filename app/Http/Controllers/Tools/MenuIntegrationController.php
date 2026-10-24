<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\MenuVisibilitySetting;
use App\Services\Menu\MenuCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class MenuIntegrationController extends Controller
{
    /**
     * Display the Menu & Module Integration Management Studio.
     */
    public function index(Request $request)
    {
        $roles = Role::withCount('users')->orderBy('name')->get();

        // Determine active role selection (null means Global)
        $roleParam = $request->query('role_id');
        $selectedRoleId = null;

        if ($roleParam !== null && $roleParam !== 'global' && $roleParam !== '') {
            $selectedRoleId = (int) $roleParam;
        } elseif ($roleParam === null) {
            // Default to current user's role or first role
            $selectedRoleId = auth()->user()?->roles->first()?->id ?? ($roles->first()?->id ?? null);
        }

        $selectedRole = $selectedRoleId ? Role::find($selectedRoleId) : null;

        // Fetch visibility maps
        $visibilityMap = MenuVisibilitySetting::getVisibilityMap($selectedRoleId);
        $globalMap = MenuVisibilitySetting::getVisibilityMap(null);

        // Fetch catalogs
        $mainMenus = MenuCatalogService::getMainMenusCatalog();
        $reportsGrouped = MenuCatalogService::getReportsCatalog();
        $presets = MenuCatalogService::getPresets();

        // Calculate counts
        $totalMainMenuCount = 0;
        $enabledMainMenuCount = 0;
        foreach ($mainMenus as $cat) {
            foreach (array_keys($cat['items']) as $k) {
                $totalMainMenuCount++;
                $isVisible = array_key_exists($k, $visibilityMap) ? $visibilityMap[$k] : ($globalMap[$k] ?? true);
                if ($isVisible) $enabledMainMenuCount++;
            }
        }

        $totalReportCount = 0;
        $enabledReportCount = 0;
        foreach ($reportsGrouped as $cat) {
            foreach (array_keys($cat['items']) as $k) {
                $totalReportCount++;
                $isVisible = array_key_exists($k, $visibilityMap) ? $visibilityMap[$k] : ($globalMap[$k] ?? true);
                if ($isVisible) $enabledReportCount++;
            }
        }

        return view('tools.module-integrations.index', compact(
            'roles',
            'selectedRoleId',
            'selectedRole',
            'visibilityMap',
            'globalMap',
            'mainMenus',
            'reportsGrouped',
            'presets',
            'totalMainMenuCount',
            'enabledMainMenuCount',
            'totalReportCount',
            'enabledReportCount'
        ));
    }

    /**
     * AJAX Toggle for a single menu or report item.
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'role_id' => 'nullable',
            'menu_key' => 'required|string',
            'is_visible' => 'required|boolean',
        ]);

        $roleId = $request->input('role_id');
        if ($roleId === 'global' || $roleId === '' || $roleId === null) {
            $roleId = null;
        } else {
            $roleId = (int) $roleId;
        }

        $menuKey = $request->input('menu_key');
        $isVisible = (bool) $request->input('is_visible');

        MenuVisibilitySetting::setVisibility($menuKey, $isVisible, $roleId);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'menu_key' => $menuKey,
            'is_visible' => $isVisible,
            'role_id' => $roleId,
        ]);
    }

    /**
     * AJAX Bulk Toggle for an entire category of menus or reports.
     */
    public function bulkToggle(Request $request): JsonResponse
    {
        $request->validate([
            'role_id' => 'nullable',
            'keys' => 'required|array',
            'is_visible' => 'required|boolean',
        ]);

        $roleId = $request->input('role_id');
        if ($roleId === 'global' || $roleId === '' || $roleId === null) {
            $roleId = null;
        } else {
            $roleId = (int) $roleId;
        }

        $keys = $request->input('keys');
        $isVisible = (bool) $request->input('is_visible');

        $keyMap = [];
        foreach ($keys as $key) {
            $keyMap[$key] = $isVisible;
        }

        MenuVisibilitySetting::bulkSetVisibility($keyMap, $roleId);

        return response()->json([
            'success' => true,
            'message' => count($keys) . ' items updated successfully.',
            'count' => count($keys),
            'is_visible' => $isVisible,
        ]);
    }

    /**
     * Apply a pre-configured profile preset.
     */
    public function applyPreset(Request $request)
    {
        $request->validate([
            'role_id' => 'nullable',
            'preset' => 'required|string',
        ]);

        $roleId = $request->input('role_id');
        if ($roleId === 'global' || $roleId === '' || $roleId === null) {
            $roleId = null;
        } else {
            $roleId = (int) $roleId;
        }

        $preset = $request->input('preset');
        MenuCatalogService::applyPreset($preset, $roleId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Preset '{$preset}' applied successfully.",
            ]);
        }

        return redirect()->route('tools.module-integrations.index', ['role_id' => $roleId ?? 'global'])
            ->with('status', "Preset applied successfully.");
    }

    /**
     * Reset visibility settings to default.
     */
    public function reset(Request $request)
    {
        $roleId = $request->input('role_id');
        if ($roleId === 'global' || $roleId === '' || $roleId === null) {
            $roleId = null;
        } else {
            $roleId = (int) $roleId;
        }

        MenuVisibilitySetting::resetRole($roleId);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Visibility settings reset to system default.",
            ]);
        }

        return redirect()->route('tools.module-integrations.index', ['role_id' => $roleId ?? 'global'])
            ->with('status', "Visibility settings reset to system default.");
    }
}
