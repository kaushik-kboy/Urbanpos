<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomFieldDefinition extends Model
{
    use HasFactory;

    protected $table = 'custom_field_definitions';

    protected $fillable = [
        'module',
        'field_name',
        'field_key',
        'field_type',
        'options',
        'default_value',
        'is_required',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
        'status' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function values()
    {
        return $this->hasMany(CustomFieldValue::class, 'custom_field_definition_id');
    }

    /**
     * Scope for active fields ordered by sort_order.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Scope for specific module.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Generate unique slug key from field name.
     */
    public static function generateKey(string $module, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name, '_');
        if (empty($base)) {
            $base = 'custom_field';
        }

        $key = $base;
        $counter = 1;

        while (static::where('module', $module)->where('field_key', $key)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $key = $base . '_' . $counter;
            $counter++;
        }

        return $key;
    }
}
