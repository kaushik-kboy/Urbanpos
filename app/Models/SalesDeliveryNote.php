<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesDeliveryNote extends Model
{
    use HasFactory, HasPostingLifecycle;

    protected $fillable = [
        'delivery_number',
        'delivery_date',
        'customer_id',
        'branch_id',
        'sales_order_id',
        'sales_bill_id',
        'reference_no',
        'transporter_name',
        'vehicle_no',
        'lr_no',
        'lr_date',
        'delivery_address',
        'total_ordered_qty',
        'total_dispatched_qty',
        'total_amount',
        'status',
        'remarks',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by_id',
        'created_by_id',
        'posting_key',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'lr_date' => 'date',
        'cancelled_at' => 'datetime',
        'total_ordered_qty' => 'decimal:3',
        'total_dispatched_qty' => 'decimal:3',
        'total_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesBill(): BelongsTo
    {
        return $this->belongsTo(SalesBill::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesDeliveryNoteItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function isPosted(): bool
    {
        return in_array($this->status, ['Invoiced', 'Cancelled'], true);
    }
}
