<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLoyaltyPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'sales_bill_id',
        'type',
        'points',
        'amount_value',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'amount_value' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesBill(): BelongsTo
    {
        return $this->belongsTo(SalesBill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
