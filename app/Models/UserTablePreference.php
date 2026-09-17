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
