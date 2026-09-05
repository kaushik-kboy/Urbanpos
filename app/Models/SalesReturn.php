<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_number', 'return_date', 'customer_id', 'branch_id', 'sales_bill_id',
        'return_mode', 'sales_type', 'item_disc_amount', 'disc_percent', 'disc_amount',
        'round_off', 'total_gst', 'total_extra_cess', 'gst_calamity_cess', 'total', 'remarks',
    ];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesBill(): BelongsTo
    {
        return $this->belongsTo(SalesBill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
