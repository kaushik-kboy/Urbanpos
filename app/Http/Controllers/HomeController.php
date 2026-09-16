<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\TillSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Branch scoping
        $allBranches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $branchId = null;
        if ($user && $user->branch_id) {
            $branchId = (int) $user->branch_id;
        } elseif ($request->filled('branch_id')) {
            $branchId = $request->input('branch_id') === 'all' ? null : (int) $request->input('branch_id');
        } elseif (session()->has('active_branch_id') && session('active_branch_id')) {
            $branchId = (int) session('active_branch_id');
        }

        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');
        $startOfPrevMonth = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $endOfPrevMonthSameDay = now()->subMonth()->format('Y-m-d');

        // 1. KPI Cards
        $todayQuery = SalesBill::whereDate('bill_date', $today)
            ->where('status', '!=', 'Cancelled')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $todaySales = (float) ($todayQuery->sum('total') ?? 0);
        $todayBillsCount = (int) $todayQuery->count();

        $monthQuery = SalesBill::whereDate('bill_date', '>=', $monthStart)
            ->where('status', '!=', 'Cancelled')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $monthSales = (float) ($monthQuery->sum('total') ?? 0);
        $monthBillsCount = (int) $monthQuery->count();

        // Prev month sales for growth %
        $prevMonthSales = (float) (SalesBill::whereDate('bill_date', '>=', $startOfPrevMonth)
            ->whereDate('bill_date', '<=', $endOfPrevMonthSameDay)
            ->where('status', '!=', 'Cancelled')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total') ?? 0);
        $salesGrowthPct = $prevMonthSales > 0
            ? round((($monthSales - $prevMonthSales) / $prevMonthSales) * 100, 1)
            : 0.0;

        // Month Gross Profit Margin
        $monthProfit = (float) (SalesBillItem::join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereDate('sales_bills.bill_date', '>=', $monthStart)
            ->when($branchId, fn ($q) => $q->where('sales_bills.branch_id', $branchId))
            ->selectRaw('SUM(sales_bill_items.net_amount - (COALESCE(sales_bill_items.cost_at_sale, 0) * sales_bill_items.qty)) as profit')
            ->value('profit') ?? 0);

        $monthPurchase = (float) (PurchaseInvoice::whereDate('invoice_date', '>=', $monthStart)
            ->where('status', '!=', 'Cancelled')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total') ?? 0);

        $monthReturns = (float) (SalesReturn::whereDate('return_date', '>=', $monthStart)
            ->where('status', '!=', 'Cancelled')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('total') ?? 0);

        // Stock Valuation & Health
        $stockValue = (float) (ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
            ->when($branchId, fn ($q) => $q->where('item_stocks.branch_id', $branchId))
            ->where('item_stocks.quantity', '>', 0)
            ->sum(DB::raw('item_stocks.quantity * COALESCE(items.cost_price, 0)')) ?? 0);

        $lowStockItems = (int) ItemStock::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('quantity', '<=', 5)
            ->where('quantity', '>', 0)
            ->distinct('item_id')
            ->count('item_id');

        $outOfStockItems = (int) ItemStock::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('quantity', '<=', 0)
            ->distinct('item_id')
            ->count('item_id');

        $totalCustomers = (int) Customer::count();
        $totalItems = (int) Item::where('status', true)->count();

        // 2. 30-Day Revenue Trend
        $thirtyDaysAgo = now()->subDays(29)->format('Y-m-d');
        $rawTrend = SalesBill::selectRaw('DATE(bill_date) as d, SUM(total) as revenue, COUNT(*) as bills')
            ->where('status', '!=', 'Cancelled')
            ->whereDate('bill_date', '>=', $thirtyDaysAgo)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $trendLabels = [];
        $trendRevenue = [];
        $trendBills = [];
        for ($i = 29; $i >= 0; $i--) {
            $dt = now()->subDays($i);
            $dateKey = $dt->format('Y-m-d');
            $trendLabels[] = $dt->format('d M');
            $row = $rawTrend->get($dateKey);
            $trendRevenue[] = $row ? round((float) $row->revenue, 2) : 0;
            $trendBills[] = $row ? (int) $row->bills : 0;
        }

        // 3. Top 10 Fast-Moving Items
        $topItems = SalesBillItem::join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereDate('sales_bills.bill_date', '>=', $thirtyDaysAgo)
            ->when($branchId, fn ($q) => $q->where('sales_bills.branch_id', $branchId))
            ->selectRaw('items.id, items.name, items.item_code, SUM(sales_bill_items.qty) as total_qty, SUM(sales_bill_items.net_amount) as total_revenue')
            ->groupBy('items.id', 'items.name', 'items.item_code')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $topItemLabels = $topItems->map(fn ($item) => mb_strimwidth($item->name, 0, 20, '...'))->values()->toArray();
        $topItemQty = $topItems->map(fn ($item) => (float) $item->total_qty)->values()->toArray();
        $topItemRevenue = $topItems->map(fn ($item) => round((float) $item->total_revenue, 2))->values()->toArray();

        // 4. Category Sales Distribution
        $categorySales = SalesBillItem::join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
            ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
            ->leftJoin('item_category_values', 'items.category_value_id', '=', 'item_category_values.id')
            ->where('sales_bills.status', '!=', 'Cancelled')
            ->whereDate('sales_bills.bill_date', '>=', $thirtyDaysAgo)
            ->when($branchId, fn ($q) => $q->where('sales_bills.branch_id', $branchId))
            ->selectRaw("COALESCE(item_category_values.name, 'General') as cat_name, SUM(sales_bill_items.net_amount) as cat_revenue")
            ->groupBy('cat_name')
            ->orderByDesc('cat_revenue')
            ->limit(6)
            ->get();

        $categoryLabels = $categorySales->pluck('cat_name')->values()->toArray();
        $categoryAmounts = $categorySales->map(fn ($c) => round((float) $c->cat_revenue, 2))->values()->toArray();

        // 5. Today's Hourly Sales Velocity
        $rawHourly = SalesBill::where('status', '!=', 'Cancelled')
            ->whereDate('bill_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('HOUR(created_at) as hr, SUM(total) as revenue, COUNT(*) as bills')
            ->groupBy('hr')
            ->get()
            ->keyBy('hr');

        $hourlyLabels = [];
        $hourlyRevenue = [];
        $hourlyBills = [];
        for ($h = 8; $h <= 22; $h++) {
            $hourlyLabels[] = Carbon::createFromTime($h)->format('g A');
            $hRow = $rawHourly->get($h);
            $hourlyRevenue[] = $hRow ? round((float) $hRow->revenue, 2) : 0;
            $hourlyBills[] = $hRow ? (int) $hRow->bills : 0;
        }

        // 6. Active Till Sessions
        $activeTills = TillSession::where('status', 'Open')
            ->with(['register', 'branch', 'user'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('opened_at')
            ->limit(5)
            ->get();

        // 7. Recent Transactions
        $recentBills = SalesBill::with(['customer', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest('bill_date')
            ->latest('id')
            ->limit(6)
            ->get();

        $recentQuotations = SalesQuotation::with(['customer', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'Open')
            ->latest('quotation_date')
            ->latest('id')
            ->limit(5)
            ->get();

        $openQuotationsCount = SalesQuotation::where('status', 'Open')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        $openOrdersCount = SalesOrder::where('status', 'Open')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->count();

        return view('home', compact(
            'allBranches', 'branchId',
            'todaySales', 'todayBillsCount',
            'monthSales', 'monthBillsCount', 'salesGrowthPct', 'monthProfit',
            'monthPurchase', 'monthReturns',
            'stockValue', 'lowStockItems', 'outOfStockItems',
            'totalCustomers', 'totalItems',
            'trendLabels', 'trendRevenue', 'trendBills',
            'topItemLabels', 'topItemQty', 'topItemRevenue',
            'categoryLabels', 'categoryAmounts',
            'hourlyLabels', 'hourlyRevenue', 'hourlyBills',
            'activeTills', 'recentBills', 'recentQuotations',
            'openQuotationsCount', 'openOrdersCount'
        ));
    }
}
