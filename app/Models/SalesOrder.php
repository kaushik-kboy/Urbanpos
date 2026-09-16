<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_date',
        'expected_delivery_date',
        'customer_id',
        'branch_id',
        'sales_type',
        'item_disc_amount',
        'disc_percent',
        'disc_amount',
        'round_off',
        'total_gst',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total',
        'advance_amount',
        'remarks',
        'status',
        'converted_sales_bill_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
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
        return $this->hasMany(SalesOrderItem::class);
    }

    public function salesBill(): BelongsTo
    {
        return $this->belongsTo(SalesBill::class, 'converted_sales_bill_id');
    }
}
