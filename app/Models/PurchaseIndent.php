<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseIndent extends Model
{
    use HasFactory;

    protected $fillable = [
        'indent_number',
        'indent_date',
        'required_by_date',
        'branch_id',
        'requested_by_id',
        'department',
        'priority',
        'purchase_order_id',
        'status',
        'total_requested_qty',
        'total_approved_qty',
        'total_estimated_amount',
        'remarks',
        'rejection_reason',
        'reviewed_by_id',
        'reviewed_at',
        'cancelled_by_id',
        'cancelled_at',
        'cancellation_reason',
        'posting_key',
    ];

    protected $casts = [
        'indent_date' => 'date',
        'required_by_date' => 'date',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_requested_qty' => 'decimal:3',
        'total_approved_qty' => 'decimal:3',
        'total_estimated_amount' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseIndentItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'Approved';
    }

    public function isConverted(): bool
    {
        return $this->status === 'Converted';
    }
}
