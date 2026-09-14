<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'branch_id',
        'quantity',
        'cost_price',
        'landing_cost',
        'sell_price',
        'mrp',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'cost_price' => 'decimal:2',
        'landing_cost' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'mrp' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @deprecated Legacy generic mutation path, kept only for call sites not yet
     * retrofitted onto StockLedgerService (see app/Services/Inventory/StockLedgerService.php).
     * Forwards to the ledger service so every stock change — even from an un-migrated
     * caller — still produces an auditable stock_ledger row, tagged CORRECTION with no
     * source-document reference since this path has no document context to supply.
     * Retrofitted controllers should call StockLedgerService::post() directly instead.
     */
    public static function adjust(int $itemId, int $branchId, float $delta): void
    {
        app(\App\Services\Inventory\StockLedgerService::class)->post(
            itemId: $itemId,
            branchId: $branchId,
            movementType: 'CORRECTION',
            qtyDelta: $delta,
            unitCost: null,
            referenceType: null,
            referenceId: null,
            documentDate: now()->toDateString(),
        );
    }
}
