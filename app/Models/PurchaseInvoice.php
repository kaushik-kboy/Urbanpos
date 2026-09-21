<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use HasFactory, HasPostingLifecycle, \App\Traits\HasCustomFields;

    protected $fillable = [
        'invoice_number', 'invoice_date', 'supplier_id', 'branch_id', 'purchase_order_id',
        'purchase_receipt_note_id', 'grn_number', 'grn_date', 'supplier_inv_no', 'supplier_inv_date', 'supplier_inv_amount',
        'purchase_type', 'c_form', 'item_disc_amount', 'disc_percent', 'disc_amount', 'freight',
        'round_off', 'scheme_item_disc_amt', 'scheme_item_disc_percent', 'other_disc_amt', 'total_gst', 'total_cgst',
        'total_sgst', 'total_igst', 'total_extra_cess',
        'tcs_amount', 'total_qty', 'total_weight', 'total', 'remarks', 'message',
        'status', 'posting_key',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'grn_date' => 'date',
        'supplier_inv_date' => 'date',
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

    public function receiptNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptNote::class, 'purchase_receipt_note_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function settlementItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(BillSettlementItem::class, 'billable');
    }
}
