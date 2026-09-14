<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TillSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'register_id', 'branch_id', 'user_id', 'opening_cash', 'opened_at', 'status',
        'expected_cash', 'actual_cash', 'variance', 'closed_by_id', 'closed_at',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'variance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(TillCashMovement::class);
    }

    public function salesBills(): HasMany
    {
        return $this->hasMany(SalesBill::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }
}
