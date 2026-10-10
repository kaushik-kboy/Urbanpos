<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class BarcodeMappingRequest extends Model
{
    protected $fillable = [
        'scanned_barcode',
        'suggested_item_id',
        'branch_id',
        'status',
        'submitted_by',
        'reviewed_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    public function suggestedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'suggested_item_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
