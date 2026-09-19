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
