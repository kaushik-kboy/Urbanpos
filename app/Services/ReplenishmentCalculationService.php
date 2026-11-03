<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ReplenishmentItemMetric;
use App\Models\ReplenishmentSalesRule;
use App\Models\ReplenishmentStockRule;
use App\Models\SalesBillItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReplenishmentCalculationService
{
    /**
     * Window in days used for sales velocity calculations.
     */
    protected int $windowDays;

    public function __construct()
    {
        $this->windowDays = config('replenishment.velocity_days', 30);
    }

    /**
     * Calculate and cache metrics for all active branches.
     */
    public function calculateAll(): array
    {
        $branches = Branch::where('status', true)->get();
        $results = [];

        foreach ($branches as $branch) {
            $updated = $this->calculateForBranch($branch->id);
            $results[$branch->id] = [
                'branch_name' => $branch->name,
                'items_processed' => $updated,
            ];
        }

        return $results;
    }

    /**
     * Calculate and cache metrics for a specific branch.
     */
    public function calculateForBranch(int $branchId, ?int $itemId = null): int
    {
        $salesRules = ReplenishmentSalesRule::where('is_active', true)
            ->orderBy('min_daily_avg', 'desc')
            ->get();

        $stockRules = ReplenishmentStockRule::where('is_active', true)
            ->orderBy('min_cover_days', 'desc')
            ->get();

        $sinceDate = now()->subDays($this->windowDays)->startOfDay();

        // 1. Fetch sales aggregation in last 30 days for this branch
        $salesQuery = SalesBillItem::join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->where('sales_bills.branch_id', $branchId)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->where('sales_bills.bill_date', '>=', $sinceDate)
            ->selectRaw('sales_bill_items.item_id, SUM(sales_bill_items.qty) as total_sold')
            ->groupBy('sales_bill_items.item_id');

        if ($itemId) {
            $salesQuery->where('sales_bill_items.item_id', $itemId);
        }

        $salesMap = $salesQuery->pluck('total_sold', 'item_id');

        // 2. Fetch current branch stock
        $stocksQuery = ItemStock::where('branch_id', $branchId);
        if ($itemId) {
            $stocksQuery->where('item_id', $itemId);
        }
        $currentStocksMap = $stocksQuery->pluck('quantity', 'item_id');

        // 3. Fetch other branches' combined stock
        $otherStocksQuery = ItemStock::where('branch_id', '!=', $branchId)
            ->selectRaw('item_id, SUM(quantity) as other_qty')
            ->groupBy('item_id');
        if ($itemId) {
            $otherStocksQuery->where('item_id', $itemId);
        }
        $otherStocksMap = $otherStocksQuery->pluck('other_qty', 'item_id');

        // 4. Query active items
        $itemsQuery = Item::where('status', true)->select('id', 'supplier_id', 'cost_price');
        if ($itemId) {
            $itemsQuery->where('id', $itemId);
        }

        $now = now();
        $totalProcessed = 0;

        $itemsQuery->chunk(500, function ($items) use (
            $branchId,
            $salesMap,
            $currentStocksMap,
            $otherStocksMap,
            $salesRules,
            $stockRules,
            $now,
            &$totalProcessed
        ) {
            $upsertData = [];

            foreach ($items as $item) {
                $curStock = (float) ($currentStocksMap[$item->id] ?? 0);
                $othStock = (float) ($otherStocksMap[$item->id] ?? 0);
                $sold30d = (float) ($salesMap[$item->id] ?? 0);

                $dailyAvg = round($sold30d / $this->windowDays, 2);

                // Sales Rule & Target Days
                $salesRule = $this->matchSalesRule($dailyAvg, $salesRules);
                $targetDays = $salesRule ? $salesRule->target_cover_days : 7;
                $salesType = $salesRule ? $salesRule->rule_name : 'Dead Stock';

                // Cover Days & Suggested Order Qty
                if ($dailyAvg > 0) {
                    $coverDays = max(0.0, round($curStock / $dailyAvg, 1));
                    $requiredStock = $dailyAvg * $targetDays;
                    $suggestedQty = (int) max(0, ceil($requiredStock - $curStock));

                    // Match Stock Status
                    $stockRule = $this->matchStockRule($coverDays, $stockRules);
                    $stockStatus = $stockRule ? $stockRule->stock_type : 'Healthy';
                } else {
                    // Avg Sales == 0
                    $suggestedQty = 0; // Note 4: If Avg Sales = 0 -> Suggested Qty = 0
                    if ($curStock > 0) {
                        $coverDays = 999.0;
                        $stockStatus = 'Overstock';
                    } elseif ($curStock < 0) {
                        $coverDays = 0.0;
                        $stockStatus = 'Critical';
                    } else {
                        $coverDays = 0.0;
                        $stockStatus = 'Dead Stock';
                    }
                }

                $upsertData[] = [
                    'item_id' => $item->id,
                    'branch_id' => $branchId,
                    'current_stock' => $curStock,
                    'other_branch_stock' => $othStock,
                    'sales_30d' => $sold30d,
                    'daily_avg_sales' => $dailyAvg,
                    'cover_days' => $coverDays,
                    'target_cover_days' => $targetDays,
                    'suggested_qty' => $suggestedQty,
                    'sales_type' => $salesType,
                    'stock_status' => $stockStatus,
                    'primary_supplier_id' => $item->supplier_id,
                    'calculated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($upsertData)) {
                ReplenishmentItemMetric::upsert(
                    $upsertData,
                    ['item_id', 'branch_id'],
                    [
                        'current_stock',
                        'other_branch_stock',
                        'sales_30d',
                        'daily_avg_sales',
                        'cover_days',
                        'target_cover_days',
                        'suggested_qty',
                        'sales_type',
                        'stock_status',
                        'primary_supplier_id',
                        'calculated_at',
                        'updated_at',
                    ]
                );
                $totalProcessed += count($upsertData);
            }
        });

        return $totalProcessed;
    }

    /**
     * Dashboard KPI summary counts.
     */
    public function getDashboardSummary(?int $branchId = null): array
    {
        $query = ReplenishmentItemMetric::query();
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $statusCounts = (clone $query)
            ->selectRaw('stock_status, COUNT(*) as aggregate')
            ->groupBy('stock_status')
            ->pluck('aggregate', 'stock_status');

        $velocityCounts = (clone $query)
            ->selectRaw('sales_type, COUNT(*) as aggregate')
            ->groupBy('sales_type')
            ->pluck('aggregate', 'sales_type');

        // Total Suggested Reorder Value (Suggested Qty * Cost Price)
        $poTotals = (clone $query)
            ->join('items', 'replenishment_item_metrics.item_id', '=', 'items.id')
            ->where('replenishment_item_metrics.suggested_qty', '>', 0)
            ->selectRaw('SUM(replenishment_item_metrics.suggested_qty * items.cost_price) as total_val, SUM(replenishment_item_metrics.suggested_qty) as total_units')
            ->first();

        // High-performance count of transfer opportunities via direct SQL join
        $transferOpportunitiesCount = $this->getTransferRecommendations($branchId)->count();

        return [
            'critical_count' => (int) ($statusCounts['Critical'] ?? 0),
            'low_count' => (int) ($statusCounts['Low'] ?? 0),
            'healthy_count' => (int) ($statusCounts['Healthy'] ?? 0),
            'overstock_count' => (int) ($statusCounts['Overstock'] ?? 0),
            'dead_stock_count' => (int) ($statusCounts['Dead Stock'] ?? 0),
            'status_chart' => [
                'Critical' => (int) ($statusCounts['Critical'] ?? 0),
                'Low' => (int) ($statusCounts['Low'] ?? 0),
                'Healthy' => (int) ($statusCounts['Healthy'] ?? 0),
                'Overstock' => (int) ($statusCounts['Overstock'] ?? 0),
                'Dead Stock' => (int) ($statusCounts['Dead Stock'] ?? 0),
            ],
            'velocity_chart' => [
                'Highest Moving' => (int) ($velocityCounts['Highest Moving'] ?? 0),
                'Fast Moving' => (int) ($velocityCounts['Fast Moving'] ?? 0),
                'Normal Moving' => (int) ($velocityCounts['Normal Moving'] ?? 0),
                'Slow Moving' => (int) ($velocityCounts['Slow Moving'] ?? 0),
                'Dead Stock' => (int) ($velocityCounts['Dead Stock'] ?? 0),
            ],
            'transfer_opportunities_count' => $transferOpportunitiesCount,
            'suggested_po_value' => round((float) ($poTotals->total_val ?? 0), 2),
            'suggested_po_units' => (int) ($poTotals->total_units ?? 0),
        ];
    }

    /**
     * Smart Inter-Branch Matchmaking:
     * Fast single SQL JOIN query matching deficit branch items with surplus branches.
     */
    public function getTransferRecommendations(?int $destBranchId = null, int $limit = 100): Collection
    {
        $query = DB::table('replenishment_item_metrics as dest')
            ->join('replenishment_item_metrics as src', function ($join) {
                $join->on('dest.item_id', '=', 'src.item_id')
                    ->whereColumn('dest.branch_id', '!=', 'src.branch_id');
            })
            ->join('items', 'dest.item_id', '=', 'items.id')
            ->join('branches as b_dest', 'dest.branch_id', '=', 'b_dest.id')
            ->join('branches as b_src', 'src.branch_id', '=', 'b_src.id')
            ->whereIn('dest.stock_status', ['Critical', 'Low'])
            ->whereIn('src.stock_status', ['Healthy', 'Overstock'])
            ->where('src.current_stock', '>', 0);

        if ($destBranchId) {
            $query->where('dest.branch_id', $destBranchId);
        }

        $rows = $query->select([
            'dest.item_id',
            'items.name as item_name',
            'items.item_code',
            'dest.branch_id as dest_branch_id',
            'b_dest.name as dest_branch_name',
            'dest.current_stock as dest_stock',
            'dest.cover_days as dest_cover_days',
            'dest.stock_status as dest_status',
            'dest.suggested_qty as dest_suggested_qty',
            'src.branch_id as source_branch_id',
            'b_src.name as source_branch_name',
            'src.current_stock as source_stock',
            'src.cover_days as source_cover_days',
            'src.stock_status as source_status',
            'src.daily_avg_sales as source_daily_avg',
            'src.target_cover_days as source_target_days',
        ])
            ->limit($limit)
            ->get();

        return $rows->map(function ($row) {
            if ($row->source_daily_avg > 0) {
                $srcRequired = $row->source_daily_avg * $row->source_target_days;
                $surplusSpare = max(0, floor($row->source_stock - $srcRequired));
                if ($surplusSpare < 1 && $row->source_stock >= 5) {
                    $surplusSpare = (int) floor($row->source_stock * 0.2);
                }
            } else {
                $surplusSpare = (int) $row->source_stock;
            }

            $needed = max(1, (int) ($row->dest_suggested_qty > 0 ? $row->dest_suggested_qty : 5));
            $transferQty = (int) min($needed, $surplusSpare);

            $row->suggested_transfer_qty = $transferQty;

            return $row;
        })->filter(function ($row) {
            return $row->suggested_transfer_qty > 0;
        })->values();
    }

    /**
     * Group recommended reorders by Supplier for 1-Click PO generation.
     */
    public function getSupplierOrderRecommendations(?int $branchId = null): Collection
    {
        $query = ReplenishmentItemMetric::with(['item:id,name,item_code,cost_price,supplier_id', 'supplier:id,name,phone'])
            ->where('suggested_qty', '>', 0);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $items = $query->get();

        return $items->groupBy('primary_supplier_id')->map(function ($group, $supplierId) {
            $supplier = $group->first()->supplier ?? null;
            $supplierName = $supplier ? $supplier->name : 'Unassigned / Direct';
            $totalUnits = $group->sum('suggested_qty');
            $totalEstimatedValue = $group->sum(function ($metric) {
                $cost = (float) ($metric->item->cost_price ?? 0);

                return $metric->suggested_qty * $cost;
            });

            return (object) [
                'supplier_id' => $supplierId ?: null,
                'supplier_name' => $supplierName,
                'items_count' => $group->count(),
                'total_units' => $totalUnits,
                'estimated_value' => round($totalEstimatedValue, 2),
                'items' => $group,
            ];
        })->sortByDesc('estimated_value')->values();
    }

    /**
     * Helper: Match daily sales to sales rules with continuous zero-gap thresholds.
     */
    protected function matchSalesRule(float $dailyAvg, Collection $salesRules): ?ReplenishmentSalesRule
    {
        $normalizedVelocity = max(0.0, $dailyAvg);
        $sorted = $salesRules->sortByDesc('min_daily_avg');

        foreach ($sorted as $rule) {
            if ($normalizedVelocity >= (float) $rule->min_daily_avg) {
                return $rule;
            }
        }

        return $sorted->last();
    }

    /**
     * Helper: Match cover days to stock rules with continuous zero-gap thresholds.
     */
    protected function matchStockRule(float $coverDays, Collection $stockRules): ?ReplenishmentStockRule
    {
        $normalizedDays = max(0.0, $coverDays);

        // Check tiers with max threshold in ascending order (Critical <= 3, Low <= 10, Healthy <= 30)
        $rulesWithMax = $stockRules->whereNotNull('max_cover_days')->sortBy('max_cover_days');

        foreach ($rulesWithMax as $rule) {
            if ($normalizedDays <= (float) $rule->max_cover_days) {
                return $rule;
            }
        }

        // Exceeds all thresholds -> Overstock (max_cover_days is null)
        return $stockRules->firstWhere('max_cover_days', null)
            ?? $stockRules->sortByDesc('min_cover_days')->first();
    }
}
