<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplenishmentSalesRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'rule_name',
        'min_daily_avg',
        'max_daily_avg',
        'target_cover_days',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_daily_avg' => 'float',
        'max_daily_avg' => 'float',
        'target_cover_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Determine matching sales rule for a given daily average with continuous zero-gap thresholds.
     */
    public static function resolveForVelocity(float $avgDailySales): ?self
    {
        $normalizedVelocity = max(0.0, $avgDailySales);

        // Check rules ordered by min_daily_avg descending (Highest >= 20, Fast >= 10, Normal >= 5, Slow >= 1, Dead >= 0)
        $rules = static::where('is_active', true)
            ->orderBy('min_daily_avg', 'desc')
            ->get();

        foreach ($rules as $rule) {
            if ($normalizedVelocity >= (float) $rule->min_daily_avg) {
                return $rule;
            }
        }

        return $rules->last();
    }
}
