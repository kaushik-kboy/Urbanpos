<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillSettlement extends Model
{
    use HasFactory;

    protected $fillable = [
        'settlement_number',
        'settlement_type',
        'customer_id',
        'supplier_id',
        'branch_id',
        'settlement_date',
        'total_amount',
        'discount_amount',
        'payment_mode',
        'bank_ledger_id',
        'reference_no',
        'remarks',
        'journal_entry_id',
        'status',
        'cancelled_at',
        'cancellation_reason',
        'cancelled_by_id',
    ];

    protected $casts = [
        'settlement_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'bank_ledger_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillSettlementItem::class);
    }
}
