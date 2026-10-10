<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpiryBucketSetting extends Model
{
    protected $fillable = [
        'label',
        'min_days',
        'max_days',
        'severity',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_days' => 'integer',
            'max_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
