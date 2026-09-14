<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    use HasFactory, HasPostingLifecycle;

    protected $fillable = [
        'transfer_number', 'transfer_date', 'from_branch_id', 'to_branch_id', 'status',
        'posting_key', 'remarks', 'total_qty', 'total_value', 'dispatched_at', 'received_at',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * Received and Cancelled are both terminal — only a Dispatched transfer can still
     * be acted on (received or cancelled). There is no "edit" action on this model at
     * all (see StockTransferController), so this mainly documents intent.
     */
    public function isPosted(): bool
    {
        return in_array($this->status, ['Received', 'Cancelled'], true);
    }
}
