<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'app_access', 'enable_loyalty', 'discount_percent', 'business_type', 'status',
    ];

    protected $casts = [
        'app_access' => 'boolean',
        'enable_loyalty' => 'boolean',
        'discount_percent' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
