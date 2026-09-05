<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id', 'item_id', 'exp_date', 'qty', 'free_qty', 'cost_price',
        'sell_price', 'mrp', 'disc_percent', 'disc_amount', 'gst_percent', 'gst_tax_amount',
        'net_amount',
    ];

    protected $casts = [
        'exp_date' => 'date',
    ];

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
