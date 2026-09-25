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
use App\Models\SalesBillItem;
use App\Models\SalesBillPayment;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\TillSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $customerId = $request->input('customer_id');
        $selectedCustForFilter = $customerId ? Customer::find($customerId) : null;
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);
        if ($selectedCustForFilter && ! isset($customers[$customerId])) {
            $customers->put($selectedCustForFilter->id, $selectedCustForFilter->mobile ? "{$selectedCustForFilter->name} ({$selectedCustForFilter->mobile})" : $selectedCustForFilter->name);
        }
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
        $branchId = $this->resolveBranchId($request);
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
        $customerId = $request->input('customer_id');
        $selectedCustForFilter = $customerId ? Customer::find($customerId) : null;
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);
        if ($selectedCustForFilter && ! isset($customers[$customerId])) {
            $customers->put($selectedCustForFilter->id, $selectedCustForFilter->mobile ? "{$selectedCustForFilter->name} ({$selectedCustForFilter->mobile})" : $selectedCustForFilter->name);
        }
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
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $branchId = $this->resolveBranchId($request);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', (bool) $request->status);
        }

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderBy('name'),
                [
                    'Code' => fn($c) => $c->customer_code,
                    'Customer Name' => fn($c) => $c->name,
                    'Mobile / Phone' => fn($c) => $c->phone,
                    'City' => fn($c) => $c->city,
                    'GST No' => fn($c) => $c->gst_no,
                    'Category' => fn($c) => $c->category?->name ?? '',
                    'Branch' => fn($c) => $c->branch?->name ?? 'GLOBAL',
                    'Credit Balance' => fn($c) => number_format((float) $c->credit_balance, 2),
                    'Status' => fn($c) => $c->status ? 'Active' : 'Inactive',
                ],
                'customer-master-report.csv'
            );
        }

        $customers = $query->orderBy('name')->paginate(50)->withQueryString();
        $categories = CustomerCategory::orderBy('name')->pluck('name', 'id');
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.customer-master', compact('customers', 'categories', 'branches', 'branchId'));
    }

    public function customerPetDetails(Request $request)
    {
        $query = Customer::with(['pets.petType', 'pets.breed'])
            ->whereHas('pets');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderBy('name'),
                [
                    'Customer Code',
                    'Customer Name',
                    'Pet Name',
                    'Pet Type',
                    'Breed',
                    'Gender',
                    'Age',
                    'Birth Date',
                ],
                'customer-pet-details-report.csv',
                function ($customer) {
                    $lines = [];
                    foreach ($customer->pets as $pet) {
                        $lines[] = [
                            $customer->customer_code,
                            $customer->name,
                            $pet->name,
                            $pet->petType?->name ?? '-',
                            $pet->breed?->name ?? '-',
                            $pet->gender ?: '-',
                            $pet->age ?: '-',
                            $pet->birth_date ? optional($pet->birth_date)->format('d-m-Y') : '-',
                        ];
                    }
                    return $lines;
                }
            );
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderBy('name'),
                [
                    'Code' => fn($i) => $i->item_code,
                    'Barcode' => fn($i) => $i->ean_upc_code ?: '-',
                    'Item Name' => fn($i) => $i->name,
                    'Brand' => fn($i) => $i->brand?->name ?? '-',
                    'Category' => fn($i) => $i->categoryValue?->name ?? '-',
                    'UOM' => fn($i) => $i->base_uom ?: '-',
                    'HSN' => fn($i) => $i->hsn_code ?: '-',
                    'Tax %' => fn($i) => $i->gstTax ? $i->gstTax->percentage . '%' : '-',
                    'Cost Price' => fn($i) => number_format((float) $i->cost_price, 2),
                    'Sell Price' => fn($i) => number_format((float) $i->sell_price, 2),
                    'MRP' => fn($i) => number_format((float) $i->mrp, 2),
                    'Status' => fn($i) => $i->status ? 'Active' : 'Inactive',
                ],
                'item-master-report.csv'
            );
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderBy('name'),
                [
                    'Supplier Name' => fn($s) => $s->name,
                    'Mobile' => fn($s) => $s->mobile ?: '-',
                    'Phone' => fn($s) => $s->phone ?: '-',
                    'Email' => fn($s) => $s->email ?: '-',
                    'GST No' => fn($s) => $s->gst_no ?: '-',
                    'City / State' => fn($s) => $s->city ? $s->city . ($s->state ? ', ' . $s->state : '') : ($s->state ?: '-'),
                    'Address' => fn($s) => $s->address ?: '-',
                    'Credit Limit' => fn($s) => number_format((float) $s->credit_limit, 2),
                    'Credit Balance' => fn($s) => number_format((float) $s->credit_balance, 2),
                    'Status' => fn($s) => $s->status ? 'Active' : 'Inactive',
                ],
                'supplier-master-report.csv'
            );
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderByDesc('po_date'),
                [
                    'PO Number' => fn($po) => $po->po_number,
                    'PO Date' => fn($po) => $po->po_date->format('d-m-Y'),
                    'Supplier' => fn($po) => $po->supplier?->name ?? '-',
                    'Branch' => fn($po) => $po->branch?->name ?? '-',
                    'Total Qty' => fn($po) => number_format((float) $po->total_qty, 2),
                    'Freight' => fn($po) => number_format((float) $po->freight, 2),
                    'Total GST' => fn($po) => number_format((float) $po->total_gst, 2),
                    'Total Amount' => fn($po) => number_format((float) $po->total, 2),
                    'Status' => fn($po) => $po->status,
                ],
                'purchase-order-summary-report.csv'
            );
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderByDesc('transfer_date'),
                [
                    'Transfer Number' => fn($t) => $t->transfer_number,
                    'Transfer Date' => fn($t) => $t->transfer_date->format('d-m-Y'),
                    'From Branch' => fn($t) => $t->fromBranch?->name ?? '-',
                    'To Branch' => fn($t) => $t->toBranch?->name ?? '-',
                    'Total Qty' => fn($t) => number_format((float) $t->total_qty, 2),
                    'Dispatched At' => fn($t) => $t->dispatched_at ? $t->dispatched_at->format('d-m-Y H:i') : '-',
                    'Received At' => fn($t) => $t->received_at ? $t->received_at->format('d-m-Y H:i') : '-',
                    'Status' => fn($t) => $t->status,
                ],
                'stock-transfer-summary-report.csv'
            );
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderByDesc('entry_date'),
                [
                    'Entry Number' => fn($ds) => $ds->damage_number,
                    'Entry Date' => fn($ds) => $ds->entry_date->format('d-m-Y'),
                    'Branch' => fn($ds) => $ds->branch?->name ?? '-',
                    'Wastage Type' => fn($ds) => ucfirst($ds->wastage_type ?: 'Damage'),
                    'Total Damaged Qty' => fn($ds) => number_format((float) $ds->total_qty, 2),
                    'Total Loss Cost' => fn($ds) => number_format((float) $ds->total_cost, 2),
                    'Remarks' => fn($ds) => $ds->remarks ?: '-',
                    'Status' => fn($ds) => $ds->status,
                ],
                'damage-stock-summary-report.csv'
            );
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

        if ($request->query('export') === 'csv') {
            return $this->exportQueryToCsv(
                $query->orderByDesc('created_at'),
                [
                    'Date & Time' => fn($log) => $log->created_at->format('d-m-Y H:i:s'),
                    'User' => fn($log) => $log->user?->name ?? 'System',
                    'Action' => fn($log) => strtoupper($log->action),
                    'Entity' => fn($log) => class_basename($log->auditable_type),
                    'Entity ID' => fn($log) => $log->auditable_id,
                    'Reason / Description' => fn($log) => $log->reason ?: '-',
                    'IP Address' => fn($log) => $log->ip_address ?: '-',
                ],
                'audit-logs-report.csv'
            );
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();
        $users = User::orderBy('name')->pluck('name', 'id');
        $actions = AuditLog::select('action')->distinct()->whereNotNull('action')->pluck('action');

        return view('reports.audit-logs', compact('logs', 'from', 'to', 'userId', 'action', 'search', 'users', 'actions'));
    }

    // ----------------------------------------------------------------
    //  Phase 5 — Sales Margin, Quotation/Order Summary, Re-order Report
    // ----------------------------------------------------------------

    /**
     * Itemwise sales margin — uses cost_at_sale stored at bill-creation time
     * (Foundation Phase 1) so gross profit is always historically accurate even
     * after item cost changes.
     */
    public function salesMarginItemwise(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $brandId       = $request->input('brand_id');
        $categoryValueId = $request->input('category_value_id');
        $customerId    = $request->input('customer_id');
        $search        = $request->input('search');

        $query = SalesBillItem::with(['item.brand', 'item.categoryValue', 'salesBill.customer', 'salesBill.branch'])
            ->whereHas('salesBill', function ($q) use ($from, $to, $branchId) {
                $q->whereDate('bill_date', '>=', $from)
                  ->whereDate('bill_date', '<=', $to)
                  ->when($branchId, fn ($bq) => $bq->where('branch_id', $branchId));
            })
            ->when($brandId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('brand_id', $brandId)))
            ->when($categoryValueId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('category_value_id', $categoryValueId)))
            ->when($customerId, fn ($q) => $q->whereHas('salesBill', fn ($bq) => $bq->where('customer_id', $customerId)))
            ->when($search, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%")));

        $lines = $query->orderBy('id', 'desc')->get()->map(function ($line) {
            $sellTotal  = (float) $line->net_amount;
            $cogTotal   = (float) ($line->cost_at_sale * $line->qty);
            $margin     = $sellTotal - $cogTotal;
            $marginPct  = $sellTotal > 0 ? ($margin / $sellTotal) * 100 : 0;
            return (object) [
                'bill_date'     => $line->salesBill?->bill_date,
                'bill_number'   => $line->salesBill?->bill_number,
                'customer_name' => $line->salesBill?->customer?->name ?? 'Walk-in',
                'branch_name'   => $line->salesBill?->branch?->name,
                'item_code'     => $line->item?->item_code,
                'item_name'     => $line->item?->name,
                'brand_name'    => $line->item?->brand?->name,
                'category_name' => $line->item?->categoryValue?->name,
                'qty'           => $line->qty,
                'sell_price'    => $line->sell_price,
                'sell_total'    => $sellTotal,
                'cost_at_sale'  => $line->cost_at_sale,
                'cog_total'     => $cogTotal,
                'gross_margin'  => $margin,
                'margin_pct'    => $marginPct,
            ];
        });

        $totals = [
            'sell_total'   => $lines->sum('sell_total'),
            'cog_total'    => $lines->sum('cog_total'),
            'gross_margin' => $lines->sum('gross_margin'),
        ];
        $totals['margin_pct'] = $totals['sell_total'] > 0
            ? ($totals['gross_margin'] / $totals['sell_total']) * 100 : 0;

        $branches        = Branch::orderBy('name')->pluck('name', 'id');
        $brands          = Brand::orderBy('name')->pluck('name', 'id');
        $categories      = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'CATEGORY'))->orderBy('name')->pluck('name', 'id');
        $customerId = $request->input('customer_id');
        $selectedCustForFilter = $customerId ? Customer::find($customerId) : null;
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->pluck('name', 'id');
        if ($selectedCustForFilter && ! isset($customers[$customerId])) {
            $customers->put($selectedCustForFilter->id, $selectedCustForFilter->name);
        }

        return view('reports.sales-margin-itemwise', compact(
            'lines', 'totals', 'from', 'to', 'branchId', 'brandId', 'categoryValueId',
            'customerId', 'search', 'branches', 'brands', 'categories', 'customers'
        ));
    }

    /**
     * Categorywise sales margin — aggregate gross profit grouped by item category.
     */
    public function salesMarginCategorywise(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);

        $lines = SalesBillItem::with(['item.categoryValue'])
            ->whereHas('salesBill', function ($q) use ($from, $to, $branchId) {
                $q->whereDate('bill_date', '>=', $from)
                  ->whereDate('bill_date', '<=', $to)
                  ->when($branchId, fn ($bq) => $bq->where('branch_id', $branchId));
            })
            ->get();

        $grouped = $lines->groupBy(fn ($line) => $line->item?->categoryValue?->name ?? 'Uncategorised')
            ->map(function ($group, $categoryName) {
                $sellTotal = $group->sum(fn ($l) => (float) $l->net_amount);
                $cogTotal  = $group->sum(fn ($l) => (float) ($l->cost_at_sale * $l->qty));
                $margin    = $sellTotal - $cogTotal;
                return (object) [
                    'category_name' => $categoryName,
                    'qty'           => $group->sum('qty'),
                    'sell_total'    => $sellTotal,
                    'cog_total'     => $cogTotal,
                    'gross_margin'  => $margin,
                    'margin_pct'    => $sellTotal > 0 ? ($margin / $sellTotal) * 100 : 0,
                ];
            })
            ->sortByDesc('gross_margin')
            ->values();

        $totals = [
            'sell_total'   => $grouped->sum('sell_total'),
            'cog_total'    => $grouped->sum('cog_total'),
            'gross_margin' => $grouped->sum('gross_margin'),
        ];
        $totals['margin_pct'] = $totals['sell_total'] > 0
            ? ($totals['gross_margin'] / $totals['sell_total']) * 100 : 0;

        // Top-10 for chart
        $chartLabels = $grouped->take(10)->pluck('category_name');
        $chartMargin = $grouped->take(10)->pluck('gross_margin');
        $chartSales  = $grouped->take(10)->pluck('sell_total');

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('reports.sales-margin-categorywise', compact(
            'grouped', 'totals', 'from', 'to', 'branchId', 'branches',
            'chartLabels', 'chartMargin', 'chartSales'
        ));
    }

    /**
     * Combined Quotation + Order summary report with status tracking.
     */
    public function quotationOrderSummary(Request $request)
    {
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $type     = $request->input('type');   // 'quotation' | 'order'
        $status   = $request->input('status');
        $customerId = $request->input('customer_id');

        $quotations = collect();
        $orders     = collect();

        if (!$type || $type === 'quotation') {
            $quotations = SalesQuotation::with(['customer', 'branch', 'items'])
                ->whereDate('quotation_date', '>=', $from)
                ->whereDate('quotation_date', '<=', $to)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->orderByDesc('quotation_date')
                ->get()
                ->map(fn ($q) => (object) [
                    'type'         => 'Quotation',
                    'number'       => $q->quotation_number,
                    'date'         => $q->quotation_date,
                    'valid_until'  => $q->valid_until,
                    'customer'     => $q->customer?->name ?? 'Walk-in',
                    'branch'       => $q->branch?->name,
                    'items_count'  => $q->items->count(),
                    'total'        => $q->total,
                    'status'       => $q->status,
                    'converted_bill_id' => $q->converted_sales_bill_id,
                    'show_url'     => route('sales.sales-quotations.show', $q),
                ]);
        }

        if (!$type || $type === 'order') {
            $orders = SalesOrder::with(['customer', 'branch', 'items'])
                ->whereDate('order_date', '>=', $from)
                ->whereDate('order_date', '<=', $to)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($customerId, fn ($q) => $q->where('customer_id', $customerId))
                ->orderByDesc('order_date')
                ->get()
                ->map(fn ($o) => (object) [
                    'type'         => 'Order',
                    'number'       => $o->order_number,
                    'date'         => $o->order_date,
                    'valid_until'  => $o->expected_delivery_date,
                    'customer'     => $o->customer?->name ?? 'Walk-in',
                    'branch'       => $o->branch?->name,
                    'items_count'  => $o->items->count(),
                    'total'        => $o->total,
                    'status'       => $o->status,
                    'converted_bill_id' => $o->converted_sales_bill_id,
                    'show_url'     => route('sales.sales-orders.show', $o),
                ]);
        }

        $rows = $quotations->merge($orders)->sortByDesc('date')->values();

        $summary = [
            'total_quotations' => $quotations->count(),
            'total_orders'     => $orders->count(),
            'open'             => $rows->whereIn('status', ['Draft', 'Confirmed'])->count(),
            'converted'        => $rows->where('status', 'Converted')->count(),
            'cancelled'        => $rows->where('status', 'Cancelled')->count(),
        ];

        $branches   = Branch::orderBy('name')->pluck('name', 'id');
        $filterCustId = $request->input('customer_id');
        $customers  = Customer::where('status', true)->orderBy('name')->limit(30)->pluck('name', 'id');
        if ($filterCustId && ! isset($customers[$filterCustId])) {
            $fc = Customer::find($filterCustId);
            if ($fc) $customers->put($fc->id, $fc->name);
        }
        $statuses   = ['Draft', 'Confirmed', 'Converted', 'Cancelled'];

        return view('reports.quotation-order-summary', compact(
            'rows', 'summary', 'from', 'to', 'branchId', 'type', 'status',
            'customerId', 'branches', 'customers', 'statuses'
        ));
    }

    /**
     * Re-order / Low Stock Report — items with quantity at or below a threshold (≤5),
     * plus items that are completely out of stock.
     */
    public function reorderReport(Request $request)
    {
        $branchId        = $this->resolveBranchId($request);
        $brandId         = $request->input('brand_id');
        $categoryValueId = $request->input('category_value_id');
        $stockStatus     = $request->input('stock_status'); // 'out' | 'low'
        $search          = $request->input('search');
        $threshold       = (int) $request->input('threshold', 5); // default low-stock threshold

        $query = ItemStock::with(['item.brand', 'item.categoryValue', 'item.supplier', 'branch'])
            ->where('quantity', '<=', max($threshold, 0))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($brandId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('brand_id', $brandId)))
            ->when($categoryValueId, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('category_value_id', $categoryValueId)))
            ->when($stockStatus === 'out', fn ($q) => $q->where('quantity', '<=', 0))
            ->when($stockStatus === 'low', fn ($q) => $q->where('quantity', '>', 0)->where('quantity', '<=', $threshold))
            ->when($search, fn ($q) => $q->whereHas('item', fn ($iq) =>
                $iq->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%")
            ));

        $rows = $query->orderBy('quantity')->get()->map(function ($stock) use ($threshold) {
            return (object) [
                'item_code'     => $stock->item?->item_code,
                'item_name'     => $stock->item?->name,
                'brand_name'    => $stock->item?->brand?->name,
                'category_name' => $stock->item?->categoryValue?->name,
                'branch_name'   => $stock->branch?->name,
                'branch_id'     => $stock->branch_id,
                'quantity'      => $stock->quantity,
                'sell_price'    => $stock->sell_price,
                'mrp'           => $stock->mrp,
                'supplier_id'   => $stock->item?->supplier_id,
                'supplier_name' => $stock->item?->supplier?->name,
                'status'        => $stock->quantity <= 0 ? 'Out of Stock' : 'Low Stock',
            ];
        });

        $kpi = [
            'out_of_stock' => $rows->where('quantity', '<=', 0)->count(),
            'low_stock'    => $rows->where('quantity', '>', 0)->count(),
            'total_skus'   => $rows->count(),
        ];

        $branches   = Branch::orderBy('name')->pluck('name', 'id');
        $brands     = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'CATEGORY'))->orderBy('name')->pluck('name', 'id');

        return view('reports.reorder-report', compact(
            'rows', 'kpi', 'branchId', 'brandId', 'categoryValueId', 'stockStatus',
            'search', 'threshold', 'branches', 'brands', 'categories'
        ));
    }

    public function renderGenericReport(Request $request, string $module, \App\Services\Reports\DynamicReportService $reportService)
    {
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        [$from, $to, $branchId] = $this->dateAndBranchFilter($request);
        $search = $request->input('search');

        $reportData = $reportService->generate($request, $module);

        return view('reports.generic-report', array_merge($reportData, [
            'module' => $module,
            'branches' => $branches,
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'search' => $search,
        ]));
    }

    private function resolveBranchId(Request $request): ?int
    {
        if ($request->has('branch_id')) {
            $val = $request->input('branch_id');
            if ($val === 'all' || empty($val) || $val === '0') {
                return null;
            }
            return (int) $val;
        }

        $sessionBranch = session('active_branch_id');
        if ($sessionBranch && $sessionBranch !== 'all') {
            return (int) $sessionBranch;
        }

        return null;
    }

    private function dateAndBranchFilter(Request $request): array
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $this->resolveBranchId($request);

        return [$from, $to, $branchId];
    }

    /**
     * Stream query results directly to CSV download for full dataset export.
     */
    protected function exportQueryToCsv($query, array $columns, string $filename, ?callable $rowMapper = null): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query, $columns, $rowMapper) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Header row
            $headerLabels = array_map(function ($val, $key) {
                return is_string($key) ? $key : $val;
            }, array_values($columns), array_keys($columns));
            fputcsv($handle, $headerLabels);

            // Stream rows in chunks of 500
            $query->chunk(500, function ($rows) use ($handle, $columns, $rowMapper) {
                foreach ($rows as $item) {
                    if ($rowMapper !== null) {
                        $mapped = $rowMapper($item);
                        if (is_array($mapped) && isset($mapped[0]) && is_array($mapped[0])) {
                            foreach ($mapped as $subRow) {
                                fputcsv($handle, $subRow);
                            }
                        } elseif (is_array($mapped)) {
                            fputcsv($handle, $mapped);
                        }
                    } else {
                        $line = [];
                        foreach ($columns as $header => $extractor) {
                            if (is_callable($extractor)) {
                                $line[] = $extractor($item);
                            } else {
                                $line[] = data_get($item, $extractor, '');
                            }
                        }
                        fputcsv($handle, $line);
                    }
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}
