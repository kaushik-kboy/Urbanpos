<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class StockCountTaskItem extends Model
{
    protected $fillable = [
        'task_id',
        'item_id',
        'batch_no',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(StockCountTask::class, 'task_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
