<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserSavedReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsBuilderController extends Controller
{
    /**
     * Display the Analytics & Custom Report Studio UI.
     */
    public function index(Request $request)
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $savedReports = UserSavedReport::where('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        $initialPreset = $request->query('preset', '');
        $initialItemId = $request->query('item_id');
        $initialSupplierId = $request->query('supplier_id');
        $initialCustomerId = $request->query('customer_id');

        $initialItem = $initialItemId ? Item::find($initialItemId) : null;
        $initialSupplier = $initialSupplierId ? Supplier::find($initialSupplierId) : null;
        $initialCustomer = $initialCustomerId ? Customer::find($initialCustomerId) : null;

        return view('reports.analytics-builder', compact(
            'branches',
            'savedReports',
            'initialPreset',
            'initialItem',
            'initialSupplier',
            'initialCustomer'
        ));
    }

    /**
     * Execute the dynamic query and return JSON for table and chart.
     */
    public function generate(Request $request): JsonResponse
    {
        $groupBy = $request->input('group_by', 'item');
        $metrics = (array) $request->input('metrics', ['qty', 'sales_value', 'margin', 'bill_count']);
        if (empty($metrics)) {
            $metrics = ['qty', 'sales_value', 'margin', 'bill_count'];
        }

        [$from, $to] = $this->resolveDateRange($request);
        $branchId = $request->input('branch_id');
        $itemId = $request->input('item_id');
        $supplierId = $request->input('supplier_id');
        $customerId = $request->input('customer_id');
        $sortBy = $request->input('sort_by');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $limit = $request->input('limit');

        if ($groupBy === 'item_supplier') {
            $result = $this->queryItemSupplierSourcing($from, $to, $branchId, $itemId, $supplierId, $sortBy, $sortDir, $limit);
        } else {
            $result = $this->querySalesAnalytics($groupBy, $metrics, $from, $to, $branchId, $itemId, $customerId, $sortBy, $sortDir, $limit);
        }

        return response()->json(array_merge([
            'success' => true,
            'group_by' => $groupBy,
            'metrics' => $metrics,
            'date_from' => $from,
            'date_to' => $to,
        ], $result));
    }

    /**
     * Save custom report configuration for the authenticated user.
     */
    public function saveReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'group_by' => 'required|string|in:item,customer,category,brand,cashier,payment_mode,date,item_supplier',
            'metrics' => 'required|array',
            'filters' => 'nullable|array',
        ]);

        $saved = UserSavedReport::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'group_by' => $validated['group_by'],
            'metrics' => $validated['metrics'],
            'filters' => $validated['filters'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report saved successfully!',
            'report' => $saved,
        ]);
    }

    /**
     * Delete a saved report.
     */
    public function deleteReport($id): JsonResponse
    {
        UserSavedReport::where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully.',
        ]);
    }

    /**
     * Stream dynamic CSV export.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $groupBy = $request->input('group_by', 'item');
        $metrics = (array) $request->input('metrics', ['qty', 'sales_value', 'margin', 'bill_count']);
        [$from, $to] = $this->resolveDateRange($request);
        $branchId = $request->input('branch_id');
        $itemId = $request->input('item_id');
        $supplierId = $request->input('supplier_id');
        $customerId = $request->input('customer_id');
        $sortBy = $request->input('sort_by');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($groupBy === 'item_supplier') {
            $data = $this->queryItemSupplierSourcing($from, $to, $branchId, $itemId, $supplierId, $sortBy, $sortDir, null);
        } else {
            $data = $this->querySalesAnalytics($groupBy, $metrics, $from, $to, $branchId, $itemId, $customerId, $sortBy, $sortDir, null);
        }

        $filename = 'Custom_Report_' . $groupBy . '_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($data, $groupBy) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            $headers = array_map(fn($col) => $col['label'], $data['columns']);
            fputcsv($handle, $headers);

            foreach ($data['rows'] as $row) {
                $line = [];
                foreach ($data['columns'] as $col) {
                    $key = $col['key'];
                    $line[] = $row->{$key} ?? '';
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Query Item-Supplier Sourcing Traceability (Item 1 ➡️ Supplier X, Supplier Y)
     */
    protected function queryItemSupplierSourcing($from, $to, $branchId, $itemId, $supplierId, $sortBy, $sortDir, $limit): array
    {
        $query = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->join('items', 'purchase_invoice_items.item_id', '=', 'items.id')
            ->join('suppliers', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
            ->where('purchase_invoices.status', '!=', 'Cancelled');

        if ($from && $to) {
            $query->whereBetween('purchase_invoices.invoice_date', [$from, $to]);
        }
        if ($branchId) {
            $query->where('purchase_invoices.branch_id', $branchId);
        }
        if ($itemId) {
            $query->where('purchase_invoice_items.item_id', $itemId);
        }
        if ($supplierId) {
            $query->where('purchase_invoices.supplier_id', $supplierId);
        }

        $rows = $query->selectRaw('
            items.id as item_id,
            items.item_code,
            items.name as item_name,
            items.supplier_id as default_supplier_id,
            suppliers.id as supplier_id,
            suppliers.name as supplier_name,
            suppliers.mobile as supplier_mobile,
            suppliers.city as supplier_city,
            SUM(purchase_invoice_items.qty) as total_qty,
            SUM(purchase_invoice_items.net_amount) as total_amount,
            COUNT(DISTINCT purchase_invoices.id) as invoice_count,
            MAX(purchase_invoices.invoice_date) as last_date,
            AVG(purchase_invoice_items.cost_price) as avg_cost_price,
            MAX(purchase_invoice_items.cost_price) as max_cost_price,
            MIN(purchase_invoice_items.cost_price) as min_cost_price,
            (SELECT pii.cost_price 
             FROM purchase_invoice_items pii 
             JOIN purchase_invoices pi ON pii.purchase_invoice_id = pi.id 
             WHERE pii.item_id = items.id AND pi.supplier_id = suppliers.id AND pi.status != "Cancelled" 
             ORDER BY pi.invoice_date DESC, pi.id DESC LIMIT 1) as last_cost_price
        ')
        ->groupBy(
            'items.id',
            'items.item_code',
            'items.name',
            'items.supplier_id',
            'suppliers.id',
            'suppliers.name',
            'suppliers.mobile',
            'suppliers.city'
        );

        $allowedSorts = ['item_name', 'supplier_name', 'total_qty', 'total_amount', 'last_date', 'last_cost_price', 'invoice_count'];
        $orderCol = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'item_name';
        $rows->orderBy($orderCol, $sortDir);

        if ($limit && is_numeric($limit) && (int) $limit > 0) {
            $rows->limit((int) $limit);
        }

        $records = $rows->get();

        // Totals & Formats
        $totalQty = 0;
        $totalAmount = 0;
        $totalInvoices = 0;

        foreach ($records as $r) {
            $r->is_primary_supplier = ($r->default_supplier_id == $r->supplier_id);
            $r->formatted_qty = number_format($r->total_qty, 2);
            $r->formatted_amount = '₹ ' . number_format($r->total_amount, 2);
            $r->formatted_last_cost = '₹ ' . number_format($r->last_cost_price ?? $r->avg_cost_price ?? 0, 2);
            $r->formatted_last_date = $r->last_date ? Carbon::parse($r->last_date)->format('d-M-Y') : 'N/A';

            $totalQty += (float) $r->total_qty;
            $totalAmount += (float) $r->total_amount;
            $totalInvoices += (int) $r->invoice_count;
        }

        $columns = [
            ['key' => 'item_code', 'label' => 'Item Code'],
            ['key' => 'item_name', 'label' => 'Item Name'],
            ['key' => 'supplier_name', 'label' => 'Supplier Name'],
            ['key' => 'is_primary_supplier', 'label' => 'Primary Source'],
            ['key' => 'formatted_qty', 'label' => 'Total Inward Qty'],
            ['key' => 'formatted_last_cost', 'label' => 'Last Purchase Cost'],
            ['key' => 'formatted_amount', 'label' => 'Total Purchase Value'],
            ['key' => 'invoice_count', 'label' => 'Bills / Invoices'],
            ['key' => 'formatted_last_date', 'label' => 'Last Inward Date'],
        ];

        // Chart dataset (Top 10 suppliers or items by inward qty)
        $chartLabels = [];
        $chartValues = [];
        foreach ($records->take(10) as $row) {
            $chartLabels[] = $row->item_name . ' (' . $row->supplier_name . ')';
            $chartValues[] = (float) $row->total_qty;
        }

        return [
            'columns' => $columns,
            'rows' => $records,
            'totals' => [
                'total_qty' => number_format($totalQty, 2),
                'total_amount' => '₹ ' . number_format($totalAmount, 2),
                'total_invoices' => $totalInvoices,
                'count' => count($records),
            ],
            'chart' => [
                'label' => 'Inward Quantity by Sourcing',
                'labels' => $chartLabels,
                'values' => $chartValues,
            ],
        ];
    }

    /**
     * Query Standard Sales Analytics by Dynamic Group By
     */
    protected function querySalesAnalytics($groupBy, array $metrics, $from, $to, $branchId, $itemId, $customerId, $sortBy, $sortDir, $limit): array
    {
        $columns = [];
        $query = null;

        switch ($groupBy) {
            case 'customer':
                $query = DB::table('sales_bills')
                    ->join('customers', 'sales_bills.customer_id', '=', 'customers.id')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        customers.id as group_id,
                        customers.name as group_name,
                        COALESCE(customers.mobile, "-") as group_subtext,
                        COUNT(sales_bills.id) as bill_count,
                        SUM(sales_bills.total) as total_sales,
                        SUM(sales_bills.disc_amount) as total_disc,
                        MAX(sales_bills.bill_date) as last_date,
                        SUM(sales_bills.total_qty) as total_qty
                    ')
                    ->groupBy('customers.id', 'customers.name', 'customers.mobile');

                if ($customerId) {
                    $query->where('sales_bills.customer_id', $customerId);
                }
                $groupLabel = 'Customer Name';
                $subtextLabel = 'Mobile';
                break;

            case 'category':
                $query = DB::table('sales_bill_items')
                    ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                    ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
                    ->leftJoin('item_category_values', 'items.category_value_id', '=', 'item_category_values.id')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        COALESCE(item_category_values.id, 0) as group_id,
                        COALESCE(item_category_values.name, "Uncategorized") as group_name,
                        "" as group_subtext,
                        SUM(sales_bill_items.qty) as total_qty,
                        SUM(sales_bill_items.net_amount) as total_sales,
                        SUM(sales_bill_items.disc_amount) as total_disc,
                        SUM(sales_bill_items.net_amount - (COALESCE(sales_bill_items.cost_at_sale, items.cost_price, 0) * sales_bill_items.qty)) as total_profit,
                        COUNT(DISTINCT sales_bills.id) as bill_count,
                        MAX(sales_bills.bill_date) as last_date
                    ')
                    ->groupBy('item_category_values.id', 'item_category_values.name');

                $groupLabel = 'Category';
                $subtextLabel = '';
                break;

            case 'brand':
                $query = DB::table('sales_bill_items')
                    ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                    ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
                    ->leftJoin('brands', 'items.brand_id', '=', 'brands.id')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        COALESCE(brands.id, 0) as group_id,
                        COALESCE(brands.name, "No Brand") as group_name,
                        "" as group_subtext,
                        SUM(sales_bill_items.qty) as total_qty,
                        SUM(sales_bill_items.net_amount) as total_sales,
                        SUM(sales_bill_items.disc_amount) as total_disc,
                        SUM(sales_bill_items.net_amount - (COALESCE(sales_bill_items.cost_at_sale, items.cost_price, 0) * sales_bill_items.qty)) as total_profit,
                        COUNT(DISTINCT sales_bills.id) as bill_count,
                        MAX(sales_bills.bill_date) as last_date
                    ')
                    ->groupBy('brands.id', 'brands.name');

                $groupLabel = 'Brand';
                $subtextLabel = '';
                break;

            case 'cashier':
                $query = DB::table('sales_bills')
                    ->leftJoin('till_sessions', 'sales_bills.till_session_id', '=', 'till_sessions.id')
                    ->leftJoin('users', 'till_sessions.user_id', '=', 'users.id')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        COALESCE(users.id, 0) as group_id,
                        COALESCE(users.name, "Admin / POS Direct") as group_name,
                        COALESCE(users.email, "") as group_subtext,
                        COUNT(sales_bills.id) as bill_count,
                        SUM(sales_bills.total) as total_sales,
                        SUM(sales_bills.disc_amount) as total_disc,
                        MAX(sales_bills.bill_date) as last_date,
                        SUM(sales_bills.total_qty) as total_qty
                    ')
                    ->groupBy('users.id', 'users.name', 'users.email');

                $groupLabel = 'Cashier / Staff';
                $subtextLabel = 'User Email';
                break;

            case 'payment_mode':
                $query = DB::table('sales_bills')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        COALESCE(sales_bills.payment_type, "Cash") as group_id,
                        COALESCE(sales_bills.payment_type, "Cash") as group_name,
                        "" as group_subtext,
                        COUNT(sales_bills.id) as bill_count,
                        SUM(sales_bills.total) as total_sales,
                        SUM(sales_bills.disc_amount) as total_disc,
                        MAX(sales_bills.bill_date) as last_date,
                        SUM(sales_bills.total_qty) as total_qty
                    ')
                    ->groupBy('sales_bills.payment_type');

                $groupLabel = 'Payment Mode';
                $subtextLabel = '';
                break;

            case 'date':
                $query = DB::table('sales_bills')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        DATE(sales_bills.bill_date) as group_id,
                        DATE(sales_bills.bill_date) as group_name,
                        "" as group_subtext,
                        COUNT(sales_bills.id) as bill_count,
                        SUM(sales_bills.total) as total_sales,
                        SUM(sales_bills.disc_amount) as total_disc,
                        MAX(sales_bills.bill_date) as last_date,
                        SUM(sales_bills.total_qty) as total_qty
                    ')
                    ->groupBy(DB::raw('DATE(sales_bills.bill_date)'));

                $groupLabel = 'Date';
                $subtextLabel = '';
                break;

            case 'item':
            default:
                $query = DB::table('sales_bill_items')
                    ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                    ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
                    ->where('sales_bills.status', '!=', 'Cancelled')
                    ->selectRaw('
                        items.id as group_id,
                        items.name as group_name,
                        COALESCE(items.item_code, "") as group_subtext,
                        SUM(sales_bill_items.qty) as total_qty,
                        SUM(sales_bill_items.net_amount) as total_sales,
                        SUM(sales_bill_items.disc_amount) as total_disc,
                        SUM(sales_bill_items.net_amount - (COALESCE(sales_bill_items.cost_at_sale, items.cost_price, 0) * sales_bill_items.qty)) as total_profit,
                        COUNT(DISTINCT sales_bills.id) as bill_count,
                        MAX(sales_bills.bill_date) as last_date
                    ')
                    ->groupBy('items.id', 'items.name', 'items.item_code');

                if ($itemId) {
                    $query->where('sales_bill_items.item_id', $itemId);
                }
                $groupLabel = 'Item Name';
                $subtextLabel = 'Item Code';
                break;
        }

        if ($from && $to) {
            $query->whereBetween('sales_bills.bill_date', [$from, $to]);
        }
        if ($branchId) {
            $query->where('sales_bills.branch_id', $branchId);
        }

        // Sorting
        $allowedSorts = ['group_name', 'total_qty', 'total_sales', 'total_disc', 'total_profit', 'bill_count', 'last_date'];
        $orderCol = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'total_sales';
        $query->orderBy($orderCol, $sortDir);

        if ($limit && is_numeric($limit) && (int) $limit > 0) {
            $query->limit((int) $limit);
        }

        $records = $query->get();

        // Columns definition based on chosen metrics
        $columns[] = ['key' => 'group_name', 'label' => $groupLabel];
        if (!empty($subtextLabel)) {
            $columns[] = ['key' => 'group_subtext', 'label' => $subtextLabel];
        }

        if (in_array('qty', $metrics, true)) {
            $columns[] = ['key' => 'formatted_qty', 'label' => 'Quantity Sold'];
        }
        if (in_array('sales_value', $metrics, true)) {
            $columns[] = ['key' => 'formatted_sales', 'label' => 'Total Sales Value (₹)'];
        }
        if (in_array('discount', $metrics, true)) {
            $columns[] = ['key' => 'formatted_disc', 'label' => 'Discount (₹)'];
        }
        if (in_array('margin', $metrics, true)) {
            $columns[] = ['key' => 'formatted_profit', 'label' => 'Gross Profit / Margin'];
        }
        if (in_array('bill_count', $metrics, true)) {
            $columns[] = ['key' => 'bill_count', 'label' => 'Bill Count'];
        }
        if (in_array('aov', $metrics, true)) {
            $columns[] = ['key' => 'formatted_aov', 'label' => 'Avg Order Value (AOV)'];
        }
        if (in_array('last_date', $metrics, true)) {
            $columns[] = ['key' => 'formatted_last_date', 'label' => 'Last Transaction'];
        }

        // Aggregations
        $totalQty = 0;
        $totalSales = 0;
        $totalDisc = 0;
        $totalProfit = 0;
        $totalBills = 0;

        foreach ($records as $r) {
            $qty = (float) ($r->total_qty ?? 0);
            $sales = (float) ($r->total_sales ?? 0);
            $disc = (float) ($r->total_disc ?? 0);
            $profit = (float) ($r->total_profit ?? 0);
            $bills = (int) ($r->bill_count ?? 0);

            $aov = $bills > 0 ? $sales / $bills : 0;
            $marginPct = $sales > 0 ? ($profit / $sales) * 100 : 0;

            $r->formatted_qty = number_format($qty, 2);
            $r->formatted_sales = '₹ ' . number_format($sales, 2);
            $r->formatted_disc = '₹ ' . number_format($disc, 2);
            $r->formatted_profit = '₹ ' . number_format($profit, 2) . ' (' . number_format($marginPct, 1) . '%)';
            $r->formatted_aov = '₹ ' . number_format($aov, 2);
            $r->formatted_last_date = $r->last_date ? Carbon::parse($r->last_date)->format('d-M-Y H:i') : '-';

            $totalQty += $qty;
            $totalSales += $sales;
            $totalDisc += $disc;
            $totalProfit += $profit;
            $totalBills += $bills;
        }

        $overallAov = $totalBills > 0 ? $totalSales / $totalBills : 0;
        $overallMarginPct = $totalSales > 0 ? ($totalProfit / $totalSales) * 100 : 0;

        // Chart representation (Top 10)
        $chartLabels = [];
        $chartValues = [];
        foreach ($records->take(10) as $row) {
            $chartLabels[] = (string) $row->group_name;
            $chartValues[] = (float) ($row->total_sales ?? 0);
        }

        return [
            'columns' => $columns,
            'rows' => $records,
            'totals' => [
                'total_qty' => number_format($totalQty, 2),
                'total_sales' => '₹ ' . number_format($totalSales, 2),
                'total_disc' => '₹ ' . number_format($totalDisc, 2),
                'total_profit' => '₹ ' . number_format($totalProfit, 2) . ' (' . number_format($overallMarginPct, 1) . '%)',
                'total_bills' => $totalBills,
                'aov' => '₹ ' . number_format($overallAov, 2),
                'count' => count($records),
            ],
            'chart' => [
                'label' => 'Total Sales (₹) by ' . $groupLabel,
                'labels' => $chartLabels,
                'values' => $chartValues,
            ],
        ];
    }

    /**
     * Resolve date range from preset or custom inputs.
     */
    protected function resolveDateRange(Request $request): array
    {
        $preset = $request->input('date_preset', 'this_month');

        switch ($preset) {
            case 'today':
                return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
            case 'yesterday':
                return [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()];
            case 'this_week':
                return [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()];
            case 'last_month':
                return [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()];
            case 'all_time':
                return [null, null];
            case 'custom':
                $from = $request->input('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : Carbon::now()->startOfMonth();
                $to = $request->input('date_to') ? Carbon::parse($request->input('date_to'))->endOfDay() : Carbon::now()->endOfDay();
                return [$from, $to];
            case 'this_month':
            default:
                return [Carbon::now()->startOfMonth(), Carbon::now()->endOfDay()];
        }
    }

    /**
     * Live search for items (Select2 format).
     */
    public function searchItems(Request $request): JsonResponse
    {
        $term = $request->query('q', '');
        $items = Item::where('name', 'LIKE', "%{$term}%")
            ->orWhere('item_code', 'LIKE', "%{$term}%")
            ->orWhere('ean_upc_code', 'LIKE', "%{$term}%")
            ->limit(30)
            ->get(['id', 'name', 'item_code', 'sell_price']);

        $formatted = $items->map(fn($item) => [
            'id' => $item->id,
            'text' => ($item->item_code ? "[{$item->item_code}] " : '') . "{$item->name} (₹{$item->sell_price})",
        ]);

        return response()->json(['results' => $formatted]);
    }

    /**
     * Live search for suppliers (Select2 format).
     */
    public function searchSuppliers(Request $request): JsonResponse
    {
        $term = $request->query('q', '');
        $suppliers = Supplier::where('name', 'LIKE', "%{$term}%")
            ->orWhere('mobile', 'LIKE', "%{$term}%")
            ->orWhere('city', 'LIKE', "%{$term}%")
            ->limit(30)
            ->get(['id', 'name', 'mobile', 'city']);

        $formatted = $suppliers->map(fn($s) => [
            'id' => $s->id,
            'text' => "{$s->name}" . ($s->city ? " - {$s->city}" : '') . ($s->mobile ? " ({$s->mobile})" : ''),
        ]);

        return response()->json(['results' => $formatted]);
    }

    /**
     * Live search for customers (Select2 format).
     */
    public function searchCustomers(Request $request): JsonResponse
    {
        $term = $request->query('q', '');
        $customers = Customer::where('name', 'LIKE', "%{$term}%")
            ->orWhere('mobile', 'LIKE', "%{$term}%")
            ->limit(30)
            ->get(['id', 'name', 'mobile']);

        $formatted = $customers->map(fn($c) => [
            'id' => $c->id,
            'text' => "{$c->name} ({$c->mobile})",
        ]);

        return response()->json(['results' => $formatted]);
    }
}
