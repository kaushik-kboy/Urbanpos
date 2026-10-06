<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitRecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'kit_recipe_id',
        'component_item_id',
        'qty_per_kit',
        'remarks',
    ];

    protected $casts = [
        'qty_per_kit' => 'decimal:4',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(KitRecipe::class, 'kit_recipe_id');
    }

    public function componentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
