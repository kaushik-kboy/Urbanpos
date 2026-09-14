<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBillPayment extends Model
{
    use HasFactory;

    protected $fillable = ['sales_bill_id', 'tender_type_id', 'tender_type_value_id', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function salesBill(): BelongsTo
    {
        return $this->belongsTo(SalesBill::class);
    }

    public function tenderType(): BelongsTo
    {
        return $this->belongsTo(TenderType::class);
    }

    public function tenderTypeValue(): BelongsTo
    {
        return $this->belongsTo(TenderTypeValue::class);
    }
}
