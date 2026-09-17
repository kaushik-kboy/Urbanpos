<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class FunctionKeyMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_key',
        'action_title',
        'shortcut_combination',
        'scope',
        'target_url',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function getActiveMappings()
    {
        return Cache::remember('active_function_key_mappings', 86400, function () {
            return self::where('is_enabled', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('active_function_key_mappings');
    }
}
