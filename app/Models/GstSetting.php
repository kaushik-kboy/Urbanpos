<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'gstin',
        'username',
        'password',
        'client_id',
        'client_secret',
        'gsp_provider',
        'auto_upload_threshold',
        'auto_upload_enabled',
        'auto_upload_scope',
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
            'company_name' => 'URBANPETS SERVICES PRIVATE LIMITED',
            'gstin' => '24AAECU0338G1ZN',
            'username' => 'API_Urbanpets1',
            'gsp_provider' => 'mock',
            'auto_upload_threshold' => 50000.00,
            'auto_upload_enabled' => true,
            'auto_upload_scope' => 'both',
            'is_sandbox' => true,
        ]);
    }
}
