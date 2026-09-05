<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_return_id', 'item_id', 'exp_date', 'qty', 'sell_price', 'mrp', 'disc_percent',
        'disc_amount', 'gst_percent', 'gst_tax_amount', 'net_amount',
    ];

    protected $casts = [
        'exp_date' => 'date',
    ];

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
