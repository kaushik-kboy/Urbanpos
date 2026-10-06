<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'kit_item_id',
        'name',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function kitItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'kit_item_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitRecipeItem::class);
    }
}
