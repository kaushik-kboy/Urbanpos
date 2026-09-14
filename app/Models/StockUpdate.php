<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockUpdate extends Model
{
    use HasFactory, HasPostingLifecycle;

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
}
