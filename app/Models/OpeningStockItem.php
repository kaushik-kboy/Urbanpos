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
        'sell_price', 'mrp', 'disc_percent', 'disc_amount',
        'scheme_disc_percent', 'scheme_amount', 'scheme_others',
        'gst_percent', 'gst_tax_amount', 'net_amount',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'qty' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'disc_percent' => 'decimal:2',
        'disc_amount' => 'decimal:2',
        'scheme_disc_percent' => 'decimal:2',
        'scheme_amount' => 'decimal:2',
        'scheme_others' => 'decimal:2',
        'gst_percent' => 'decimal:2',
        'gst_tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
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
