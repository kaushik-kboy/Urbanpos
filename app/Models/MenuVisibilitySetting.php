<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class MenuVisibilitySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'menu_key',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'role_id' => 'integer',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get a key-value map of [menu_key => is_visible] for a specific role (or global null).
     */
    public static function getVisibilityMap(?int $roleId = null): array
    {
        $cacheKey = 'menu_visibility_role_' . ($roleId ?? 'global');

        return Cache::remember($cacheKey, 3600, function () use ($roleId) {
            return static::where('role_id', $roleId)
                ->pluck('is_visible', 'menu_key')
                ->toArray();
        });
    }

    /**
     * Check if a specific menu key is visible for a given role.
     * Falls back to global (null) setting if role-specific setting isn't explicitly defined.
     * Defaults to true if no record exists.
     */
    public static function isVisible(string $menuKey, ?int $roleId = null): bool
    {
        if ($roleId !== null) {
            $roleMap = static::getVisibilityMap($roleId);
            if (array_key_exists($menuKey, $roleMap)) {
                return (bool) $roleMap[$menuKey];
            }
        }

        // Fallback to global setting (null)
        $globalMap = static::getVisibilityMap(null);
        if (array_key_exists($menuKey, $globalMap)) {
            return (bool) $globalMap[$menuKey];
        }

        return true; // Default is visible
    }

    /**
     * Check visibility for a specific authenticated user, across all their assigned roles.
     * Falls back to global setting if no role override exists.
     */
    public static function isVisibleForUser(string $menuKey, ?User $user = null): bool
    {
        if ($user) {
            $userRoleIds = $user->roles->pluck('id')->toArray();
            foreach ($userRoleIds as $rId) {
                $roleMap = static::getVisibilityMap($rId);
                if (array_key_exists($menuKey, $roleMap)) {
                    return (bool) $roleMap[$menuKey];
                }
            }
        }

        // Fallback to global setting
        $globalMap = static::getVisibilityMap(null);
        if (array_key_exists($menuKey, $globalMap)) {
            return (bool) $globalMap[$menuKey];
        }

        return true; // Default is visible
    }

    /**
     * Set visibility for a single key and clear cache.
     */
    public static function setVisibility(string $menuKey, bool $isVisible, ?int $roleId = null): self
    {
        $record = static::updateOrCreate(
            ['role_id' => $roleId, 'menu_key' => $menuKey],
            ['is_visible' => $isVisible]
        );

        static::clearCache($roleId);
        return $record;
    }

    /**
     * Bulk update visibility settings for a role.
     */
    public static function bulkSetVisibility(array $keyValues, ?int $roleId = null): void
    {
        foreach ($keyValues as $menuKey => $isVisible) {
            static::updateOrCreate(
                ['role_id' => $roleId, 'menu_key' => $menuKey],
                ['is_visible' => (bool) $isVisible]
            );
        }

        static::clearCache($roleId);
    }

    /**
     * Reset all visibility settings for a role (or global).
     */
    public static function resetRole(?int $roleId = null): void
    {
        static::where('role_id', $roleId)->delete();
        static::clearCache($roleId);
    }

    /**
     * Invalidate caches across roles and global.
     */
    public static function clearCache(?int $roleId = null): void
    {
        Cache::forget('menu_visibility_role_global');
        if ($roleId !== null) {
            Cache::forget('menu_visibility_role_' . $roleId);
        } else {
            // When global settings change, invalidate all role caches so fallbacks are fresh
            try {
                $roles = Role::pluck('id');
                foreach ($roles as $rId) {
                    Cache::forget('menu_visibility_role_' . $rId);
                }
            } catch (\Throwable $e) {
                // Ignore during testing / early migrations
            }
        }
    }
}
