<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\ReplenishmentItemMetric;
use App\Models\ReplenishmentSalesRule;
use App\Models\ReplenishmentStockRule;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Services\Accounting\DocumentNumberingService;
use App\Services\ReplenishmentCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * 1-Click Purchase Order Generation Pipeline:
     * Seamlessly hooks into existing PurchaseOrder & PurchaseOrderItem models.
     */
    public function generatePo(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'branch_id' => 'required|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $supplierId = (int) $validated['supplier_id'];
        $branchId = (int) $validated['branch_id'];
        $today = now()->format('Y-m-d');

        return DB::transaction(function () use ($supplierId, $branchId, $validated, $today) {
            $poNumber = app(DocumentNumberingService::class)->generate('purchase_order', $branchId, $today);

            $itemIds = collect($validated['items'])->pluck('item_id')->unique();
            $itemsMap = Item::whereIn('id', $itemIds)->with('gstTax:id,percentage')->get()->keyBy('id');

            $totalQty = 0;
            $totalGst = 0;
            $grandTotal = 0;
            $poLines = [];

            foreach ($validated['items'] as $line) {
                $item = $itemsMap[$line['item_id']] ?? null;
                if (! $item) {
                    continue;
                }

                $qty = (int) $line['qty'];
                $costPrice = (float) $item->cost_price;
                $sellPrice = (float) $item->sell_price;
                $mrp = (float) $item->mrp;
                $gstPercent = (float) ($item->gstTax?->percentage ?? 0);

                $netAmount = round($qty * $costPrice, 2);
                $gstTaxAmount = round($netAmount * ($gstPercent / 100), 2);
                $lineTotal = $netAmount + $gstTaxAmount;

                $totalQty += $qty;
                $totalGst += $gstTaxAmount;
                $grandTotal += $lineTotal;

                $poLines[] = [
                    'item_id' => $item->id,
                    'qty' => $qty,
                    'free_qty' => 0,
                    'cost_price' => $costPrice,
                    'effective_cost' => $costPrice,
                    'sell_price' => $sellPrice,
                    'mrp' => $mrp,
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => $gstPercent,
                    'gst_tax_amount' => $gstTaxAmount,
                    'net_amount' => $netAmount,
                    'received_qty' => 0,
                ];
            }

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $poNumber,
                'po_date' => $today,
                'supplier_id' => $supplierId,
                'branch_id' => $branchId,
                'purchase_type' => 'Local',
                'c_form' => 'No Forms',
                'total_qty' => $totalQty,
                'total_gst' => $totalGst,
                'total' => $grandTotal,
                'status' => 'Open',
                'remarks' => 'Generated automatically by Smart Replenishment Engine (V1)',
            ]);

            $purchaseOrder->items()->createMany($poLines);

            return response()->json([
                'success' => true,
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'total_items' => count($poLines),
                'total_qty' => $totalQty,
                'total_amount' => $grandTotal,
                'redirect_url' => route('purchase.purchase-orders.show', $purchaseOrder->id),
                'message' => "Purchase Order {$purchaseOrder->po_number} created successfully with {$totalQty} units (Total: ₹".number_format($grandTotal, 2).')!',
            ]);
        });
    }

    /**
     * 1-Click Inter-Branch Stock Transfer Request Pipeline:
     * Seamlessly hooks into existing StockTransfer & StockTransferItem models.
     */
    public function createTransfer(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $validated = $request->validate([
            'from_branch_id' => 'required|exists:branches,id',
            'to_branch_id' => 'required|exists:branches,id|different:from_branch_id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        $fromBranchId = (int) $validated['from_branch_id'];
        $toBranchId = (int) $validated['to_branch_id'];
        $today = now()->format('Y-m-d');

        return DB::transaction(function () use ($fromBranchId, $toBranchId, $validated, $today) {
            $transferNumber = app(DocumentNumberingService::class)->generate('stock_transfer', $fromBranchId, $today);

            $itemIds = collect($validated['items'])->pluck('item_id')->unique();
            $itemsMap = Item::whereIn('id', $itemIds)->with('gstTax:id,percentage')->get()->keyBy('id');

            $totalQty = 0;
            $totalValue = 0;
            $transferLines = [];

            foreach ($validated['items'] as $line) {
                $item = $itemsMap[$line['item_id']] ?? null;
                if (! $item) {
                    continue;
                }

                $qty = (int) $line['qty'];
                $unitCost = (float) $item->cost_price;
                $sellPrice = (float) $item->sell_price;
                $mrp = (float) $item->mrp;
                $gstPercent = (float) ($item->gstTax?->percentage ?? 0);

                $taxableValue = round($qty * $unitCost, 2);
                $gstAmount = round($taxableValue * ($gstPercent / 100), 2);
                $cgstAmount = round($gstAmount / 2, 2);
                $sgstAmount = $gstAmount - $cgstAmount;

                $totalQty += $qty;
                $totalValue += $taxableValue;

                $transferLines[] = [
                    'item_id' => $item->id,
                    'batch_no' => 'NA',
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'sell_price' => $sellPrice,
                    'mrp' => $mrp,
                    'received_qty' => 0,
                    'gst_percent' => $gstPercent,
                    'taxable_value' => $taxableValue,
                    'gst_tax_amount' => $gstAmount,
                    'cgst_amount' => $cgstAmount,
                    'sgst_amount' => $sgstAmount,
                    'igst_amount' => 0,
                ];
            }

            $stockTransfer = StockTransfer::create([
                'transfer_number' => $transferNumber,
                'transfer_date' => $today,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'status' => 'Dispatched',
                'remarks' => 'Automated Inter-Branch transfer recommended by Smart Replenishment Engine (V1)',
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'dispatched_at' => now(),
            ]);

            $stockTransfer->items()->createMany($transferLines);

            return response()->json([
                'success' => true,
                'transfer_id' => $stockTransfer->id,
                'transfer_number' => $stockTransfer->transfer_number,
                'total_items' => count($transferLines),
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'redirect_url' => route('inventory.stock-transfers.show', $stockTransfer->id),
                'message' => "Stock Transfer {$stockTransfer->transfer_number} dispatched successfully with {$totalQty} units!",
            ]);
        });
    }
}
