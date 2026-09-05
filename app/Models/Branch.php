<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'address_line1', 'address_line2', 'city', 'postal_code', 'state', 'country',
        'contact_person', 'phone', 'email', 'mobile', 'language', 'area_code', 'circle_code',
        'business_type', 'webstore', 'erp_code', 'country_code', 'license_id', 'cst',
        'website_link', 'social_media_link', 'enable_thirdparty_loyalty',
        'gst_no', 'pan_no', 'gst_type', 'gst_filing', 'status',
    ];

    protected $casts = [
        'webstore' => 'boolean',
        'enable_thirdparty_loyalty' => 'boolean',
        'status' => 'boolean',
    ];
}
