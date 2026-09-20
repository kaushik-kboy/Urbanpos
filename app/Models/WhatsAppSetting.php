<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    use HasFactory;

    protected $table = 'whats_app_settings';

    protected $fillable = [
        'api_url',
        'app_key',
        'auth_key',
        'template_name',
        'template_lang',
        'header_title',
        'footer_message',
        'support_phone',
        'auto_send_on_bill',
        'is_active',
    ];

    protected $casts = [
        'auto_send_on_bill' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get or create the singleton WhatsApp settings row.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'api_url' => config('services.chatonclick.url') ?: 'https://chatonclick.com',
            'app_key' => config('services.chatonclick.appkey') ?: '01cbe6e8-abf4-449c-a40f-46e83709aa63',
            'auth_key' => config('services.chatonclick.authkey') ?: 'HrvnRKqlZGpJFwVRMDEhFXT8NStSHnmd12',
            'template_name' => config('services.chatonclick.template_name') ?: null,
            'template_lang' => config('services.chatonclick.template_lang') ?: 'en',
            'header_title' => 'URBAN PETS',
            'footer_message' => 'Have an Awesome Day!',
            'support_phone' => '7383056626',
            'auto_send_on_bill' => true,
            'is_active' => true,
        ]);
    }
}
