<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstTax extends Model
{
    use HasFactory;

    protected $fillable = ['description', 'percentage', 'status'];

    protected $casts = [
        'percentage' => 'decimal:2',
        'status' => 'boolean',
    ];
}
