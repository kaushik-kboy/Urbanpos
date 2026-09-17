<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormFieldValidation extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_key',
        'field_name',
        'field_label',
        'field_type',
        'is_required',
        'is_readonly',
        'block_future_date',
        'is_unique',
        'custom_error_message',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'block_future_date' => 'boolean',
        'is_unique' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeForModule($query, string $moduleKey)
    {
        return $query->where('module_key', $moduleKey)->orderBy('sort_order');
    }
}
