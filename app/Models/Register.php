<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Register extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id', 'name', 'status', 'product_type', 'inv_seq_no', 'device_id',
        'online_sales_allowed', 'register_prefix',
    ];

    protected $casts = [
        'online_sales_allowed' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
