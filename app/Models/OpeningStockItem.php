<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningStockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_stock_id', 'item_id', 'supplier_id', 'exp_date', 'qty', 'cost_price',
        'sell_price', 'mrp', 'disc_percent', 'disc_amount', 'gst_percent', 'gst_tax_amount',
        'net_amount',
    ];

    protected $casts = [
        'exp_date' => 'date',
    ];

    public function openingStock(): BelongsTo
    {
        return $this->belongsTo(OpeningStock::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
