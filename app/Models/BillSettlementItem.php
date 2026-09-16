<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BillSettlementItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_settlement_id',
        'billable_type',
        'billable_id',
        'bill_amount',
        'settled_amount',
        'discount_amount',
    ];

    protected $casts = [
        'bill_amount' => 'decimal:2',
        'settled_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(BillSettlement::class, 'bill_settlement_id');
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
