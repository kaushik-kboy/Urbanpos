<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_number', 'branch_id', 'entry_date', 'total_qty', 'total', 'remarks', 'message',
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
        return $this->hasMany(OpeningStockItem::class);
    }
}
