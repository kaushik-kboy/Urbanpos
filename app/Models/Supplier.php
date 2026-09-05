<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'currency', 'purchase_type', 'purchase_mode', 'credit_limit', 'credit_balance',
        'credit_days', 'status', 'gst_type', 'mail_type', 'address', 'city', 'postal_code',
        'state', 'country', 'phone', 'email', 'mobile', 'aadhar_no', 'pan_no', 'gst_no',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function ledger(): HasOne
    {
        return $this->hasOne(Ledger::class);
    }

    protected static function booted(): void
    {
        static::created(function (Supplier $supplier) {
            Ledger::create([
                'name' => $supplier->name,
                'ledger_group' => 'Sundry Creditors',
                'supplier_id' => $supplier->id,
            ]);
        });
    }
}
