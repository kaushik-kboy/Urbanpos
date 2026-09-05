<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCategoryValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_category_id', 'name', 'show_in_webstore', 'status',
        'sellquick_applicable', 'allowed_qty_ml',
    ];

    protected $casts = [
        'show_in_webstore' => 'boolean',
        'status' => 'boolean',
        'sellquick_applicable' => 'boolean',
    ];

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }
}
