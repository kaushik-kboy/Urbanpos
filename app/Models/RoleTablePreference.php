<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RoleTablePreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'table_key',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function getForRole(int $roleId, string $tableKey): ?array
    {
        try {
            $record = static::where('role_id', $roleId)
                ->where('table_key', $tableKey)
                ->first();

            return $record ? $record->preferences : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getForRoles(array $roleIds, string $tableKey): ?array
    {
        if (empty($roleIds)) {
            return null;
        }

        try {
            $record = static::whereIn('role_id', $roleIds)
                ->where('table_key', $tableKey)
                ->first();

            return $record ? $record->preferences : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getGlobal(string $tableKey): ?array
    {
        try {
            $record = static::whereNull('role_id')
                ->where('table_key', $tableKey)
                ->first();

            return $record ? $record->preferences : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function setForRole(?int $roleId, string $tableKey, array $preferences): static
    {
        return static::updateOrCreate(
            ['role_id' => $roleId, 'table_key' => $tableKey],
            ['preferences' => $preferences]
        );
    }

    public static function resetForRole(?int $roleId, string $tableKey): bool
    {
        return (bool) static::where('role_id', $roleId)
            ->where('table_key', $tableKey)
            ->delete();
    }
}
