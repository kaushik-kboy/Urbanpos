<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_return_id',
        'item_id',
        'exp_date',
        'qty',
        'cost_price',
        'disc_percent',
        'disc_amount',
        'gst_percent',
        'gst_tax_amount',
        'cgst_amount',
        'sgst_amount',
        'igst_amount',
        'net_amount',
        'cost_at_return',
    ];

    protected $casts = [
        'exp_date' => 'date',
        'qty' => 'float',
        'cost_price' => 'float',
        'disc_percent' => 'float',
        'disc_amount' => 'float',
        'gst_percent' => 'float',
        'gst_tax_amount' => 'float',
        'cgst_amount' => 'float',
        'sgst_amount' => 'float',
        'igst_amount' => 'float',
        'net_amount' => 'float',
        'cost_at_return' => 'float',
    ];

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
