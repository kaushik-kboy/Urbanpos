<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class InventoryObservation extends Model
{
    protected $fillable = [
        'type',
        'task_id',
        'item_id',
        'branch_id',
        'batch_no',
        'batch_correction_applied',
        'original_batch_no',
        'batch_correction_reason',
        'exp_date_observed',
        'physical_qty',
        'remarks',
        'photo_path',
        'idempotency_key',
        'system_qty_snapshot',
        'status',
        'parent_observation_id',
        'approved_qty',
        'actual_adjustment_qty',
        'stock_ledger_id',
        'submitted_by',
        'reviewed_by',
        'review_remarks',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => 'string',
            'status' => 'string',
            'batch_correction_applied' => 'boolean',
            'exp_date_observed' => 'date',
            'physical_qty' => 'decimal:3',
            'system_qty_snapshot' => 'decimal:3',
            'approved_qty' => 'decimal:3',
            'actual_adjustment_qty' => 'decimal:3',
            'reviewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(StockCountTask::class, 'task_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockLedger(): BelongsTo
    {
        return $this->belongsTo(StockLedger::class, 'stock_ledger_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The prior observation this row recounts (set when status transitioned via
     * recount_requested and the staff resubmitted a new row).
     */
    public function parentObservation(): BelongsTo
    {
        return $this->belongsTo(InventoryObservation::class, 'parent_observation_id');
    }

    /**
     * Recount re-submissions that reference this observation as their parent.
     */
    public function recounts(): HasMany
    {
        return $this->hasMany(InventoryObservation::class, 'parent_observation_id');
    }
}
