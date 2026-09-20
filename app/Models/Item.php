<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Item extends Model
{
    use HasFactory, \App\Traits\HasCustomFields;

    protected $fillable = [
        'item_code', 'ean_upc_code', 'name', 'alias', 'brand_id', 'supplier_id', 'product_type',
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

    protected static function booted(): void
    {
        static::saving(function (Item $item) {
            // If cost_price is null/empty, fallback to landing_cost or 0
            if ($item->cost_price === null || $item->cost_price === '') {
                $item->cost_price = ($item->landing_cost !== null && $item->landing_cost !== '') ? $item->landing_cost : 0;
            }
            // If landing_cost is null/empty, fallback to cost_price or 0
            if ($item->landing_cost === null || $item->landing_cost === '') {
                $item->landing_cost = ($item->cost_price !== null && $item->cost_price !== '') ? $item->cost_price : 0;
            }
            // If sell_price is null/empty, fallback to mrp or 0
            if ($item->sell_price === null || $item->sell_price === '') {
                $item->sell_price = ($item->mrp !== null && $item->mrp !== '') ? $item->mrp : 0;
            }
            // If mrp is null/empty, fallback to sell_price or 0
            if ($item->mrp === null || $item->mrp === '') {
                $item->mrp = ($item->sell_price !== null && $item->sell_price !== '') ? $item->sell_price : 0;
            }

            // Fallbacks for enum / boolean fields
            $item->product_type = $item->product_type ?: 'Standard';
            $item->status = $item->status ?? true;
            $item->store_pickup = $item->store_pickup ?? false;
            $item->tax_inclusive = $item->tax_inclusive ?? false;
            $item->batch_expiry_details = $item->batch_expiry_details ?: 'Not Required';
            $item->allow_negative_stock = $item->allow_negative_stock ?? false;

            // Nullable integer, foreign key, and string fields: convert empty strings to null
            $nullableAttributes = [
                'brand_id',
                'supplier_id',
                'department_value_id',
                'category_value_id',
                'brand_value_id',
                'gst_tax_id',
                'shelf_life_days',
                'minimum_shelf_life_days',
                'hsn_code',
                'alias',
                'ean_upc_code',
                'item_code',
            ];
            foreach ($nullableAttributes as $attr) {
                if ($item->$attr === '' || (is_string($item->$attr) && trim($item->$attr) === '')) {
                    $item->$attr = null;
                }
            }
        });
    }

    public static function generateUniqueEanUpc(): string
    {
        do {
            $digits = '890' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int)$digits[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $checkDigit = (10 - ($sum % 10)) % 10;
            $code = $digits . $checkDigit;
        } while (self::where('ean_upc_code', $code)->exists());

        return $code;
    }

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

    public function stocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ItemStock::class);
    }
}
