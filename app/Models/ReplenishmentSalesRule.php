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
     * Determine matching sales rule for a given daily average.
     */
    public static function resolveForVelocity(float $avgDailySales): ?self
    {
        return static::where('is_active', true)
            ->where('min_daily_avg', '<=', $avgDailySales)
            ->where(function ($query) use ($avgDailySales) {
                $query->whereNull('max_daily_avg')
                    ->orWhere('max_daily_avg', '>=', $avgDailySales);
            })
            ->orderBy('min_daily_avg', 'desc')
            ->first();
    }
}

