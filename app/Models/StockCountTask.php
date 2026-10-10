<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class StockCountTask extends Model
{
    protected $fillable = [
        'branch_id',
        'title',
        'section',
        'category_id',
        'category_value_id',
        'brand_id',
        'assigned_to',
        'assigned_by',
        'due_date',
        'priority',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'priority' => 'string',
            'status' => 'string',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    /**
     * The product category the task is scoped to (items.category_value_id), i.e. the
     * same category concept the rest of the POS filters products by. See migration
     * 2026_10_10_010007 for why this is distinct from category() (a category HEAD).
     */
    public function categoryValue(): BelongsTo
    {
        return $this->belongsTo(ItemCategoryValue::class, 'category_value_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Authoritative MULTI-value category scope (business rule 2026-10-10: a task may
     * target more than one category, but only when explicitly selected). The singular
     * category_value_id column is kept in sync when exactly one is selected, for
     * backward compatibility with existing single-category queries/tests.
     */
    public function categoryValues(): BelongsToMany
    {
        return $this->belongsToMany(ItemCategoryValue::class, 'stock_count_task_category_values', 'task_id', 'category_value_id');
    }

    /** Authoritative MULTI-value brand scope — see categoryValues() docblock. */
    public function brands(): BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'stock_count_task_brands', 'task_id', 'brand_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountTaskItem::class, 'task_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(InventoryObservation::class, 'task_id');
    }
}
