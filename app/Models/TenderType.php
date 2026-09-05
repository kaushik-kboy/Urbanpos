<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenderType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'status', 'type', 'mode', 'service_applicable', 'mandate_refno',
        'service_charge_perc', 'branch_id',
    ];

    protected $casts = [
        'status' => 'boolean',
        'service_applicable' => 'boolean',
        'mandate_refno' => 'boolean',
        'service_charge_perc' => 'decimal:2',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(TenderTypeValue::class);
    }
}
