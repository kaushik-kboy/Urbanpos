<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesBill extends Model
{
    use HasFactory, HasPostingLifecycle;

    protected $fillable = [
        'bill_number', 'bill_date', 'customer_id', 'branch_id', 'till_session_id', 'invoice_type', 'delivery_type',
        'delivery_time', 'sales_type', 'payment_type', 'item_disc_amount', 'disc_percent',
        'disc_amount', 'round_off', 'total_gst', 'total_cgst', 'total_sgst', 'total_igst',
        'total_extra_cess', 'gst_calamity_cess',
        'total_qty', 'total_weight', 'total', 'remarks', 'message',
        'status', 'posting_key',
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

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalesBillPayment::class);
    }

    public function settlementItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(BillSettlementItem::class, 'billable');
    }
}
