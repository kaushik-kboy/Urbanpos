<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'is_mandatory', 'status'];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'status' => 'boolean',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ItemCategoryValue::class);
    }
}
