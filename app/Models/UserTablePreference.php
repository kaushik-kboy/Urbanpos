<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTablePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'table_key',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getForUser(int $userId, string $tableKey): ?array
    {
        $record = static::where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->first();

        return $record ? $record->preferences : null;
    }

    public static function getResolvedForUser(?User $user, string $tableKey): ?array
    {
        if (! $user) {
            return null;
        }

        $canCustomize = $user->hasRole(['Owner', 'Super Admin', 'Admin', 'Administrator'])
            || $user->can('layout-customizer.customize')
            || $user->can('layout.customize');

        // Only authorized users can maintain and view personal override layouts
        if ($canCustomize) {
            $userPref = static::getForUser($user->id, $tableKey);
            if ($userPref !== null) {
                return $userPref;
            }
        }

        // Look up Role layout for this user's assigned roles
        $roleIds = $user->roles->pluck('id')->filter()->all();
        if (! empty($roleIds)) {
            $rolePref = RoleTablePreference::getForRoles($roleIds, $tableKey);
            if ($rolePref !== null) {
                return $rolePref;
            }
        }

        // Fallback to global role layout (role_id = null)
        $globalPref = RoleTablePreference::getGlobal($tableKey);
        if ($globalPref !== null) {
            return $globalPref;
        }

        return null;
    }

    public static function setForUser(int $userId, string $tableKey, array $preferences): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'table_key' => $tableKey],
            ['preferences' => $preferences]
        );
    }

    public static function resetForUser(int $userId, string $tableKey): bool
    {
        return (bool) static::where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->delete();
    }
}
