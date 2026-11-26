<?php

namespace App\Models;

use App\Support\PetBirthDate;
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

    /**
     * Get birth_date as a PetBirthDate instance (supports DD-MM-YYYY and MM-YYYY)
     */
    public function getBirthDateAttribute($value): ?PetBirthDate
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new PetBirthDate($value);
    }

    /**
     * Clean and store birth date string
     */
    public function setBirthDateAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['birth_date'] = null;
            return;
        }

        $cleaned = trim(str_replace(['/', '.'], '-', (string) $value));
        // If YYYY-MM-DD was passed, convert to DD-MM-YYYY
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $cleaned, $m)) {
            $cleaned = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $this->attributes['birth_date'] = $cleaned;
    }

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
