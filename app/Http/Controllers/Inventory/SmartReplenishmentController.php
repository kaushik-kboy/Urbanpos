<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\ItemCategory;
use App\Models\ReplenishmentItemMetric;
use App\Models\ReplenishmentSalesRule;
use App\Models\ReplenishmentStockRule;
use App\Models\Supplier;
use App\Services\ReplenishmentCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmartReplenishmentController extends Controller
{
    protected ReplenishmentCalculationService $service;

    public function __construct(ReplenishmentCalculationService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the Smart Replenishment Studio Dashboard.
     */
    public function index(Request $request): View
    {
        if (! config('replenishment.enabled', false)) {
            abort(404, 'Smart Replenishment is not enabled in this environment.');
        }

        $branches = Branch::where('status', true)->get();
        $activeBranchId = $request->filled('branch_id') && $request->get('branch_id') !== 'all'
            ? (int) $request->get('branch_id')
            : ($branches->first()->id ?? 1);

        $summary = $this->service->getDashboardSummary($activeBranchId);
        $transferRecommendations = $this->service->getTransferRecommendations($activeBranchId, 10);
        $supplierOrders = $this->service->getSupplierOrderRecommendations($activeBranchId)->take(6);

        $brands = Brand::orderBy('name')->get(['id', 'name']);
        $categories = ItemCategory::orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $salesRules = ReplenishmentSalesRule::orderBy('sort_order')->get();
        $stockRules = ReplenishmentStockRule::orderBy('sort_order')->get();

        return view('inventory.replenishment.index', compact(
            'branches',
            'activeBranchId',
            'summary',
            'transferRecommendations',
            'supplierOrders',
            'brands',
            'categories',
            'suppliers',
            'salesRules',
            'stockRules'
        ));
    }

    /**
     * Ajax Server-side Data endpoint for responsive data table.
     */
    public function data(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $branchId = $request->get('branch_id');
        $status = $request->get('status');
        $brandId = $request->get('brand_id');
        $categoryId = $request->get('category_id');
        $supplierId = $request->get('supplier_id');
        $search = $request->get('search');

        $query = ReplenishmentItemMetric::with([
            'item:id,name,item_code,brand_id,category_id,supplier_id,cost_price,sell_price',
            'item.brand:id,name',
            'item.category:id,name',
            'supplier:id,name',
            'branch:id,name',
        ]);

        if ($branchId && $branchId !== 'all') {
            $query->where('branch_id', $branchId);
        }

        if ($status && $status !== 'all') {
            if ($status === 'needs_action') {
                $query->where(function ($q) {
                    $q->where('suggested_qty', '>', 0)
                        ->orWhere(function ($sub) {
                            $sub->whereIn('stock_status', ['Critical', 'Low'])
                                ->where('daily_avg_sales', '>', 0);
                        });
                });
            } else {
                $query->where('stock_status', $status);
            }
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('primary_supplier_id', $supplierId);
        }

        if ($brandId && $brandId !== 'all') {
            $query->whereHas('item', function ($q) use ($brandId) {
                $q->where('brand_id', $brandId);
            });
        }

        if ($categoryId && $categoryId !== 'all') {
            $query->whereHas('item', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($search) {
            $query->whereHas('item', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('alias', 'like', "%{$search}%");
            });
        }

        // Sorting: Prioritize items needing urgent action first
        $query->orderByDesc('suggested_qty')
            ->orderBy('cover_days', 'asc');

        $paginator = $query->paginate($request->get('per_page', 25));

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    /**
     * Trigger on-demand sync & metric calculation.
     */
    public function sync(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $branchId = $request->get('branch_id');
        if ($branchId && $branchId !== 'all') {
            $count = $this->service->calculateForBranch((int) $branchId);
            $message = "Successfully recalculated metrics for {$count} items in selected branch.";
        } else {
            $results = $this->service->calculateAll();
            $totalCount = array_sum(array_column($results, 'items_processed'));
            $message = "Successfully recalculated metrics across all branches ({$totalCount} item records).";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'summary' => $this->service->getDashboardSummary($branchId && $branchId !== 'all' ? (int) $branchId : null),
        ]);
    }

    /**
     * Update Replenishment Rules & Settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $salesRulesData = $request->input('sales_rules', []);
        foreach ($salesRulesData as $ruleId => $targetDays) {
            ReplenishmentSalesRule::where('id', $ruleId)->update([
                'target_cover_days' => max(1, (int) $targetDays),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Replenishment rules and target days updated successfully!',
        ]);
    }

    /**
     * View all Inter-Branch Transfer Recommendations.
     */
    public function transfers(Request $request): View
    {
        if (! config('replenishment.enabled', false)) {
            abort(404);
        }

        $branches = Branch::where('status', true)->get();
        $activeBranchId = $request->filled('branch_id') && $request->get('branch_id') !== 'all'
            ? (int) $request->get('branch_id')
            : null;

        $transferRecommendations = $this->service->getTransferRecommendations($activeBranchId, 200);

        return view('inventory.replenishment.transfers', compact('branches', 'activeBranchId', 'transferRecommendations'));
    }

    /**
     * View all Supplier-wise PO Recommendations.
     */
    public function supplierOrders(Request $request): View
    {
        if (! config('replenishment.enabled', false)) {
            abort(404);
        }

        $branches = Branch::where('status', true)->get();
        $activeBranchId = $request->filled('branch_id') && $request->get('branch_id') !== 'all'
            ? (int) $request->get('branch_id')
            : null;

        $supplierOrders = $this->service->getSupplierOrderRecommendations($activeBranchId);

        return view('inventory.replenishment.supplier_orders', compact('branches', 'activeBranchId', 'supplierOrders'));
    }
}
