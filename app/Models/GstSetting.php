<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'gstin',
        'username',
        'password',
        'client_id',
        'client_secret',
        'gsp_provider',
        'auto_upload_threshold',
        'auto_upload_enabled',
        'is_sandbox',
    ];

    protected $casts = [
        'auto_upload_threshold' => 'decimal:2',
        'auto_upload_enabled' => 'boolean',
        'is_sandbox' => 'boolean',
    ];

    /**
     * Get or create the singleton GST settings row.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'gstin' => '24AAECU3183G1ZN',
            'username' => 'admin1',
            'gsp_provider' => 'mock',
            'auto_upload_threshold' => 50000.00,
            'auto_upload_enabled' => true,
            'is_sandbox' => true,
        ]);
    }
}
