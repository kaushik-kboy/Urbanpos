<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseIndentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_indent_id',
        'item_id',
        'current_stock',
        'requested_qty',
        'approved_qty',
        'estimated_cost',
        'remarks',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'requested_qty' => 'decimal:3',
        'approved_qty' => 'decimal:3',
        'estimated_cost' => 'decimal:4',
    ];

    public function indent(): BelongsTo
    {
        return $this->belongsTo(PurchaseIndent::class, 'purchase_indent_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function getLineTotalAttribute(): float
    {
        $qty = (float) ($this->approved_qty !== null ? $this->approved_qty : $this->requested_qty);

        return round($qty * (float) $this->estimated_cost, 2);
    }
}
