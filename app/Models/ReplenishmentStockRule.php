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
     * Determine matching stock status for a given cover days count.
     */
    public static function resolveForCoverDays(float $coverDays): ?self
    {
        return static::where('is_active', true)
            ->where('min_cover_days', '<=', $coverDays)
            ->where(function ($query) use ($coverDays) {
                $query->whereNull('max_cover_days')
                    ->orWhere('max_cover_days', '>=', $coverDays);
            })
            ->orderBy('min_cover_days', 'desc')
            ->first();
    }
}

