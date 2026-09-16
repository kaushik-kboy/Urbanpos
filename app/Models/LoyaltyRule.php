<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'min_bill_amount',
        'max_bill_amount',
        'points_earned',
    ];

    protected $casts = [
        'min_bill_amount' => 'decimal:2',
        'max_bill_amount' => 'decimal:2',
        'points_earned' => 'decimal:2',
    ];

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }
}
