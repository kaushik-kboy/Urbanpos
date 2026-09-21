<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockUpdate extends Model
{
    use HasFactory, HasPostingLifecycle, \App\Traits\HasCustomFields;

    protected $fillable = ['update_number', 'branch_id', 'entry_date', 'remarks', 'status', 'posting_key'];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockUpdateItem::class);
    }

    /**
     * StockUpdate predates the generic Draft/Posted/Cancelled vocabulary — it already
     * has its own Pending/Approved/Rejected status, where Approved is the posted state.
     */
    public function isPosted(): bool
    {
        return $this->status === 'Approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'Rejected';
    }

    public function assertEditable(): void
    {
        if ($this->isPosted()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => "Stock Update #{$this->update_number} has already been approved and cannot be edited.",
            ]);
        }

        if ($this->isRejected()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => "Stock Update #{$this->update_number} has been rejected and cannot be edited.",
            ]);
        }
    }
}
