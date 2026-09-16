<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Breed;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\DamageStock;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use App\Models\PetType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesBill;
use App\Models\SalesBillPayment;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\TillSession;
use App\Models\User;
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
        $search = $request->input('search');
        $customerId = $request->input('customer_id');
        $invoiceType = $request->input('invoice_type');

        $query = SalesBill::with(['customer', 'branch', 'items.item'])
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($invoiceType, fn ($q) => $q->where('invoice_type', $invoiceType));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        $bills = $query->orderBy('bill_date')->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $customers = Customer::orderBy('name')->get();
        $invoiceTypes = SalesBill::select('invoice_type')->distinct()->whereNotNull('invoice_type')->pluck('invoice_type');

        return view('reports.billwise-sales', compact('bills', 'from', 'to', 'branchId', 'branches', 'customers', 'invoiceTypes', 'search', 'customerId', 'invoiceType'));
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
        $search = $request->input('search');
        $supplierId = $request->input('supplier_id');
        $purchaseType = $request->input('purchase_type');

        $query = PurchaseInvoice::with(['supplier', 'branch', 'items.item'])
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($purchaseType, fn ($q) => $q->where('purchase_type', $purchaseType));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('supplier_inv_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $invoices = $query->orderBy('invoice_date')->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::orderBy('name')->get();
        $purchaseTypes = PurchaseInvoice::select('purchase_type')->distinct()->whereNotNull('purchase_type')->pluck('purchase_type');

        return view('reports.purchase-detail', compact('invoices', 'from', 'to', 'branchId', 'branches', 'suppliers', 'purchaseTypes', 'search', 'supplierId', 'purchaseType'));
    }

    public function currentStock(Request $request)
    {
        $branchId = $request->input('branch_id');
        $brandId = $request->input('brand_id');
        $categoryValueId = $request->input('category_value_id');
        $search = $request->input('search');

        $query = ItemStock::with(['item.brand', 'branch'])
            ->where('quantity', '>', 0);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->whereHas('item', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('ean_upc_code', 'like', "%{$search}%");
            });
        }

        if ($brandId) {
            $query->whereHas('item', fn ($q) => $q->where('brand_id', $brandId));
        }

        if ($categoryValueId) {
            $query->whereHas('item', fn ($q) => $q->where('category_value_id', $categoryValueId));
        }

        $rows = $query->orderBy('branch_id')->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $brands = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'CATEGORY'))->orderBy('name')->pluck('name', 'id');

        return view('reports.current-stock', compact('rows', 'branchId', 'branches', 'brands', 'categories', 'brandId', 'categoryValueId', 'search'));
    }

    public function salesReturnSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $search = $request->input('search');
        $customerId = $request->input('customer_id');
        $returnMode = $request->input('return_mode');

        $query = SalesReturn::with(['customer', 'branch', 'salesBill'])
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
            ->when($returnMode, fn ($q) => $q->where('return_mode', $returnMode));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                    ->orWhereHas('salesBill', fn ($bq) => $bq->where('bill_number', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $returns = $query->orderBy('return_date')->get();

        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $customers = Customer::orderBy('name')->get();
        $returnModes = SalesReturn::select('return_mode')->distinct()->whereNotNull('return_mode')->pluck('return_mode');

        return view('reports.sales-return-summary', compact('returns', 'from', 'to', 'branchId', 'branches', 'customers', 'returnModes', 'search', 'customerId', 'returnMode'));
    }

    public function customerMaster(Request $request)
    {
        $query = Customer::with(['category', 'branch']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', (bool) $request->status);
        }

        $customers = $query->orderBy('name')->paginate(50)->withQueryString();
        $categories = CustomerCategory::orderBy('name')->pluck('name', 'id');
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.customer-master', compact('customers', 'categories', 'branches'));
    }

    public function customerPetDetails(Request $request)
    {
        $query = Customer::with(['pets.petType', 'pets.breed'])
            ->whereHas('pets');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('pets', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('pet_type_id')) {
            $query->whereHas('pets', fn ($pq) => $pq->where('pet_type_id', $request->pet_type_id));
        }

        if ($request->filled('breed_id')) {
            $query->whereHas('pets', fn ($pq) => $pq->where('breed_id', $request->breed_id));
        }

        $customers = $query->orderBy('name')->paginate(50)->withQueryString();
        $petTypes = PetType::orderBy('name')->pluck('name', 'id');
        $breeds = Breed::orderBy('name')->pluck('name', 'id');

        return view('reports.customer-pet-details', compact('customers', 'petTypes', 'breeds'));
    }

    /**
     * Spec §14's EOD field list (sales/returns/discounts/GST/cash/UPI/card/credit/
     * outstanding/till-variance), scoped to a branch+date range or a single till session.
     * Payment totals are grouped by TenderType.type (Cash/Card/Wallet/Credit/...) rather
     * than hard-coded "cash/UPI/card" buckets — this schema has no distinct "UPI" enum
     * value, so whatever a store actually configures (e.g. UPI under type=Wallet) shows
     * under its own real type instead of being force-fit into a bucket that doesn't
     * exist here. Bills with no sales_bill_payments rows at all (old-style, or any bill
     * that never opted into split-tender) are surfaced separately as "unattributed"
     * rather than silently guessed at.
     */
    public function eod(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $tillSessionId = $request->input('till_session_id');

        $bills = SalesBill::with('payments.tenderType')
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($tillSessionId, fn ($q) => $q->where('till_session_id', $tillSessionId))
            ->get();

        $returns = SalesReturn::whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $paymentTotals = $bills->flatMap->payments
            ->groupBy(fn ($payment) => $payment->tenderType->type)
            ->map(fn ($group) => $group->sum('amount'));

        $unattributedTotal = $bills->reject(fn ($bill) => $bill->payments->isNotEmpty())->sum('total');

        $tillSessions = $tillSessionId
            ? TillSession::whereKey($tillSessionId)->get()
            : TillSession::whereDate('opened_at', '>=', $from)->whereDate('opened_at', '<=', $to)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->get();

        $summary = [
            'sales_count' => $bills->count(),
            'sales_total' => (float) $bills->sum('total'),
            'returns_count' => $returns->count(),
            'returns_total' => (float) $returns->sum('total'),
            'discount_total' => (float) $bills->sum('disc_amount'),
            'gst_total' => (float) $bills->sum('total_gst'),
            'payment_totals' => $paymentTotals,
            'unattributed_total' => (float) $unattributedTotal,
            'till_variance_total' => (float) $tillSessions->whereNotNull('variance')->sum('variance'),
        ];

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.eod', compact('summary', 'tillSessions', 'from', 'to', 'branchId', 'tillSessionId', 'branches'));
    }

    public function itemMaster(Request $request)
    {
        $query = Item::with(['brand', 'gstTax', 'categoryValue']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('item_code', 'like', "%{$search}%")
                    ->orWhere('ean_upc_code', 'like', "%{$search}%")
                    ->orWhere('alias', 'like', "%{$search}%");
            });
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('category_value_id')) {
            $query->where('category_value_id', $request->category_value_id);
        }

        if ($request->filled('status')) {
            $query->where('status', (bool) $request->status);
        }

        $items = $query->orderBy('name')->paginate(50)->withQueryString();
        $brands = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'CATEGORY'))->orderBy('name')->pluck('name', 'id');

        return view('reports.item-master', compact('items', 'brands', 'categories'));
    }

    public function supplierMaster(Request $request)
    {
        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('gst_no', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('state')) {
            $query->where('state', $request->state);
        }

        if ($request->filled('status')) {
            $query->where('status', (bool) $request->status);
        }

        $suppliers = $query->orderBy('name')->paginate(50)->withQueryString();
        $states = Supplier::select('state')->distinct()->whereNotNull('state')->pluck('state');

        return view('reports.supplier-master', compact('suppliers', 'states'));
    }

    public function gstPurchaseSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $rows = PurchaseInvoice::with('items.item')
            ->whereDate('invoice_date', '>=', $from)
            ->whereDate('invoice_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->flatMap(fn ($inv) => $inv->items->map(fn ($line) => (object) [
                'hsn_code' => $line->item?->hsn_code ?: 'N/A',
                'gst_percent' => (float) ($line->gst_percent ?? 0),
                'taxable_amount' => (float) ($line->net_amount - $line->gst_tax_amount),
                'cgst_amount' => (float) ($line->cgst_amount ?? 0),
                'sgst_amount' => (float) ($line->sgst_amount ?? 0),
                'igst_amount' => (float) ($line->igst_amount ?? 0),
                'gst_amount' => (float) ($line->gst_tax_amount ?? 0),
            ]))
            ->groupBy(fn ($row) => $row->hsn_code.'|'.$row->gst_percent)
            ->map(function ($group) {
                $first = $group->first();
                return (object) [
                    'hsn_code' => $first->hsn_code,
                    'gst_percent' => $first->gst_percent,
                    'taxable_amount' => $group->sum('taxable_amount'),
                    'cgst_amount' => $group->sum('cgst_amount'),
                    'sgst_amount' => $group->sum('sgst_amount'),
                    'igst_amount' => $group->sum('igst_amount'),
                    'gst_amount' => $group->sum('gst_amount'),
                ];
            })
            ->values();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.gst-purchase-summary', compact('rows', 'from', 'to', 'branchId', 'branches'));
    }

    public function purchaseOrderSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $supplierId = $request->input('supplier_id');
        $status = $request->input('status');
        $search = $request->input('search');

        $query = PurchaseOrder::with(['supplier', 'branch'])
            ->whereDate('po_date', '>=', $from)
            ->whereDate('po_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($status, fn ($q) => $q->where('status', $status));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $purchaseOrders = $query->orderByDesc('po_date')->paginate(30)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::orderBy('name')->pluck('name', 'id');
        $statuses = PurchaseOrder::select('status')->distinct()->whereNotNull('status')->pluck('status');

        return view('reports.purchase-order-summary', compact('purchaseOrders', 'from', 'to', 'branchId', 'branches', 'suppliers', 'statuses', 'supplierId', 'status', 'search'));
    }

    public function stockTransferSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $toBranchId = $request->input('to_branch_id');
        $status = $request->input('status');
        $search = $request->input('search');

        $query = StockTransfer::with(['fromBranch', 'toBranch'])
            ->whereDate('transfer_date', '>=', $from)
            ->whereDate('transfer_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('from_branch_id', $branchId))
            ->when($toBranchId, fn ($q) => $q->where('to_branch_id', $toBranchId))
            ->when($status, fn ($q) => $q->where('status', $status));

        if ($search) {
            $query->where('transfer_number', 'like', "%{$search}%");
        }

        $transfers = $query->orderByDesc('transfer_date')->paginate(30)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $statuses = StockTransfer::select('status')->distinct()->whereNotNull('status')->pluck('status');

        return view('reports.stock-transfer-summary', compact('transfers', 'from', 'to', 'branchId', 'toBranchId', 'branches', 'statuses', 'status', 'search'));
    }

    public function damageStockSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $search = $request->input('search');

        $query = DamageStock::with(['branch', 'items.item'])
            ->whereDate('entry_date', '>=', $from)
            ->whereDate('entry_date', '<=', $to)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        if ($search) {
            $query->where('damage_number', 'like', "%{$search}%");
        }

        $damageStocks = $query->orderByDesc('entry_date')->paginate(30)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.damage-stock-summary', compact('damageStocks', 'from', 'to', 'branchId', 'branches', 'search'));
    }

    public function tenderSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $tenderTypeId = $request->input('tender_type_id');

        $query = SalesBillPayment::with(['tenderType', 'tenderTypeValue', 'salesBill.branch'])
            ->whereHas('salesBill', function ($q) use ($from, $to, $branchId) {
                $q->whereDate('bill_date', '>=', $from)
                  ->whereDate('bill_date', '<=', $to)
                  ->when($branchId, fn ($bq) => $bq->where('branch_id', $branchId));
            })
            ->when($tenderTypeId, fn ($q) => $q->where('tender_type_id', $tenderTypeId));

        $payments = $query->get();

        $rows = $payments->groupBy(fn ($p) => $p->tenderType?->name ?? 'Unknown')
            ->map(function ($group, $name) {
                return (object) [
                    'tender_name' => $name,
                    'type' => $group->first()->tenderType?->type ?? 'Other',
                    'count' => $group->count(),
                    'total_amount' => (float) $group->sum('amount'),
                ];
            })
            ->values();

        $totalCollected = (float) $rows->sum('total_amount');
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $tenderTypes = TenderType::orderBy('name')->pluck('name', 'id');

        return view('reports.tender-summary', compact('rows', 'totalCollected', 'from', 'to', 'branchId', 'branches', 'tenderTypes', 'tenderTypeId'));
    }

    public function auditLogs(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $userId = $request->input('user_id');
        $action = $request->input('action');
        $search = $request->input('search');

        $query = AuditLog::with(['user', 'branch'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($action, fn ($q) => $q->where('action', $action));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();
        $users = User::orderBy('name')->pluck('name', 'id');
        $actions = AuditLog::select('action')->distinct()->whereNotNull('action')->pluck('action');

        return view('reports.audit-logs', compact('logs', 'from', 'to', 'userId', 'action', 'search', 'users', 'actions'));
    }

    private function dateAndBranchFilter(Request $request): array
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $request->input('branch_id');

        return [$from, $to, $branchId];
    }
}
