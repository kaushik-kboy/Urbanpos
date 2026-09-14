<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLedger extends Model
{
    protected $table = 'stock_ledger';

    protected $fillable = [
        'item_id', 'branch_id', 'exp_date', 'movement_type',
        'reference_type', 'reference_id',
        'qty_in', 'qty_out', 'unit_cost', 'value_in', 'value_out',
        'running_balance_qty', 'running_balance_value',
        'user_id', 'reason_code', 'reversal_of',
        'document_date', 'posted_at',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'qty_in' => 'decimal:3',
        'qty_out' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'value_in' => 'decimal:2',
        'value_out' => 'decimal:2',
        'running_balance_qty' => 'decimal:3',
        'running_balance_value' => 'decimal:2',
        'document_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }
}
