<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'ean_upc_code', 'name', 'alias', 'brand_id', 'supplier_id', 'product_type',
        'cost_price', 'landing_cost', 'sell_price', 'mrp', 'status', 'store_pickup',
        'tax_inclusive', 'batch_expiry_details', 'shelf_life_days', 'minimum_shelf_life_days',
        'allow_negative_stock', 'department_value_id', 'category_value_id', 'brand_value_id',
        'gst_tax_id', 'hsn_code',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'landing_cost' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'mrp' => 'decimal:2',
        'status' => 'boolean',
        'store_pickup' => 'boolean',
        'tax_inclusive' => 'boolean',
        'allow_negative_stock' => 'boolean',
    ];

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function departmentValue(): BelongsTo
    {
        return $this->belongsTo(ItemCategoryValue::class, 'department_value_id');
    }

    public function categoryValue(): BelongsTo
    {
        return $this->belongsTo(ItemCategoryValue::class, 'category_value_id');
    }

    public function brandValue(): BelongsTo
    {
        return $this->belongsTo(ItemCategoryValue::class, 'brand_value_id');
    }

    public function gstTax(): BelongsTo
    {
        return $this->belongsTo(GstTax::class);
    }
}
