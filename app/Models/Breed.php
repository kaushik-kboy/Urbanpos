<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Breed extends Model
{
    use HasFactory;

    protected $fillable = ['pet_type_id', 'name', 'status'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function petType(): BelongsTo
    {
        return $this->belongsTo(PetType::class);
    }
}
