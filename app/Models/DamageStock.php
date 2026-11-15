<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageStock extends Model
{
    use HasFactory, HasPostingLifecycle, \App\Traits\HasCustomFields;

    protected $fillable = [
        'damage_number', 'branch_id', 'entry_date', 'wastage_type', 'total_qty', 'total_cost',
        'remarks', 'message', 'status', 'created_by', 'approved_by', 'posting_key',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DamageStockItem::class);
    }
}
