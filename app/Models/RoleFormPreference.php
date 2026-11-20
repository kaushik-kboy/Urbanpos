<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RoleFormPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'form_key',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function getForRole(int $roleId, string $formKey): ?array
    {
        $record = static::where('role_id', $roleId)
            ->where('form_key', $formKey)
            ->first();

        return $record ? $record->preferences : null;
    }

    public static function getForRoles(array $roleIds, string $formKey): ?array
    {
        if (empty($roleIds)) {
            return null;
        }

        $record = static::whereIn('role_id', $roleIds)
            ->where('form_key', $formKey)
            ->first();

        return $record ? $record->preferences : null;
    }

    public static function getGlobal(string $formKey): ?array
    {
        $record = static::whereNull('role_id')
            ->where('form_key', $formKey)
            ->first();

        return $record ? $record->preferences : null;
    }

    public static function setForRole(?int $roleId, string $formKey, array $preferences): static
    {
        return static::updateOrCreate(
            ['role_id' => $roleId, 'form_key' => $formKey],
            ['preferences' => $preferences]
        );
    }

    public static function resetForRole(?int $roleId, string $formKey): bool
    {
        return (bool) static::where('role_id', $roleId)
            ->where('form_key', $formKey)
            ->delete();
    }
}
