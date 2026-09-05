<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ledger extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'ledger_group', 'opening_balance', 'opening_balance_type',
        'customer_id', 'supplier_id', 'status',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Signed running balance (Debit positive, Credit negative), including opening balance.
     */
    public function balance(?string $asOf = null): float
    {
        $opening = $this->opening_balance_type === 'Debit' ? (float) $this->opening_balance : -(float) $this->opening_balance;

        $query = $this->lines();
        if ($asOf) {
            $query->whereHas('journalEntry', fn ($q) => $q->whereDate('voucher_date', '<=', $asOf));
        }

        $movement = $query->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')->value('net');

        return round($opening + (float) $movement, 2);
    }

    public static function findOrCreateSystemLedger(string $name, string $group): self
    {
        return static::firstOrCreate(['name' => $name, 'ledger_group' => $group]);
    }
}
