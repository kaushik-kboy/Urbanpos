<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardPreference extends Model
{
    use HasFactory;

    protected $table = 'user_dashboard_preferences';

    protected $fillable = [
        'user_id',
        'shortcuts',
        'visible_widgets',
    ];

    protected $casts = [
        'shortcuts' => 'array',
        'visible_widgets' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get dashboard preferences for a specific user.
     */
    public static function getForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * Set or update preferences for a specific user.
     */
    public static function setForUser(int $userId, array $shortcuts, ?array $visibleWidgets = null): static
    {
        $payload = ['shortcuts' => array_values($shortcuts)];
        if ($visibleWidgets !== null) {
            $payload['visible_widgets'] = array_values($visibleWidgets);
        }

        return static::updateOrCreate(
            ['user_id' => $userId],
            $payload
        );
    }

    /**
     * Reset preferences for a specific user.
     */
    public static function resetForUser(int $userId): bool
    {
        return (bool) static::where('user_id', $userId)->delete();
    }
}
