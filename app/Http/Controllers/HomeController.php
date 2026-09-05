<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use Illuminate\Http\Request;

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
    public function index()
    {
        $today = now()->format('Y-m-d');
        $monthStart = now()->startOfMonth()->format('Y-m-d');

        $todaySales = SalesBill::whereDate('bill_date', $today)->sum('total');
        $monthSales = SalesBill::whereDate('bill_date', '>=', $monthStart)->sum('total');
        $monthPurchase = PurchaseInvoice::whereDate('invoice_date', '>=', $monthStart)->sum('total');
        $monthReturns = SalesReturn::whereDate('return_date', '>=', $monthStart)->sum('total');

        $stockValue = ItemStock::with('item')->get()->sum(fn ($row) => $row->quantity * ($row->item?->cost_price ?? 0));

        $lowStockItems = ItemStock::where('quantity', '<=', 0)->distinct('item_id')->count('item_id');

        $totalCustomers = Customer::count();
        $totalItems = Item::where('status', true)->count();

        $recentBills = SalesBill::with(['customer', 'branch'])->latest('bill_date')->latest('id')->limit(5)->get();

        return view('home', compact(
            'todaySales', 'monthSales', 'monthPurchase', 'monthReturns',
            'stockValue', 'lowStockItems', 'totalCustomers', 'totalItems', 'recentBills'
        ));
    }
}
