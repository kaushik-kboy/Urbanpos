<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDeliveryNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_delivery_note_id',
        'sales_order_item_id',
        'item_id',
        'ordered_qty',
        'dispatched_qty',
        'unit_price',
        'cost_at_dispatch',
        'mrp',
        'batch_no',
        'exp_date',
        'remarks',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'ordered_qty' => 'decimal:3',
        'dispatched_qty' => 'decimal:3',
        'unit_price' => 'decimal:4',
        'cost_at_dispatch' => 'decimal:4',
        'mrp' => 'decimal:2',
    ];

    public function salesDeliveryNote(): BelongsTo
    {
        return $this->belongsTo(SalesDeliveryNote::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getLineTotalAttribute(): float
    {
        return round((float) $this->dispatched_qty * (float) $this->unit_price, 2);
    }
}
