<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'name', 'customer_category_id', 'customer_code', 'sales_type', 'payment_mode',
        'credit_limit', 'credit_balance', 'monthly_credit_balance', 'credit_days', 'branch_id',
        'status', 'sales_formula', 'gst_type', 'sms_consent',
        'address1', 'area_id', 'city', 'state', 'country', 'postal_code', 'std_code', 'phone',
        'email', 'remarks', 'gst_no', 'aadhar_no', 'pan_no', 'mobile',
        'gender', 'exempted_reason', 'customer_type',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'monthly_credit_balance' => 'decimal:2',
        'status' => 'boolean',
        'sms_consent' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerCategory::class, 'customer_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(CustomerPet::class);
    }

    public function ledger(): HasOne
    {
        return $this->hasOne(Ledger::class);
    }

    protected static function booted(): void
    {
        static::created(function (Customer $customer) {
            Ledger::create([
                'name' => $customer->name,
                'ledger_group' => 'Sundry Debtors',
                'customer_id' => $customer->id,
            ]);
        });
    }
}
