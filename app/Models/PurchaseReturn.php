<?php

namespace App\Models;

use App\Concerns\HasPostingLifecycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use HasFactory, HasPostingLifecycle;

    protected $fillable = [
        'return_number',
        'return_date',
        'supplier_id',
        'branch_id',
        'purchase_invoice_id',
        'supplier_debit_note_no',
        'supplier_debit_note_date',
        'purchase_type',
        'item_disc_amount',
        'disc_percent',
        'disc_amount',
        'round_off',
        'total_gst',
        'total_cgst',
        'total_sgst',
        'total_igst',
        'total',
        'remarks',
        'status',
        'posting_key',
    ];

    protected $casts = [
        'return_date' => 'date',
        'supplier_debit_note_date' => 'date',
        'item_disc_amount' => 'float',
        'disc_percent' => 'float',
        'disc_amount' => 'float',
        'round_off' => 'float',
        'total_gst' => 'float',
        'total_cgst' => 'float',
        'total_sgst' => 'float',
        'total_igst' => 'float',
        'total' => 'float',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
