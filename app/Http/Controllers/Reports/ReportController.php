<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }

    public function salesSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $rows = SalesBill::with('branch')
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('DATE(bill_date) as bill_date, branch_id, COUNT(*) as bill_count, SUM(total) as total_amount, SUM(disc_amount) as total_disc, SUM(total_gst) as total_gst')
            ->groupBy(DB::raw('DATE(bill_date)'), 'branch_id')
            ->orderBy('bill_date')
            ->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.sales-summary', compact('rows', 'from', 'to', 'branchId', 'branches'));
    }

    public function billwiseSales(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $bills = SalesBill::with(['customer', 'branch', 'items.item'])
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('bill_date')
            ->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.billwise-sales', compact('bills', 'from', 'to', 'branchId', 'branches'));
    }

    public function gstSalesSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $rows = SalesBill::with('items.item')
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->flatMap(fn ($bill) => $bill->items->map(fn ($line) => (object) [
                'hsn_code' => $line->item?->hsn_code,
                'gst_percent' => $line->gst_percent,
                'taxable_amount' => $line->net_amount - $line->gst_tax_amount,
                'gst_amount' => $line->gst_tax_amount,
            ]))
            ->groupBy(fn ($row) => ($row->hsn_code ?: 'N/A').'|'.$row->gst_percent)
            ->map(function ($group) {
                $first = $group->first();

                return (object) [
                    'hsn_code' => $first->hsn_code ?: 'N/A',
                    'gst_percent' => $first->gst_percent,
                    'taxable_amount' => $group->sum('taxable_amount'),
                    'gst_amount' => $group->sum('gst_amount'),
                ];
            })
            ->values();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.gst-sales-summary', compact('rows', 'from', 'to', 'branchId', 'branches'));
    }

    public function purchaseDetail(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $invoices = PurchaseInvoice::with(['supplier', 'branch', 'items.item'])
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('invoice_date')
            ->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.purchase-detail', compact('invoices', 'from', 'to', 'branchId', 'branches'));
    }

    public function currentStock(Request $request)
    {
        $branchId = $request->input('branch_id');

        $rows = ItemStock::with(['item', 'branch'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('quantity', '>', 0)
            ->orderBy('branch_id')
            ->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.current-stock', compact('rows', 'branchId', 'branches'));
    }

    public function salesReturnSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $returns = SalesReturn::with(['customer', 'branch', 'salesBill'])
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('return_date')
            ->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.sales-return-summary', compact('returns', 'from', 'to', 'branchId', 'branches'));
    }

    public function customerMaster()
    {
        $customers = Customer::with(['category', 'branch'])->orderBy('name')->paginate(50);

        return view('reports.customer-master', compact('customers'));
    }

    public function customerPetDetails()
    {
        $customers = Customer::with(['pets.petType', 'pets.breed'])
            ->whereHas('pets')
            ->orderBy('name')
            ->paginate(50);

        return view('reports.customer-pet-details', compact('customers'));
    }

    private function dateAndBranchFilter(Request $request): array
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $request->input('branch_id');

        return [$from, $to, $branchId];
    }
}
