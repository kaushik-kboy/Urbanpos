<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesQuotation extends Model
{
    use HasFactory, \App\Traits\HasCustomFields;

    protected $fillable = [
        'quotation_number',
        'quotation_date',
        'valid_until',
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
        'remarks',
        'status',
        'converted_sales_bill_id',
    ];

    protected $casts = [
        'quotation_date' => 'date',
        'valid_until' => 'date',
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
        return $this->hasMany(SalesQuotationItem::class);
    }

    public function salesBill(): BelongsTo
    {
        return $this->belongsTo(SalesBill::class, 'converted_sales_bill_id');
    }
}
