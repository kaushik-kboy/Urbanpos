<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'contact_person',
        'designation',
        'mobile',
        'phone',
        'email',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
