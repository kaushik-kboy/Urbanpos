<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id', 'item_id', 'exp_date', 'qty', 'unit_cost', 'received_qty',
        'gst_percent', 'taxable_value', 'gst_tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
    ];

    protected $casts = [
        'exp_date' => 'date',
    ];

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
