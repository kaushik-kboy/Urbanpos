<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageStockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'damage_stock_id', 'item_id', 'exp_date', 'qty', 'cost_price', 'sell_price', 'mrp',
        'gst_percent', 'gst_tax_amount', 'net_amount',
    ];

    protected $casts = [
        'exp_date' => 'date',
    ];

    public function damageStock(): BelongsTo
    {
        return $this->belongsTo(DamageStock::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
