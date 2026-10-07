<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySalesSummary extends Model
{
    use HasFactory;

    protected $table = 'daily_sales_summaries';

    protected $guarded = ['id'];

    protected $casts = [
        'summary_date' => 'date',
        'bill_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'disc_amount' => 'decimal:2',
        'scheme_amount' => 'decimal:2',
        'cash' => 'decimal:2',
        'card' => 'decimal:2',
        'cheque' => 'decimal:2',
        'coupon' => 'decimal:2',
        'wallet_amt' => 'decimal:2',
        'credit' => 'decimal:2',
        'due' => 'decimal:2',
        'compliment' => 'decimal:2',
        'approval' => 'decimal:2',
        'advance_adjusted' => 'decimal:2',
        'rounded_off' => 'decimal:2',
        'profit' => 'decimal:2',
        'item_discount' => 'decimal:2',
        'bill_discount' => 'decimal:2',
        'freight' => 'decimal:2',
        'total_bills' => 'integer',
        'gst_tax_amt' => 'decimal:2',
        'sgst_tax_amt' => 'decimal:2',
        'cgst_tax_amt' => 'decimal:2',
        'igst_tax_amt' => 'decimal:2',
        'gst_cess_amt' => 'decimal:2',
        'redeemed_point' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
