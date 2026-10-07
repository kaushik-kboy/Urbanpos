<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    use HasFactory, \App\Traits\HasCustomFields;

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

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    public function ledger(): HasOne
    {
        return $this->hasOne(Ledger::class);
    }

    /**
     * Determine if supplier is outside Gujarat / interstate (subject to IGST).
     */
    public function isInterstate(?string $destinationState = 'Gujarat'): bool
    {
        if (in_array(strtolower((string) $this->purchase_type), ['interstate', 'import'])) {
            return true;
        }

        // Check GSTIN 2-digit state prefix (Gujarat state code is 24)
        $gstNo = trim((string) $this->gst_no);
        if (strlen($gstNo) >= 2 && ctype_digit(substr($gstNo, 0, 2))) {
            $stateCode = substr($gstNo, 0, 2);
            if ($stateCode !== '24') {
                return true;
            }
        }

        // Check state name (outside Gujarat)
        $state = trim(strtolower((string) $this->state));
        if ($state !== '' && !in_array($state, ['gujarat', 'gj', 'guj'])) {
            return true;
        }

        return false;
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
