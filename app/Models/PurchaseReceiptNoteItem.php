<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_note_id',
        'purchase_order_item_id',
        'item_id',
        'ordered_qty',
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'unit_cost',
        'mrp',
        'batch_no',
        'exp_date',
        'remarks',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'ordered_qty' => 'decimal:3',
        'received_qty' => 'decimal:3',
        'accepted_qty' => 'decimal:3',
        'rejected_qty' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'mrp' => 'decimal:2',
    ];

    public function purchaseReceiptNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceiptNote::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getLineTotalAttribute(): float
    {
        return round((float) $this->accepted_qty * (float) $this->unit_cost, 2);
    }
}
