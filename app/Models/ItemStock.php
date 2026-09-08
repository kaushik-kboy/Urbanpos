<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'branch_id',
        'quantity',
        'cost_price',
        'landing_cost',
        'sell_price',
        'mrp',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'landing_cost' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'mrp' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function adjust(int $itemId, int $branchId, float $delta): void
    {
        $stock = static::firstOrCreate(
            ['item_id' => $itemId, 'branch_id' => $branchId],
            ['quantity' => 0]
        );

        $stock->increment('quantity', $delta);
    }
}
