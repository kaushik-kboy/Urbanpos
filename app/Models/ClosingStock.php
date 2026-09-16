<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingStock extends Model
{
    use HasFactory;

    protected $table = 'closing_stocks';

    protected $guarded = ['id'];

    protected $casts = [
        'net_cost' => 'decimal:2',
        'closing_stock' => 'decimal:3',
        'closing_stock_amount' => 'decimal:2',
        'mrp' => 'decimal:2',
        'expiry_date' => 'date',
        'as_on_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
