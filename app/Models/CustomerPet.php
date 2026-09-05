<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPet extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id', 'pet_type_id', 'breed_id', 'color_id', 'name', 'gender', 'age',
        'remarks', 'birth_date',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function petType(): BelongsTo
    {
        return $this->belongsTo(PetType::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }
}
