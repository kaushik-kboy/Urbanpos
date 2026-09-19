<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SmartAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        $defaultBranchId = session('active_branch_id') ?: ($branches->keys()->first() ?? null);
        $fromDate = $request->query('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->query('to_date', now()->toDateString());

        $initialItemId = $request->query('item_id');
        $initialItem = $initialItemId ? Item::find($initialItemId) : null;

        $initialCustomerId = $request->query('customer_id');
        $initialCustomer = $initialCustomerId ? Customer::find($initialCustomerId) : null;

        return view('reports.smart-analytics', compact(
            'branches',
            'defaultBranchId',
            'fromDate',
            'toDate',
            'initialItem',
            'initialCustomer'
        ));
    }

    public function itemAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'branch_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $itemId = (int) $request->input('item_id');
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();

        $item = Item::with('brand')->findOrFail($itemId);

        // 1. Current available stock
        $stockQuery = ItemStock::where('item_id', $itemId);
        if ($branchId) {
            $stockQuery->where('branch_id', $branchId);
        }
        $currentStock = (float) $stockQuery->sum('quantity');

        // 2. Base Query for Sales
        $salesQuery = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->leftJoin('customers', 'sales_bills.customer_id', '=', 'customers.id')
            ->where('sales_bill_items.item_id', $itemId)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereBetween('sales_bills.bill_date', [$fromDate, $toDate]);

        if ($branchId) {
            $salesQuery->where('sales_bills.branch_id', $branchId);
        }

        // Aggregate Metrics
        $summary = (clone $salesQuery)
            ->selectRaw('
                COUNT(DISTINCT sales_bills.id) as bills_count,
                COALESCE(SUM(sales_bill_items.qty), 0) as total_qty,
                COALESCE(SUM(sales_bill_items.net_amount), 0) as total_net_amount,
                COALESCE(SUM(sales_bill_items.disc_amount), 0) as total_discount,
                COALESCE(SUM(sales_bill_items.cost_at_sale * sales_bill_items.qty), 0) as total_cost
            ')
            ->first();

        $totalQty = (float) ($summary->total_qty ?? 0);
        $totalNetAmount = (float) ($summary->total_net_amount ?? 0);
        $totalDiscount = (float) ($summary->total_discount ?? 0);
        $totalCost = (float) ($summary->total_cost ?? 0);
        $billsCount = (int) ($summary->bills_count ?? 0);

        $grossProfit = $totalNetAmount - $totalCost;
        $marginPercent = $totalNetAmount > 0 ? round(($grossProfit / $totalNetAmount) * 100, 1) : 0.0;
        $avgSellingRate = $totalQty > 0 ? round($totalNetAmount / $totalQty, 2) : (float) $item->sell_price;

        // 3. Daily Sales Trend (for chart)
        $dailyTrend = (clone $salesQuery)
            ->selectRaw('DATE(sales_bills.bill_date) as date, COALESCE(SUM(sales_bill_items.qty), 0) as qty, COALESCE(SUM(sales_bill_items.net_amount), 0) as amount')
            ->groupBy(DB::raw('DATE(sales_bills.bill_date)'))
            ->orderBy(DB::raw('DATE(sales_bills.bill_date)'), 'asc')
            ->get();

        // 4. Billwise Detailed Breakdown (most recent first)
        $bills = (clone $salesQuery)
            ->select([
                'sales_bills.id as bill_id',
                'sales_bills.bill_number',
                'sales_bills.bill_date',
                'customers.name as customer_name',
                'sales_bill_items.qty',
                'sales_bill_items.sell_price',
                'sales_bill_items.disc_amount',
                'sales_bill_items.net_amount',
            ])
            ->orderBy('sales_bills.bill_date', 'desc')
            ->limit(200)
            ->get();

        return response()->json([
            'status' => 'success',
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'item_code' => $item->item_code,
                'ean_upc_code' => $item->ean_upc_code,
                'mrp' => (float) $item->mrp,
                'sell_price' => (float) $item->sell_price,
                'brand' => $item->brand?->name ?? 'Standard',
            ],
            'summary' => [
                'total_qty' => $totalQty,
                'total_net_amount' => $totalNetAmount,
                'total_discount' => $totalDiscount,
                'gross_profit' => $grossProfit,
                'margin_percent' => $marginPercent,
                'avg_selling_rate' => $avgSellingRate,
                'bills_count' => $billsCount,
                'current_stock' => $currentStock,
            ],
            'daily_trend' => $dailyTrend,
            'bills' => $bills,
        ]);
    }

    public function customerAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'branch_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $customerId = (int) $request->input('customer_id');
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();

        $customer = Customer::findOrFail($customerId);

        $billsQuery = SalesBill::where('customer_id', $customerId)
            ->where('status', '!=', 'Cancelled')
            ->whereBetween('bill_date', [$fromDate, $toDate]);

        if ($branchId) {
            $billsQuery->where('branch_id', $branchId);
        }

        $totalBills = (int) (clone $billsQuery)->count();
        $totalSpend = (float) (clone $billsQuery)->sum('total');
        $avgBillValue = $totalBills > 0 ? round($totalSpend / $totalBills, 2) : 0.0;
        $lastVisit = (clone $billsQuery)->max('bill_date');

        // Top items purchased by this customer in this period
        $topItems = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
            ->where('sales_bills.customer_id', $customerId)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereBetween('sales_bills.bill_date', [$fromDate, $toDate])
            ->select([
                'items.id',
                'items.name',
                DB::raw('SUM(sales_bill_items.qty) as total_qty'),
                DB::raw('SUM(sales_bill_items.net_amount) as total_amount')
            ])
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        $recentBills = (clone $billsQuery)
            ->select(['id', 'bill_number', 'bill_date', 'total_qty', 'total', 'status'])
            ->orderBy('bill_date', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'status' => 'success',
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'credit_balance' => (float) ($customer->credit_balance ?? 0),
            ],
            'summary' => [
                'total_bills' => $totalBills,
                'total_spend' => $totalSpend,
                'avg_bill_value' => $avgBillValue,
                'last_visit' => $lastVisit ? Carbon::parse($lastVisit)->format('d M Y, h:i A') : 'No visits in period',
            ],
            'top_items' => $topItems,
            'recent_bills' => $recentBills,
        ]);
    }

    public function rankingAnalytics(Request $request): JsonResponse
    {
        $type = $request->input('type', 'top_selling');
        $metric = $request->input('metric', 'qty');
        $limit = min((int) $request->input('limit', 10), 50);
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();

        if ($type === 'slow_moving') {
            // Items with 0 sales in the selected period
            $soldItemIds = DB::table('sales_bill_items')
                ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                ->where('sales_bills.status', '!=', 'Cancelled')
                ->whereBetween('sales_bills.bill_date', [$fromDate, $toDate])
                ->when($branchId, fn($q) => $q->where('sales_bills.branch_id', $branchId))
                ->pluck('sales_bill_items.item_id')
                ->unique()
                ->toArray();

            $items = Item::leftJoin('item_stocks', 'items.id', '=', 'item_stocks.item_id')
                ->whereNotIn('items.id', $soldItemIds)
                ->where('items.status', true)
                ->when($branchId, fn($q) => $q->where('item_stocks.branch_id', $branchId))
                ->select([
                    'items.id',
                    'items.name',
                    'items.item_code',
                    'items.sell_price',
                    DB::raw('COALESCE(SUM(item_stocks.quantity), 0) as current_stock'),
                    DB::raw('0 as total_sold_qty'),
                    DB::raw('0 as total_revenue')
                ])
                ->groupBy('items.id', 'items.name', 'items.item_code', 'items.sell_price')
                ->orderByDesc('current_stock')
                ->limit($limit)
                ->get();
        } else {
            // Top selling items
            $orderCol = $metric === 'revenue' ? 'total_revenue' : 'total_sold_qty';

            $items = DB::table('sales_bill_items')
                ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
                ->where('sales_bills.status', '!=', 'Cancelled')
                ->whereBetween('sales_bills.bill_date', [$fromDate, $toDate])
                ->when($branchId, fn($q) => $q->where('sales_bills.branch_id', $branchId))
                ->select([
                    'items.id',
                    'items.name',
                    'items.item_code',
                    'items.sell_price',
                    DB::raw('SUM(sales_bill_items.qty) as total_sold_qty'),
                    DB::raw('SUM(sales_bill_items.net_amount) as total_revenue'),
                    DB::raw('COUNT(DISTINCT sales_bills.id) as bills_count')
                ])
                ->groupBy('items.id', 'items.name', 'items.item_code', 'items.sell_price')
                ->orderByDesc($orderCol)
                ->limit($limit)
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'type' => $type,
            'items' => $items,
        ]);
    }

    public function searchItems(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            $items = Item::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'item_code', 'ean_upc_code', 'sell_price']);
        } else {
            $items = Item::where('status', true)
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('item_code', 'like', "%{$q}%")
                        ->orWhere('ean_upc_code', 'like', "%{$q}%");
                })
                ->orderBy('name')
                ->limit(30)
                ->get(['id', 'name', 'item_code', 'ean_upc_code', 'sell_price']);
        }

        return response()->json($items);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            $customers = Customer::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'mobile']);
        } else {
            $customers = Customer::where('status', true)
                ->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('mobile', 'like', "%{$q}%");
                })
                ->orderBy('name')
                ->limit(30)
                ->get(['id', 'name', 'mobile']);
        }

        return response()->json($customers);
    }

    public function exportItemCsv(Request $request): StreamedResponse
    {
        $itemId = (int) $request->input('item_id');
        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date'))->endOfDay() : now()->endOfDay();

        $item = Item::findOrFail($itemId);

        $salesQuery = DB::table('sales_bill_items')
            ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->leftJoin('customers', 'sales_bills.customer_id', '=', 'customers.id')
            ->where('sales_bill_items.item_id', $itemId)
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereBetween('sales_bills.bill_date', [$fromDate, $toDate])
            ->when($branchId, fn($q) => $q->where('sales_bills.branch_id', $branchId))
            ->select([
                'sales_bills.bill_number',
                'sales_bills.bill_date',
                'customers.name as customer_name',
                'sales_bill_items.qty',
                'sales_bill_items.sell_price',
                'sales_bill_items.disc_amount',
                'sales_bill_items.net_amount',
            ])
            ->orderBy('sales_bills.bill_date', 'asc');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="item_sales_' . $item->item_code . '_' . date('Ymd_His') . '.csv"',
        ];

        return response()->stream(function () use ($salesQuery, $item) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Product:', $item->name, 'Code:', $item->item_code]);
            fputcsv($handle, ['Bill No', 'Date & Time', 'Customer', 'Qty Sold', 'Unit Rate', 'Discount', 'Net Total']);

            $salesQuery->chunk(200, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->bill_number,
                        $row->bill_date,
                        $row->customer_name ?: 'Walk-in Customer',
                        $row->qty,
                        $row->sell_price,
                        $row->disc_amount,
                        $row->net_amount,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
