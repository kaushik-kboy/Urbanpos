<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReceiptNote extends Model
{
    use HasFactory, HasPostingLifecycle, \App\Traits\HasCustomFields;

    protected $fillable = [
        'receipt_number',
        'receipt_date',
        'supplier_id',
        'branch_id',
        'purchase_order_id',
        'purchase_invoice_id',
        'supplier_challan_no',
        'supplier_challan_date',
        'vehicle_no',
        'transporter_name',
        'total_ordered_qty',
        'total_received_qty',
        'total_accepted_qty',
        'total_rejected_qty',
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
        'receipt_date' => 'date',
        'supplier_challan_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptNoteItem::class);
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
