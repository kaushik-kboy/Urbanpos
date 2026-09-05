<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockUpdateItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_update_id', 'item_id', 'exp_date', 'physical_qty', 'system_qty_at_entry',
        'delta_qty', 'sell_price', 'mrp',
    ];

    protected $casts = [
        'exp_date' => 'date',
    ];

    public function stockUpdate(): BelongsTo
    {
        return $this->belongsTo(StockUpdate::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
