<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'item_id',
        'qty',
        'dispatched_qty',
        'sell_price',
        'mrp',
        'disc_percent',
        'disc_amount',
        'gst_percent',
        'gst_tax_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'net_amount',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'dispatched_qty' => 'decimal:3',
        'sell_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function getPendingQtyAttribute(): float
    {
        return max(0, (float) $this->qty - (float) ($this->dispatched_qty ?? 0));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
