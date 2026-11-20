<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFormPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'form_key',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getForUser(int $userId, string $formKey): ?array
    {
        $record = static::where('user_id', $userId)
            ->where('form_key', $formKey)
            ->first();

        return $record ? $record->preferences : null;
    }

    public static function getResolvedForUser(?User $user, string $formKey): ?array
    {
        if (! $user) {
            return null;
        }

        $canCustomize = $user->hasRole(['Owner', 'Super Admin', 'Admin', 'Administrator'])
            || $user->can('layout-customizer.customize')
            || $user->can('layout.customize');

        // Only authorized users can maintain and view personal override layouts
        if ($canCustomize) {
            $userPref = static::getForUser($user->id, $formKey);
            if ($userPref !== null) {
                return $userPref;
            }
        }

        // Look up Role layout for this user's assigned roles
        $roleIds = $user->roles->pluck('id')->filter()->all();
        if (! empty($roleIds)) {
            $rolePref = RoleFormPreference::getForRoles($roleIds, $formKey);
            if ($rolePref !== null) {
                return $rolePref;
            }
        }

        // Fallback to global role layout (role_id = null)
        $globalPref = RoleFormPreference::getGlobal($formKey);
        if ($globalPref !== null) {
            return $globalPref;
        }

        return null;
    }

    public static function setForUser(int $userId, string $formKey, array $preferences): static
    {
        return static::updateOrCreate(
            ['user_id' => $userId, 'form_key' => $formKey],
            ['preferences' => $preferences]
        );
    }

    public static function resetForUser(int $userId, string $formKey): bool
    {
        return (bool) static::where('user_id', $userId)
            ->where('form_key', $formKey)
            ->delete();
    }
}
