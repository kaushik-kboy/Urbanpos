<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderTypeValue extends Model
{
    use HasFactory;

    protected $fillable = ['tender_type_id', 'name', 'status', 'group_ledger', 'branch_id'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function tenderType(): BelongsTo
    {
        return $this->belongsTo(TenderType::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
