<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number', 'po_date', 'supplier_id', 'branch_id', 'purchase_type', 'c_form',
        'item_disc_amount', 'disc_percent', 'disc_amount', 'freight', 'round_off',
        'scheme_item_disc_amt', 'other_disc_amt', 'total_gst', 'total_extra_cess',
        'total_qty', 'total_weight', 'total', 'remarks', 'message', 'status',
    ];

    protected $casts = [
        'po_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }
}
