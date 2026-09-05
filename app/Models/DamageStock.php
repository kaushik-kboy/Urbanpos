<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'damage_number', 'branch_id', 'entry_date', 'wastage_type', 'total_qty', 'total_cost',
        'remarks', 'message',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DamageStockItem::class);
    }
}
