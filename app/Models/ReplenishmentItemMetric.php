<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentItemMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'branch_id',
        'current_stock',
        'other_branch_stock',
        'sales_30d',
        'daily_avg_sales',
        'cover_days',
        'target_cover_days',
        'suggested_qty',
        'sales_type',
        'stock_status',
        'primary_supplier_id',
        'calculated_at',
    ];

    protected $casts = [
        'current_stock' => 'float',
        'other_branch_stock' => 'float',
        'sales_30d' => 'float',
        'daily_avg_sales' => 'float',
        'cover_days' => 'float',
        'target_cover_days' => 'integer',
        'suggested_qty' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }
}
