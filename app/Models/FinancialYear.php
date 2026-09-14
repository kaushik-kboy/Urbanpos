<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'start_date', 'end_date', 'is_locked', 'locked_by_id', 'locked_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    public static function containing(string $date): ?self
    {
        return static::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }
}
