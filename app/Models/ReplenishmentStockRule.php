<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReplenishmentStockRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_type',
        'min_cover_days',
        'max_cover_days',
        'color_code',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_cover_days' => 'integer',
        'max_cover_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Determine matching stock status for a given cover days count with continuous zero-gap thresholds.
     */
    public static function resolveForCoverDays(float $coverDays): ?self
    {
        $normalizedDays = max(0.0, $coverDays);

        // Check tiers with max threshold in ascending order (Critical <= 3, Low <= 10, Healthy <= 30)
        $rulesWithMax = static::where('is_active', true)
            ->whereNotNull('max_cover_days')
            ->orderBy('max_cover_days', 'asc')
            ->get();

        foreach ($rulesWithMax as $rule) {
            if ($normalizedDays <= (float) $rule->max_cover_days) {
                return $rule;
            }
        }

        // Exceeds all thresholds -> Overstock (max_cover_days is null)
        return static::where('is_active', true)
            ->whereNull('max_cover_days')
            ->first() ?? static::where('is_active', true)->orderBy('min_cover_days', 'desc')->first();
    }
}
