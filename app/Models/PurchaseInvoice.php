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
        'invoice_number', 'invoice_date', 'supplier_id', 'supplier_gstin', 'branch_id', 'purchase_order_id',
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

    /**
     * Get line items, falling back to a consolidated synthetic item for historical records.
     */
    public function getResolvedItemsAttribute()
    {
        if ($this->items && $this->items->count() > 0) {
            return $this->items;
        }

        $taxable = max(0, round((float) $this->total - (float) $this->total_gst, 2));
        $qty = max(1.0, (float) ($this->total_qty ?: 1));
        $rate = $taxable > 0 ? round($taxable / $qty, 2) : round((float) $this->total / $qty, 2);
        $gstPercent = $taxable > 0 ? round(((float) $this->total_gst / $taxable) * 100, 2) : 0.0;

        $desc = 'Pet Supplies & Trade Merchandise';
        if (!empty($this->supplier_inv_no)) {
            $desc .= ' (Supplier Inv: ' . $this->supplier_inv_no . ')';
        }

        $synthetic = new PurchaseInvoiceItem([
            'purchase_invoice_id' => $this->id,
            'qty' => $qty,
            'free_qty' => 0,
            'cost_price' => $rate,
            'effective_cost' => $rate,
            'sell_price' => round((float) $this->total / $qty, 2),
            'mrp' => round((float) $this->total / $qty, 2),
            'disc_amount' => (float) ($this->disc_amount ?? 0),
            'disc_percent' => (float) ($this->disc_percent ?? 0),
            'gst_percent' => $gstPercent,
            'gst_tax_amount' => (float) ($this->total_gst ?? 0),
            'cgst_amount' => (float) ($this->total_cgst ?? 0),
            'sgst_amount' => (float) ($this->total_sgst ?? 0),
            'igst_amount' => (float) ($this->total_igst ?? 0),
            'net_amount' => (float) $this->total,
        ]);
        $synthetic->setRelation('item', new Item([
            'name' => $desc,
            'item_code' => $this->invoice_number ?: ('GRN-' . $this->id),
            'hsn_code' => '23091000',
        ]));
        $synthetic->is_synthetic = true;

        return collect([$synthetic]);
    }

    public function settlementItems(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(BillSettlementItem::class, 'billable');
    }
}
