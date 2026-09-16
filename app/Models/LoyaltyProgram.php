<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'based_on',
        'min_points_redeem',
        'amount_per_point',
        'points_per_hundred',
        'roundoff',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'min_points_redeem' => 'integer',
        'amount_per_point' => 'decimal:2',
        'points_per_hundred' => 'decimal:2',
        'roundoff' => 'boolean',
        'status' => 'boolean',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(LoyaltyRule::class)->orderBy('min_bill_amount');
    }

    public function scopeActive($query)
    {
        $today = now()->toDateString();

        return $query->where('status', true)
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', $today);
            });
    }

    public function calculatePointsForBill(float $billAmount): float
    {
        // 1. Check if matching slab exists
        $matchingRule = $this->rules()
            ->where('min_bill_amount', '<=', $billAmount)
            ->where(function ($q) use ($billAmount) {
                $q->whereNull('max_bill_amount')
                  ->orWhere('max_bill_amount', '>=', $billAmount);
            })
            ->first();

        if ($matchingRule && (float) $matchingRule->points_earned > 0) {
            $pts = (float) $matchingRule->points_earned;
        } else {
            // Default calculation using points_per_hundred
            $rate = (float) $this->points_per_hundred;
            if ($rate <= 0) {
                return 0.00;
            }
            $pts = ($billAmount / 100.0) * $rate;
        }

        return $this->roundoff ? (float) round($pts) : (float) round($pts, 2);
    }
}
