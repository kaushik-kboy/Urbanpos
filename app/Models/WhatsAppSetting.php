<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSetting extends Model
{
    use HasFactory;

    protected $table = 'whats_app_settings';

    protected $fillable = [
        'branch_id',
        'api_url',
        'app_key',
        'auth_key',
        'template_name',
        'template_lang',
        'header_title',
        'footer_message',
        'support_phone',
        'custom_message',
        'auto_send_on_bill',
        'is_active',
    ];

    protected $casts = [
        'auto_send_on_bill' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get or create WhatsApp settings for a specific branch (or global).
     */
    public static function forBranch(?int $branchId = null): self
    {
        $hasBranchCol = \Illuminate\Support\Facades\Schema::hasColumn('whats_app_settings', 'branch_id');
        if ($branchId && $hasBranchCol) {
            $branchSetting = static::where('branch_id', $branchId)->first();
            if ($branchSetting) {
                return $branchSetting;
            }
        }

        // Return global template if exists
        $global = $hasBranchCol ? static::whereNull('branch_id')->first() : static::first();
        if ($global) {
            return $global;
        }

        return static::current($branchId);
    }

    /**
     * Get or create the singleton / global WhatsApp settings row.
     */
    public static function current(?int $branchId = null): self
    {
        $hasBranchCol = \Illuminate\Support\Facades\Schema::hasColumn('whats_app_settings', 'branch_id');
        if ($branchId && $hasBranchCol) {
            $branchSetting = static::where('branch_id', $branchId)->first();
            if ($branchSetting) {
                return $branchSetting;
            }
        }

        $matchCriteria = $hasBranchCol ? ['branch_id' => null] : [];

        return static::firstOrCreate(
            $matchCriteria,
            [
                'api_url' => config('services.chatonclick.url') ?: 'https://chatonclick.com',
                'app_key' => config('services.chatonclick.appkey') ?: '01cbe6e8-abf4-449c-a40f-46e83709aa63',
                'auth_key' => config('services.chatonclick.authkey') ?: 'HrvnRKqlZGpJFwVRMDEhFXT8NStSHnmd12',
                'template_name' => config('services.chatonclick.template_name') ?: null,
                'template_lang' => config('services.chatonclick.template_lang') ?: 'en_US',
                'header_title' => 'URBAN PETS',
                'footer_message' => 'Have an Awesome Day!',
                'support_phone' => '7383056626',
                'custom_message' => null,
                'auto_send_on_bill' => true,
                'is_active' => true,
            ]
        );
    }
}
