<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_number', 'bill_date', 'customer_id', 'branch_id', 'invoice_type', 'delivery_type',
        'delivery_time', 'sales_type', 'payment_type', 'item_disc_amount', 'disc_percent',
        'disc_amount', 'round_off', 'total_gst', 'total_extra_cess', 'gst_calamity_cess',
        'total_qty', 'total_weight', 'total', 'remarks', 'message',
    ];

    protected $casts = [
        'bill_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesBillItem::class);
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }
}
