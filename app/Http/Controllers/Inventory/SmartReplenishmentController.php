<?php

namespace App\Http\Controllers\Inventory;

use App\Exports\SmartReplenishmentExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
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
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SmartReplenishmentController extends Controller
{
    protected ReplenishmentCalculationService $service;

    public function __construct(ReplenishmentCalculationService $service)
    {
        $this->service = $service;
    }

    /**
     * Helper to load ItemCategoryValue options for a category head by name or aliases (matching Item Master).
     */
    private function getCategoryHeadValues(array $aliases): \Illuminate\Support\Collection
    {
        $heads = \App\Models\ItemCategory::where(function ($query) use ($aliases) {
            foreach ($aliases as $alias) {
                $query->orWhere('name', 'like', $alias);
            }
        })->get();

        if ($heads->isEmpty()) {
            return collect();
        }

        $headIds = $heads->pluck('id')->toArray();
        return ItemCategoryValue::whereIn('item_category_id', $headIds)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);
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

        // Load Department, Category, and Brands matching Item Master exactly
        $departments = $this->getCategoryHeadValues(['DEPARTMENT', 'Department', 'department', 'Departments', 'Dept', 'DEPT']);
        $categories = $this->getCategoryHeadValues(['CATEGORY', 'Category', 'category', 'Categories', 'CAT']);
        $brands = Brand::where('status', true)->orderBy('name')->get(['id', 'name']);

        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $salesRules = ReplenishmentSalesRule::orderBy('sort_order')->get();
        $stockRules = ReplenishmentStockRule::orderBy('sort_order')->get();

        return view('inventory.replenishment.index', compact(
            'branches',
            'activeBranchId',
            'summary',
            'transferRecommendations',
            'supplierOrders',
            'departments',
            'categories',
            'brands',
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
        $salesType = $request->get('sales_type');
        $action = $request->get('action');
        $brandId = $request->get('brand_id');
        $categoryId = $request->get('category_id');
        $departmentId = $request->get('department_id');
        $supplierId = $request->get('supplier_id');
        $search = $request->get('search');

        $query = ReplenishmentItemMetric::query()
            ->select('replenishment_item_metrics.*')
            ->with([
                'item:id,name,item_code,brand_id,brand_value_id,department_value_id,category_value_id,supplier_id,cost_price,sell_price',
                'item.brand:id,name',
                'item.brandValue:id,name',
                'item.departmentValue:id,name',
                'item.categoryValue:id,name',
                'supplier:id,name',
                'branch:id,name',
            ]);

        $needsItemJoin = ($brandId && $brandId !== 'all')
            || ($categoryId && $categoryId !== 'all')
            || ($departmentId && $departmentId !== 'all')
            || ! empty($search);

        if ($needsItemJoin) {
            $query->join('items', 'replenishment_item_metrics.item_id', '=', 'items.id');
        }

        if ($branchId && $branchId !== 'all') {
            $query->where('replenishment_item_metrics.branch_id', $branchId);
        }

        if ($status && $status !== 'all') {
            if ($status === 'needs_action') {
                $query->where(function ($q) {
                    $q->where('replenishment_item_metrics.suggested_qty', '>', 0)
                        ->orWhere(function ($sub) {
                            $sub->whereIn('replenishment_item_metrics.stock_status', ['Critical', 'Low'])
                                ->where('replenishment_item_metrics.daily_avg_sales', '>', 0);
                        });
                });
            } else {
                $query->where('replenishment_item_metrics.stock_status', $status);
            }
        }

        if ($salesType && $salesType !== 'all') {
            $query->where('replenishment_item_metrics.sales_type', $salesType);
        }

        if ($action && $action !== 'all') {
            if ($action === 'order') {
                $query->where('replenishment_item_metrics.suggested_qty', '>', 0);
            } elseif ($action === 'transfer') {
                $query->where('replenishment_item_metrics.other_branch_stock', '>', 0)
                    ->where(function ($q) {
                        $q->whereIn('replenishment_item_metrics.stock_status', ['Critical', 'Low'])
                            ->orWhere('replenishment_item_metrics.suggested_qty', '>', 0);
                    });
            }
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('replenishment_item_metrics.primary_supplier_id', $supplierId);
        }

        if ($departmentId && $departmentId !== 'all') {
            $query->where('items.department_value_id', $departmentId);
        }

        if ($categoryId && $categoryId !== 'all') {
            $query->where('items.category_value_id', $categoryId);
        }

        if ($brandId && $brandId !== 'all') {
            $query->where(function ($q) use ($brandId) {
                $q->where('items.brand_id', $brandId)
                    ->orWhere('items.brand_value_id', $brandId);
            });
        }

        if ($search) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('items.item_code', 'like', "{$term}%")
                    ->orWhere('items.name', 'like', "%{$term}%")
                    ->orWhere('items.alias', 'like', "%{$term}%");
            });
        }

        // Sorting: Prioritize items needing urgent action first
        $query->orderByDesc('replenishment_item_metrics.suggested_qty')
            ->orderBy('replenishment_item_metrics.cover_days', 'asc');

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
     * View Replenishment Rules & Settings.
     */
    public function settings(): View
    {
        if (! config('replenishment.enabled', false)) {
            abort(404);
        }

        $salesRules = ReplenishmentSalesRule::orderBy('id')->get();
        $stockRules = ReplenishmentStockRule::orderBy('id')->get();

        return view('inventory.replenishment.settings', compact('salesRules', 'stockRules'));
    }

    /**
     * Update Replenishment Rules & Settings.
     */
    public function updateSettings(Request $request)
    {
        if (! config('replenishment.enabled', false)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'Not enabled'], 404);
            }
            abort(404);
        }

        $salesRulesData = $request->input('sales_rules', []);
        foreach ($salesRulesData as $ruleId => $data) {
            if (is_array($data)) {
                $update = [
                    'target_cover_days' => max(1, (int) ($data['target_cover_days'] ?? 7)),
                ];
                if (isset($data['min_daily_avg'])) {
                    $update['min_daily_avg'] = max(0.0, (float) $data['min_daily_avg']);
                }
                if (array_key_exists('max_daily_avg', $data)) {
                    $update['max_daily_avg'] = ($data['max_daily_avg'] !== '' && $data['max_daily_avg'] !== null)
                        ? (float) $data['max_daily_avg']
                        : null;
                }
                ReplenishmentSalesRule::where('id', $ruleId)->update($update);
            } else {
                ReplenishmentSalesRule::where('id', $ruleId)->update([
                    'target_cover_days' => max(1, (int) $data),
                ]);
            }
        }

        if ($request->has('stock_rules')) {
            $stockRulesInput = $request->input('stock_rules', []);
            $criticalMax = max(1, (int) ($stockRulesInput['critical_max'] ?? 3));
            $lowMax = max($criticalMax + 1, (int) ($stockRulesInput['low_max'] ?? 10));
            $healthyMax = max($lowMax + 1, (int) ($stockRulesInput['healthy_max'] ?? 30));

            ReplenishmentStockRule::where('stock_type', 'Critical')->update([
                'min_cover_days' => 0,
                'max_cover_days' => $criticalMax,
            ]);
            ReplenishmentStockRule::where('stock_type', 'Low')->update([
                'min_cover_days' => $criticalMax + 1,
                'max_cover_days' => $lowMax,
            ]);
            ReplenishmentStockRule::where('stock_type', 'Healthy')->update([
                'min_cover_days' => $lowMax + 1,
                'max_cover_days' => $healthyMax,
            ]);
            ReplenishmentStockRule::where('stock_type', 'Overstock')->update([
                'min_cover_days' => $healthyMax + 1,
                'max_cover_days' => null,
            ]);
        }

        // Auto-recalculate metrics so inventory status immediately reflects the updated thresholds
        $this->service->calculateAll();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Replenishment rules and target days updated successfully!',
            ]);
        }

        return redirect()->back()->with('success', 'Replenishment rules and target days updated successfully!');
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

        $transferRecommendations = $this->service->getTransferRecommendations($activeBranchId, 500);

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
     * Bulk Purchase Order Generation Pipeline:
     * Groups multi-item orders by supplier and creates separate consolidated Purchase Orders in a single transaction.
     */
    public function bulkGeneratePo(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'orders' => 'required|array|min:1',
            'orders.*.supplier_id' => 'required|exists:suppliers,id',
            'orders.*.items' => 'required|array|min:1',
            'orders.*.items.*.item_id' => 'required|exists:items,id',
            'orders.*.items.*.qty' => 'required|integer|min:1',
        ]);

        $branchId = (int) $validated['branch_id'];
        $today = now()->format('Y-m-d');
        $createdPos = [];
        $totalOverallQty = 0;
        $totalOverallAmount = 0;

        DB::transaction(function () use ($branchId, $today, $validated, &$createdPos, &$totalOverallQty, &$totalOverallAmount) {
            $numberingService = app(DocumentNumberingService::class);

            foreach ($validated['orders'] as $orderData) {
                $supplierId = (int) $orderData['supplier_id'];
                $supplier = Supplier::find($supplierId);
                $poNumber = $numberingService->generate('purchase_order', $branchId, $today);

                $itemIds = collect($orderData['items'])->pluck('item_id')->unique();
                $itemsMap = Item::whereIn('id', $itemIds)->with('gstTax:id,percentage')->get()->keyBy('id');

                $totalQty = 0;
                $totalGst = 0;
                $grandTotal = 0;
                $poLines = [];

                foreach ($orderData['items'] as $line) {
                    $item = $itemsMap[$line['item_id']] ?? null;
                    if (! $item) {
                        continue;
                    }

                    $qty = (int) $line['qty'];
                    if ($qty <= 0) {
                        continue;
                    }

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

                if (empty($poLines)) {
                    continue;
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
                    'remarks' => 'Bulk generated by Smart Replenishment Engine (V1)',
                ]);

                $purchaseOrder->items()->createMany($poLines);

                $totalOverallQty += $totalQty;
                $totalOverallAmount += $grandTotal;

                $createdPos[] = [
                    'po_id' => $purchaseOrder->id,
                    'po_number' => $purchaseOrder->po_number,
                    'supplier_id' => $supplierId,
                    'supplier_name' => $supplier ? $supplier->name : 'Supplier #'.$supplierId,
                    'items_count' => count($poLines),
                    'total_qty' => $totalQty,
                    'total_amount' => $grandTotal,
                    'redirect_url' => route('purchase.purchase-orders.show', $purchaseOrder->id),
                ];
            }
        });

        return response()->json([
            'success' => true,
            'created_pos' => $createdPos,
            'total_pos' => count($createdPos),
            'total_qty' => $totalOverallQty,
            'total_amount' => $totalOverallAmount,
            'message' => count($createdPos) === 1
                ? "Purchase Order {$createdPos[0]['po_number']} created successfully with {$totalOverallQty} units (₹".number_format($totalOverallAmount, 2).')!'
                : 'Successfully created '.count($createdPos).' Purchase Orders (Total: '.$totalOverallQty.' units, ₹'.number_format($totalOverallAmount, 2).')!',
        ]);
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

    /**
     * Query other store inventory sources for requested items.
     * Used by the Bulk Transfer Modal to show available source branches and surplus.
     */
    public function transferSources(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $validated = $request->validate([
            'to_branch_id' => 'required|exists:branches,id',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'required|exists:items,id',
        ]);

        $toBranchId = (int) $validated['to_branch_id'];
        $itemIds = $validated['item_ids'];

        $stocks = ItemStock::whereIn('item_id', $itemIds)
            ->where('branch_id', '!=', $toBranchId)
            ->where('quantity', '>', 0)
            ->with(['branch:id,name'])
            ->orderBy('quantity', 'desc')
            ->get();

        $grouped = $stocks->groupBy('item_id');
        $items = Item::whereIn('id', $itemIds)->select('id', 'name', 'item_code')->get();

        $itemsData = [];
        foreach ($items as $item) {
            $branchStocks = $grouped->get($item->id, collect());
            $sources = [];
            $recommendedBranchId = null;
            $maxStock = 0;

            foreach ($branchStocks as $stockRow) {
                $qty = (float) $stockRow->quantity;
                $sources[] = [
                    'branch_id' => $stockRow->branch_id,
                    'branch_name' => $stockRow->branch ? $stockRow->branch->name : 'Branch #'.$stockRow->branch_id,
                    'available_stock' => $qty,
                ];

                if ($qty > $maxStock) {
                    $maxStock = $qty;
                    $recommendedBranchId = $stockRow->branch_id;
                }
            }

            $itemsData[] = [
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_code' => $item->item_code,
                'sources' => $sources,
                'recommended_branch_id' => $recommendedBranchId,
                'max_available_stock' => $maxStock,
            ];
        }

        return response()->json([
            'success' => true,
            'items' => $itemsData,
        ]);
    }

    /**
     * Bulk Inter-Branch Stock Transfer Request Pipeline:
     * Groups multi-item transfer requests by source branch and creates separate consolidated Stock Transfers in a single transaction.
     */
    public function bulkCreateTransfer(Request $request): JsonResponse
    {
        if (! config('replenishment.enabled', false)) {
            return response()->json(['error' => 'Not enabled'], 404);
        }

        $validated = $request->validate([
            'to_branch_id' => 'nullable|exists:branches,id',
            'transfers' => 'required|array|min:1',
            'transfers.*.from_branch_id' => 'required|exists:branches,id',
            'transfers.*.to_branch_id' => 'nullable|exists:branches,id',
            'transfers.*.items' => 'required|array|min:1',
            'transfers.*.items.*.item_id' => 'required|exists:items,id',
            'transfers.*.items.*.qty' => 'required|integer|min:1',
        ]);

        $defaultToBranchId = (int) ($validated['to_branch_id'] ?? 0);
        $today = now()->format('Y-m-d');
        $createdTransfers = [];
        $totalOverallQty = 0;

        DB::transaction(function () use ($defaultToBranchId, $today, $validated, &$createdTransfers, &$totalOverallQty) {
            $numberingService = app(DocumentNumberingService::class);

            foreach ($validated['transfers'] as $transferData) {
                $fromBranchId = (int) $transferData['from_branch_id'];
                $toBranchId = (int) ($transferData['to_branch_id'] ?? $defaultToBranchId);
                if (! $toBranchId || $toBranchId === $fromBranchId) {
                    continue;
                }

                $fromBranch = Branch::find($fromBranchId);
                $toBranch = Branch::find($toBranchId);
                $transferNumber = $numberingService->generate('stock_transfer', $fromBranchId, $today);

                $itemIds = collect($transferData['items'])->pluck('item_id')->unique();
                $itemsMap = Item::whereIn('id', $itemIds)->with('gstTax:id,percentage')->get()->keyBy('id');

                $totalQty = 0;
                $totalValue = 0;
                $transferLines = [];

                foreach ($transferData['items'] as $line) {
                    $item = $itemsMap[$line['item_id']] ?? null;
                    if (! $item) {
                        continue;
                    }

                    $qty = (int) $line['qty'];
                    if ($qty <= 0) {
                        continue;
                    }

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

                if (empty($transferLines)) {
                    continue;
                }

                $stockTransfer = StockTransfer::create([
                    'transfer_number' => $transferNumber,
                    'transfer_date' => $today,
                    'from_branch_id' => $fromBranchId,
                    'to_branch_id' => $toBranchId,
                    'status' => 'Dispatched',
                    'remarks' => 'Bulk Inter-Branch transfer recommended by Smart Replenishment Engine (V1)',
                    'total_qty' => $totalQty,
                    'total_value' => $totalValue,
                    'dispatched_at' => now(),
                ]);

                $stockTransfer->items()->createMany($transferLines);

                $totalOverallQty += $totalQty;

                $createdTransfers[] = [
                    'transfer_id' => $stockTransfer->id,
                    'transfer_number' => $stockTransfer->transfer_number,
                    'from_branch_id' => $fromBranchId,
                    'from_branch_name' => $fromBranch ? $fromBranch->name : 'Branch #'.$fromBranchId,
                    'to_branch_id' => $toBranchId,
                    'to_branch_name' => $toBranch ? $toBranch->name : 'Branch #'.$toBranchId,
                    'items_count' => count($transferLines),
                    'total_qty' => $totalQty,
                    'redirect_url' => route('inventory.stock-transfers.show', $stockTransfer->id),
                ];
            }
        });

        return response()->json([
            'success' => true,
            'created_transfers' => $createdTransfers,
            'total_transfers' => count($createdTransfers),
            'total_qty' => $totalOverallQty,
            'message' => count($createdTransfers) === 1
                ? "Stock Transfer Challan {$createdTransfers[0]['transfer_number']} created successfully with {$totalOverallQty} units!"
                : 'Successfully created '.count($createdTransfers).' Stock Transfer Challans (Total: '.$totalOverallQty.' units)!',
        ]);
    }

    /**
     * 1-Click Export to Excel (.xlsx).
     */
    public function export(Request $request): BinaryFileResponse
    {
        if (! config('replenishment.enabled', false)) {
            abort(404);
        }

        $branchId = $request->get('branch_id');
        $status = $request->get('status');
        $salesType = $request->get('sales_type');
        $action = $request->get('action');
        $brandId = $request->get('brand_id');
        $categoryId = $request->get('category_id');
        $departmentId = $request->get('department_id');
        $supplierId = $request->get('supplier_id');
        $search = $request->get('search');

        $query = ReplenishmentItemMetric::query()
            ->select('replenishment_item_metrics.*')
            ->with([
                'item:id,name,item_code,brand_id,brand_value_id,department_value_id,category_value_id,supplier_id,cost_price',
                'item.brand:id,name',
                'item.brandValue:id,name',
                'item.departmentValue:id,name',
                'item.categoryValue:id,name',
                'supplier:id,name',
                'branch:id,name',
            ]);

        $needsItemJoin = ($brandId && $brandId !== 'all')
            || ($categoryId && $categoryId !== 'all')
            || ($departmentId && $departmentId !== 'all')
            || ! empty($search);

        if ($needsItemJoin) {
            $query->join('items', 'replenishment_item_metrics.item_id', '=', 'items.id');
        }

        if ($branchId && $branchId !== 'all') {
            $query->where('replenishment_item_metrics.branch_id', $branchId);
        }

        if ($status && $status !== 'all') {
            if ($status === 'needs_action') {
                $query->where(function ($q) {
                    $q->where('replenishment_item_metrics.suggested_qty', '>', 0)
                        ->orWhere(function ($sub) {
                            $sub->whereIn('replenishment_item_metrics.stock_status', ['Critical', 'Low'])
                                ->where('replenishment_item_metrics.daily_avg_sales', '>', 0);
                        });
                });
            } else {
                $query->where('replenishment_item_metrics.stock_status', $status);
            }
        }

        if ($salesType && $salesType !== 'all') {
            $query->where('replenishment_item_metrics.sales_type', $salesType);
        }

        if ($action && $action !== 'all') {
            if ($action === 'order') {
                $query->where('replenishment_item_metrics.suggested_qty', '>', 0);
            } elseif ($action === 'transfer') {
                $query->where('replenishment_item_metrics.other_branch_stock', '>', 0)
                    ->where(function ($q) {
                        $q->whereIn('replenishment_item_metrics.stock_status', ['Critical', 'Low'])
                            ->orWhere('replenishment_item_metrics.suggested_qty', '>', 0);
                    });
            }
        }

        if ($supplierId && $supplierId !== 'all') {
            $query->where('replenishment_item_metrics.primary_supplier_id', $supplierId);
        }

        if ($departmentId && $departmentId !== 'all') {
            $query->where('items.department_value_id', $departmentId);
        }

        if ($categoryId && $categoryId !== 'all') {
            $query->where('items.category_value_id', $categoryId);
        }

        if ($brandId && $brandId !== 'all') {
            $query->where(function ($q) use ($brandId) {
                $q->where('items.brand_id', $brandId)
                    ->orWhere('items.brand_value_id', $brandId);
            });
        }

        if ($search) {
            $term = trim($search);
            $query->where(function ($q) use ($term) {
                $q->where('items.item_code', 'like', "{$term}%")
                    ->orWhere('items.name', 'like', "%{$term}%")
                    ->orWhere('items.alias', 'like', "%{$term}%");
            });
        }

        $query->orderByDesc('replenishment_item_metrics.suggested_qty')
            ->orderBy('replenishment_item_metrics.cover_days', 'asc');

        $rows = [];
        foreach ($query->cursor() as $metric) {
            $item = $metric->item;
            $unitCost = (float) ($item->cost_price ?? 0);
            $reorderVal = round($metric->suggested_qty * $unitCost, 2);
            $brandName = $item->brand->name ?? ($item->brandValue->name ?? '-');

            $rows[] = [
                $item->item_code ?? '-',
                $item->name ?? 'SKU #'.$metric->item_id,
                $item->departmentValue->name ?? '-',
                $item->categoryValue->name ?? '-',
                $brandName,
                $metric->supplier->name ?? '-',
                (float) $metric->current_stock,
                (float) $metric->other_branch_stock,
                (float) ($metric->current_stock + $metric->other_branch_stock),
                (float) $metric->sales_30d,
                (float) $metric->daily_avg_sales,
                (float) $metric->cover_days,
                (int) $metric->target_cover_days,
                (int) ceil($metric->daily_avg_sales * $metric->target_cover_days),
                (int) $metric->suggested_qty,
                $metric->sales_type ?? 'Dead Stock',
                $metric->stock_status,
                $unitCost,
                $reorderVal,
            ];
        }

        $timestamp = now()->format('Y-m-d_His');
        $fileName = "Stock_Replenishment_Analysis_{$timestamp}.xlsx";

        return Excel::download(new SmartReplenishmentExport($rows), $fileName);
    }
}
