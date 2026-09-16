<?php

namespace App\Services\Reports;

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\DamageStock;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OpeningStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceiptNote;
use App\Models\PurchaseReturn;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\SalesReturn;
use App\Models\StockTransfer;
use App\Models\StockUpdate;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DynamicReportService
{
    public function generate(Request $request, string $module): array
    {
        $from = $request->input('from', now()->subDays(60)->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $request->input('branch_id');
        $search = trim($request->input('search', ''));

        // Normalize slug
        $slug = strtolower(trim($module));

        // Master reports
        if ($this->isMasterReport($slug)) {
            return $this->handleMasterReport($slug, $search, $branchId);
        }

        // Purchase reports
        if ($this->isPurchaseReport($slug)) {
            return $this->handlePurchaseReport($slug, $search, $branchId, $from, $to);
        }

        // Audit reports
        if ($this->isAuditReport($slug)) {
            return $this->handleAuditReport($slug, $search, $from, $to);
        }

        // Sales reports
        if ($this->isSalesReport($slug)) {
            return $this->handleSalesReport($slug, $search, $branchId, $from, $to);
        }

        // Inventory reports
        if ($this->isInventoryReport($slug)) {
            return $this->handleInventoryReport($slug, $search, $branchId, $from, $to);
        }

        // My reports, Production & More
        return $this->handleAuxReport($slug, $search, $branchId, $from, $to);
    }

    private function isMasterReport(string $slug): bool
    {
        return in_array($slug, [
            'brand-master', 'tax-master', 'area', 'branch-master', 'price-list',
            'supplier-vs-items', 'employee-master', 'uom-vs-item-mapping',
            'kit-mapping', 'customer-parent-list', 'ro-master'
        ]);
    }

    private function isPurchaseReport(string $slug): bool
    {
        return in_array($slug, [
            'purchase-summary', 'purchase-order-details', 'purchase-transit',
            'receiptnote-nowise-summary', 'receiptnote-nowise-detail', 'receiptnote-itemwise',
            'gin-summary', 'gin-detail', 'purchase-detail-serial', 'po-vs-invoice',
            'purchase-pending-cancelled', 'purchase-returned-transactions',
            'supplierwise-purchase-summary', 'po-purchase-discrepancy',
            'supplierwise-purchase-details', 'datewise-itemwise-purchase',
            'purchase-register-summary'
        ]);
    }

    private function isAuditReport(string $slug): bool
    {
        return in_array($slug, [
            'audit-detail-report', 'user-login-summary', 'gst-tax-change-audit',
            'foot-fall-details', 'reprint-count-details', 'service-user-consent',
            'email-audit-viewer', 'audit-cart-clear', 'audit-detail-cart-clear'
        ]);
    }

    private function isSalesReport(string $slug): bool
    {
        return in_array($slug, [
            'monthly-sales-summary-storewise', 'monthly-sales-summary-tillwise',
            'daily-sales-summary-tillwise', 'daily-sales-billwise', 'daily-sales-timefilter',
            'serial-wise-price-details', 'offline-sales-bill-details', 'billwise-sales-serialwise',
            'gst-sales-taxwise', 'billwise-sales-assembly', 'sales-register-summary',
            'offer-claim-report', 'counterwise-sales-report', 'customerwise-itemwise-sales',
            'itemwise-customerwise-sales', 'categorywise-sales-detail',
            'quotation-details', 'sales-order-summary', 'sales-order-detail', 'sales-order-stock-status',
            'sales-deliverynote-summary', 'sales-deliverynote-detail', 'kitchen-preparation-report',
            'delivery-bill-summary', 'delivery-bill-detail', 'kitchen-preparation-timewise',
            'sales-return-advice-detail', 'buy-back-details', 'sale-return-customerwise',
            'sales-return-itemwise', 'sale-return-datewise', 'sale-return-monthwise',
            'sales-cancelled-transactions', 'margin-summary', 'supplier-sales-report',
            'supplierwise-sales-details', 'date-timewise-sales', 'consumption-sales-summary',
            'consumption-sales-detail', 'areawise-sales-summary', 'monthly-sales-detail',
            'non-purchase-customer-list', 'counterwise-datewise-sales',
            'itemwise-monthly-sales-details', 'hold-bill-details', 'itemwise-discount-approval'
        ]);
    }

    private function isInventoryReport(string $slug): bool
    {
        return in_array($slug, [
            'itemwise-stock-statement', 'itemwise-stock-sales-detail', 'itemwise-stock-sales-transit',
            'transactionwise-stock-register', 'closing-stock', 'categorywise-datewise-stock',
            'branchwise-stock-age-analysis', 'categorywise-stock-age-analysis',
            'inventory-price-drop', 'inventory-price-level-advance',
            'transfer-out-approval-detail', 'stock-transferout-detail', 'itemwise-storewise-transferout',
            'stock-transferin-summary', 'stock-transferin-detail', 'itemwise-storewise-transferin',
            'stock-in-transit', 'stock-transfer-discrepancy', 'to-vs-tin', 'stock-conversion-report',
            'stock-update-detail', 'categorywise-storewise-current-stock', 'stock-fast-moving',
            'stock-slow-moving', 'wastage-damage-stock-detail', 'item-age-analysis',
            'item-expiry-update-details', 'items-details-in-cart', 'stock-reserve-status',
            'mbq-detail', 'kit-preparation', 'kit-unpack', 'itemwise-stock-transfer-advice',
            'indent-based-replenishment', 'indent-summary', 'po-replenishment',
            'picklist-detail', 'picking-list-discrepancy', 'opening-stock-detail',
            'repack-summary', 'eancode-detail', 'repack-detail', 'issue-date-expiry-details'
        ]);
    }

    /* ----------------------------------------------------------------------
     * 1. MASTERS HANDLER
     * ---------------------------------------------------------------------- */
    private function handleMasterReport(string $slug, string $search, ?string $branchId): array
    {
        $hasDateFilter = false;
        $hasBranchFilter = false;

        switch ($slug) {
            case 'brand-master':
                $query = Brand::query()
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('prefix', 'like', "%{$search}%"))
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        e($item->name),
                        e($item->prefix ?: '-'),
                        e($item->alias_code ?: '-'),
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>',
                        $item->created_at ? $item->created_at->format('d M Y') : '-'
                    ]
                ]);
                return [
                    'title' => 'Brand Master Report',
                    'subtitle' => 'Comprehensive directory of all product brands and manufacturers',
                    'columns' => ['#', 'Brand Name', 'Prefix / Code', 'Alias', 'Status', 'Registered Date'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-center', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Brands', 'value' => Brand::count(), 'icon' => 'fas fa-tag', 'color' => 'primary'],
                        ['label' => 'Active Brands', 'value' => Brand::where('status', 1)->count(), 'icon' => 'fas fa-check-circle', 'color' => 'success'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'tax-master':
                $query = GstTax::query()
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('rate', 'like', "%{$search}%"))
                    ->orderBy('rate');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        e($item->name),
                        number_format($item->rate, 2) . '%',
                        number_format($item->cgst_rate, 2) . '%',
                        number_format($item->sgst_rate, 2) . '%',
                        number_format($item->igst_rate, 2) . '%',
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'
                    ]
                ]);
                return [
                    'title' => 'GST Tax Master Report',
                    'subtitle' => 'Configured GST tax slabs with CGST, SGST, and IGST breakdowns',
                    'columns' => ['#', 'Tax Name', 'Total GST Rate', 'CGST Rate', 'SGST Rate', 'IGST Rate', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Configured Tax Slabs', 'value' => GstTax::count(), 'icon' => 'fas fa-percentage', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'area':
                $query = Area::with('branch')
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        e($item->name),
                        e($item->code ?: '-'),
                        e($item->branch?->name ?: 'All Branches'),
                        e($item->city ?: '-'),
                        e($item->state ?: '-'),
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'
                    ]
                ]);
                return [
                    'title' => 'Area Master Report',
                    'subtitle' => 'Delivery and customer locality zones',
                    'columns' => ['#', 'Area Name', 'Area Code', 'Branch', 'City', 'State', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-left', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Defined Areas', 'value' => Area::count(), 'icon' => 'fas fa-map-marker-alt', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'branch-master':
                $query = Branch::withCount(['stocks', 'salesBills'])->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->name) . '</strong>',
                        e($item->code ?: '-'),
                        e($item->phone ?: '-'),
                        e($item->address ?: '-'),
                        e($item->gst_number ?: '-'),
                        number_format($item->sales_bills_count),
                        number_format($item->stocks_count),
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'
                    ]
                ]);
                return [
                    'title' => 'Branch Master Report',
                    'subtitle' => 'List of registered POS stores, distribution centres and warehouses',
                    'columns' => ['#', 'Branch Name', 'Code', 'Phone', 'Address', 'GSTIN', 'Total Bills', 'Stock Batches', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Operational Branches', 'value' => Branch::where('status', 1)->count(), 'icon' => 'fas fa-store', 'color' => 'success'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'price-list':
                $query = Item::with(['brand', 'categoryValue', 'supplier'])
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%")->orWhere('ean_upc_code', 'like', "%{$search}%"))
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<code>' . e($item->item_code ?: $item->id) . '</code>',
                        e($item->name),
                        e($item->brand?->name ?: '-'),
                        e($item->categoryValue?->name ?: '-'),
                        '₹ ' . number_format($item->mrp, 2),
                        '<strong>₹ ' . number_format($item->sell_price, 2) . '</strong>',
                        '₹ ' . number_format($item->cost_price, 2),
                        $item->mrp > 0 ? number_format((($item->sell_price - $item->cost_price) / max($item->sell_price, 1)) * 100, 1) . '%' : '0.0%'
                    ]
                ]);
                return [
                    'title' => 'Price List Report',
                    'subtitle' => 'Item-wise pricing master detailing MRP, Selling Price, Cost, and Profit Margin',
                    'columns' => ['#', 'Item Code', 'Item Description', 'Brand', 'Category', 'MRP', 'Selling Price', 'Cost Price', 'Margin %'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Priced SKUs', 'value' => Item::count(), 'icon' => 'fas fa-tags', 'color' => 'primary'],
                        ['label' => 'Avg Sell Price', 'value' => '₹ ' . number_format(Item::avg('sell_price') ?: 0, 2), 'icon' => 'fas fa-coins', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'supplier-vs-items':
                $query = Item::with(['supplier', 'brand'])
                    ->whereNotNull('supplier_id')
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%")))
                    ->orderBy('supplier_id')
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->supplier?->name ?: '-') . '</strong>',
                        '<code>' . e($item->item_code ?: $item->id) . '</code>',
                        e($item->name),
                        e($item->brand?->name ?: '-'),
                        '₹ ' . number_format($item->cost_price, 2),
                        '₹ ' . number_format($item->sell_price, 2),
                        '₹ ' . number_format($item->mrp, 2)
                    ]
                ]);
                return [
                    'title' => 'Supplier vs Items Mapping Report',
                    'subtitle' => 'Primary vendor supply catalogue with acquisition and retail pricing',
                    'columns' => ['#', 'Supplier Name', 'Item Code', 'Item Name', 'Brand', 'Cost Rate', 'Selling Rate', 'MRP'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Mapped SKUs', 'value' => Item::whereNotNull('supplier_id')->count(), 'icon' => 'fas fa-handshake', 'color' => 'primary'],
                        ['label' => 'Active Suppliers', 'value' => Supplier::count(), 'icon' => 'fas fa-truck', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'employee-master':
                $query = User::with(['branch', 'roles'])
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->name) . '</strong>',
                        e($item->email),
                        e($item->branch?->name ?: 'All Branches'),
                        '<span class="badge badge-info">' . e($item->roles->pluck('name')->join(', ') ?: 'Staff') . '</span>',
                        e($item->phone ?: '-'),
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>',
                        $item->created_at ? $item->created_at->format('d M Y') : '-'
                    ]
                ]);
                return [
                    'title' => 'Employee Master Report',
                    'subtitle' => 'Registered staff members, POS cashiers, assigned store branches, and security roles',
                    'columns' => ['#', 'Employee Name', 'Email Address', 'Branch', 'Assigned Roles', 'Contact', 'Status', 'Joined Date'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-center', 'text-left', 'text-center', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Registered Staff', 'value' => User::count(), 'icon' => 'fas fa-user-tie', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            default:
                // General fallback for remaining masters (uom-vs-item-mapping, kit-mapping, customer-parent-list, ro-master)
                $query = Item::with(['brand', 'categoryValue'])
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%"))
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<code>' . e($item->item_code ?: $item->id) . '</code>',
                        e($item->name),
                        e($item->brand?->name ?: '-'),
                        e($item->product_type ?: 'Standard'),
                        '₹ ' . number_format($item->sell_price, 2),
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'
                    ]
                ]);
                return [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)) . ' Report',
                    'subtitle' => 'Master records and classification attributes',
                    'columns' => ['#', 'Code', 'Name / Particulars', 'Brand / Group', 'Type', 'Rate', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Records', 'value' => Item::count(), 'icon' => 'fas fa-database', 'color' => 'primary']
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];
        }
    }

    /* ----------------------------------------------------------------------
     * 2. PURCHASE HANDLER
     * ---------------------------------------------------------------------- */
    private function handlePurchaseReport(string $slug, string $search, ?string $branchId, string $from, string $to): array
    {
        switch ($slug) {
            case 'purchase-summary':
            case 'purchase-register-summary':
                $query = PurchaseInvoice::with(['supplier', 'branch'])
                    ->whereBetween('invoice_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('invoice_number', 'like', "%{$search}%")->orWhere('supplier_inv_no', 'like', "%{$search}%")->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%")))
                    ->orderBy('invoice_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->invoice_number) . '</strong>',
                        e($item->supplier_inv_no ?: '-'),
                        $item->invoice_date ? date('d M Y', strtotime($item->invoice_date)) : '-',
                        e($item->supplier?->name ?: '-'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->total - $item->total_gst, 2),
                        '₹ ' . number_format($item->total_gst, 2),
                        '<strong>₹ ' . number_format($item->total, 2) . '</strong>',
                        '<span class="badge badge-success">' . e($item->status ?: 'Completed') . '</span>'
                    ]
                ]);
                $totalSum = PurchaseInvoice::whereBetween('invoice_date', [$from, $to])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('total');
                $totalQty = PurchaseInvoice::whereBetween('invoice_date', [$from, $to])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('total_qty');
                return [
                    'title' => 'Purchase Invoice Summary Report',
                    'subtitle' => 'Consolidated list of purchase invoices, vendor bills, taxable amounts, and GST ITC',
                    'columns' => ['#', 'Invoice No', 'Vendor Bill No', 'Date', 'Supplier', 'Branch', 'Total Qty', 'Taxable Amt', 'GST Tax', 'Net Amount', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Purchase Value', 'value' => '₹ ' . number_format($totalSum, 2), 'icon' => 'fas fa-rupee-sign', 'color' => 'success'],
                        ['label' => 'Total Qty Received', 'value' => number_format($totalQty), 'icon' => 'fas fa-boxes', 'color' => 'primary'],
                        ['label' => 'Invoices Count', 'value' => $paginator->total(), 'icon' => 'fas fa-file-invoice', 'color' => 'info'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];

            case 'purchase-order-details':
                $query = DB::table('purchase_order_items')
                    ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
                    ->join('items', 'purchase_order_items.item_id', '=', 'items.id')
                    ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
                    ->leftJoin('branches', 'purchase_orders.branch_id', '=', 'branches.id')
                    ->whereBetween('purchase_orders.po_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('purchase_orders.branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('purchase_orders.po_number', 'like', "%{$search}%")->orWhere('items.name', 'like', "%{$search}%"))
                    ->selectRaw("purchase_orders.po_number, purchase_orders.po_date, suppliers.name as supplier_name, branches.name as branch_name, items.item_code, items.name as item_name, purchase_order_items.qty, purchase_order_items.cost_price, purchase_order_items.gst_tax_amount, purchase_order_items.net_amount")
                    ->orderBy('purchase_orders.po_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->po_number) . '</strong>',
                        date('d M Y', strtotime($item->po_date)),
                        e($item->supplier_name ?: '-'),
                        '<code>' . e($item->item_code) . '</code>',
                        e($item->item_name),
                        number_format($item->qty),
                        '₹ ' . number_format($item->cost_price, 2),
                        '₹ ' . number_format($item->gst_tax_amount, 2),
                        '<strong>₹ ' . number_format($item->net_amount, 2) . '</strong>'
                    ]
                ]);
                return [
                    'title' => 'Purchase Order Details Report',
                    'subtitle' => 'Line-item breakdown of purchase orders placed with suppliers',
                    'columns' => ['#', 'PO Number', 'PO Date', 'Supplier', 'Item Code', 'Item Description', 'Order Qty', 'Rate', 'GST Tax', 'Net Amount'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Line Items', 'value' => $paginator->total(), 'icon' => 'fas fa-list', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];

            case 'supplierwise-purchase-summary':
                $query = PurchaseInvoice::join('suppliers', 'purchase_invoices.supplier_id', '=', 'suppliers.id')
                    ->whereBetween('purchase_invoices.invoice_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('purchase_invoices.branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('suppliers.name', 'like', "%{$search}%"))
                    ->groupBy('suppliers.id', 'suppliers.name')
                    ->selectRaw("suppliers.name as supplier_name, COUNT(purchase_invoices.id) as invoice_count, SUM(purchase_invoices.total_qty) as total_qty, SUM(purchase_invoices.total - purchase_invoices.total_gst) as taxable_amount, SUM(purchase_invoices.total_gst) as total_tax, SUM(purchase_invoices.total) as total_amount")
                    ->orderBy('total_amount', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->supplier_name) . '</strong>',
                        number_format($item->invoice_count),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->taxable_amount, 2),
                        '₹ ' . number_format($item->total_tax, 2),
                        '<strong>₹ ' . number_format($item->total_amount, 2) . '</strong>'
                    ]
                ]);
                return [
                    'title' => 'Supplier-wise Purchase Summary Report',
                    'subtitle' => 'Vendor procurement aggregation with order volumes, tax, and expenditures',
                    'columns' => ['#', 'Supplier Name', 'Total Invoices', 'Total Qty', 'Taxable Value', 'GST Amount', 'Total Purchase Value'],
                    'column_alignments' => ['text-center', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Suppliers Billed', 'value' => $paginator->total(), 'icon' => 'fas fa-truck', 'color' => 'info'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];

            case 'purchase-pending-cancelled':
                $query = PurchaseOrder::with(['supplier', 'branch'])
                    ->whereIn('status', ['Cancelled', 'Draft', 'Pending', 'submitted'])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('po_number', 'like', "%{$search}%")->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%")))
                    ->orderBy('po_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->po_number) . '</strong>',
                        $item->po_date ? date('d M Y', strtotime($item->po_date)) : '-',
                        e($item->supplier?->name ?: '-'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->total, 2),
                        '<span class="badge badge-warning">' . e($item->status ?: 'Pending') . '</span>',
                        e($item->cancellation_reason ?: '-')
                    ]
                ]);
                return [
                    'title' => 'Pending / Cancelled Purchase Orders Report',
                    'subtitle' => 'Purchase orders awaiting delivery or marked as cancelled',
                    'columns' => ['#', 'PO Number', 'Date', 'Supplier', 'Branch', 'Total Qty', 'Order Value', 'Status', 'Cancellation Reason'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-center', 'text-left'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Pending / Cancelled Orders', 'value' => $paginator->total(), 'icon' => 'fas fa-ban', 'color' => 'warning'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            default:
                // General fallback for remaining purchase reports
                $query = PurchaseInvoice::with(['supplier', 'branch'])
                    ->whereBetween('invoice_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('invoice_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->invoice_number) . '</strong>',
                        date('d M Y', strtotime($item->invoice_date)),
                        e($item->supplier?->name ?: '-'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->total, 2),
                        '<span class="badge badge-success">' . e($item->status ?: 'Completed') . '</span>'
                    ]
                ]);
                return [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)) . ' Report',
                    'subtitle' => 'Procurement transaction details from supplier inwards and receipts',
                    'columns' => ['#', 'Ref / Doc No', 'Date', 'Supplier', 'Branch', 'Total Qty', 'Total Amount', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Transactions', 'value' => $paginator->total(), 'icon' => 'fas fa-shopping-bag', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];
        }
    }

    /* ----------------------------------------------------------------------
     * 3. AUDIT HANDLER
     * ---------------------------------------------------------------------- */
    private function handleAuditReport(string $slug, string $search, string $from, string $to): array
    {
        switch ($slug) {
            case 'user-login-summary':
                $query = User::with(['branch', 'roles'])
                    ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orderBy('name');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->name) . '</strong>',
                        e($item->email),
                        '<span class="badge badge-info">' . e($item->roles->pluck('name')->join(', ') ?: 'Staff') . '</span>',
                        e($item->branch?->name ?: 'All Branches'),
                        $item->status ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Disabled</span>',
                        e($item->updated_at ? $item->updated_at->diffForHumans() : 'Never')
                    ]
                ]);
                return [
                    'title' => 'User Login & Access Summary Report',
                    'subtitle' => 'POS user login sessions, security roles, and active authentication logs',
                    'columns' => ['#', 'User / Cashier Name', 'Login Email', 'Role Assigned', 'Branch', 'Account Status', 'Last Activity'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-center', 'text-left', 'text-center', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Users', 'value' => User::count(), 'icon' => 'fas fa-users', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'gst-tax-change-audit':
                $query = AuditLog::with('user')
                    ->where(function ($q) {
                        $q->where('model', 'like', '%Tax%')
                          ->orWhere('model', 'like', '%Item%')
                          ->orWhere('description', 'like', '%tax%');
                    })
                    ->latest();
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        $item->created_at ? $item->created_at->format('d M Y H:i:s') : '-',
                        e($item->user?->name ?: 'System'),
                        '<span class="badge badge-warning">' . e($item->event ?: 'Updated') . '</span>',
                        e($item->model ?: 'GstTax'),
                        e($item->description ?: 'Tax configuration modified'),
                        e($item->ip_address ?: '127.0.0.1')
                    ]
                ]);
                return [
                    'title' => 'GST Tax Change Audit Report',
                    'subtitle' => 'Regulatory audit track of any tax rate, HSN, or slab changes',
                    'columns' => ['#', 'Timestamp', 'User', 'Action', 'Target Model', 'Audit Description', 'IP Address'],
                    'column_alignments' => ['text-center', 'text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Tax Audit Events', 'value' => $paginator->total(), 'icon' => 'fas fa-percentage', 'color' => 'warning'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            default:
                $query = AuditLog::with('user')
                    ->when($search, fn ($q) => $q->where('description', 'like', "%{$search}%")->orWhere('event', 'like', "%{$search}%")->orWhere('model', 'like', "%{$search}%"))
                    ->latest();
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        $item->created_at ? $item->created_at->format('d M Y H:i:s') : '-',
                        e($item->user?->name ?: 'System Admin'),
                        '<span class="badge badge-secondary">' . e($item->event ?: 'Activity') . '</span>',
                        e($item->model ?: 'General'),
                        e($item->description ?: '-'),
                        '<code>' . e($item->ip_address ?: '127.0.0.1') . '</code>'
                    ]
                ]);
                return [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)) . ' Report',
                    'subtitle' => 'System activity log, audit footprints, and user operation histories',
                    'columns' => ['#', 'Date & Time', 'Operator', 'Action Type', 'Entity / Module', 'Description', 'IP Address'],
                    'column_alignments' => ['text-center', 'text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Audit Logs', 'value' => AuditLog::count(), 'icon' => 'fas fa-shield-alt', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];
        }
    }

    /* ----------------------------------------------------------------------
     * 4. SALES HANDLER
     * ---------------------------------------------------------------------- */
    private function handleSalesReport(string $slug, string $search, ?string $branchId, string $from, string $to): array
    {
        switch ($slug) {
            case 'monthly-sales-summary-storewise':
                $query = SalesBill::join('branches', 'sales_bills.branch_id', '=', 'branches.id')
                    ->when($branchId, fn ($q) => $q->where('sales_bills.branch_id', $branchId))
                    ->groupBy(DB::raw("DATE_FORMAT(sales_bills.bill_date, '%Y-%m')"), 'branches.name')
                    ->selectRaw("DATE_FORMAT(sales_bills.bill_date, '%Y-%m') as sales_month, branches.name as branch_name, COUNT(sales_bills.id) as bill_count, SUM(sales_bills.total_qty) as total_qty, SUM(sales_bills.disc_amount) as total_disc, SUM(sales_bills.total_gst) as total_gst, SUM(sales_bills.total) as total_sales")
                    ->orderBy('sales_month', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . date('F Y', strtotime($item->sales_month . '-01')) . '</strong>',
                        e($item->branch_name),
                        number_format($item->bill_count),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->total_disc, 2),
                        '₹ ' . number_format($item->total_gst, 2),
                        '<strong>₹ ' . number_format($item->total_sales, 2) . '</strong>'
                    ]
                ]);
                return [
                    'title' => 'Monthly Sales Summary [Storewise]',
                    'subtitle' => 'Consolidated monthly revenue, discounts, tax collections, and transaction counts per store location',
                    'columns' => ['#', 'Billing Month', 'Branch / Store', 'Total Bills', 'Units Sold', 'Total Discount', 'GST Collected', 'Gross Sales Revenue'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Sales', 'value' => '₹ ' . number_format(SalesBill::sum('total'), 2), 'icon' => 'fas fa-rupee-sign', 'color' => 'success'],
                        ['label' => 'Total Bills Processed', 'value' => SalesBill::count(), 'icon' => 'fas fa-receipt', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'daily-sales-billwise':
            case 'daily-sales-timefilter':
            case 'offline-sales-bill-details':
                $query = SalesBill::with(['customer', 'branch'])
                    ->whereBetween('bill_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('bill_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")))
                    ->orderBy('bill_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->bill_number) . '</strong>',
                        $item->bill_date ? date('d M Y H:i', strtotime($item->bill_date)) : '-',
                        e($item->customer?->name ?: 'Walk-in Customer'),
                        e($item->customer?->mobile ?: '-'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->disc_amount, 2),
                        '₹ ' . number_format($item->total_gst, 2),
                        '<strong>₹ ' . number_format($item->total, 2) . '</strong>',
                        '<span class="badge badge-success">' . e($item->status ?: 'Completed') . '</span>'
                    ]
                ]);
                $totalSum = SalesBill::whereBetween('bill_date', [$from, $to])->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('total');
                return [
                    'title' => 'Daily Sales [Bill No Wise] Report',
                    'subtitle' => 'Detailed bill-level sales register including customer particulars, taxes, and amounts',
                    'columns' => ['#', 'Bill Number', 'Bill Date & Time', 'Customer Name', 'Mobile', 'Branch', 'Items Qty', 'Discount', 'GST Tax', 'Net Total', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Period Sales Total', 'value' => '₹ ' . number_format($totalSum, 2), 'icon' => 'fas fa-rupee-sign', 'color' => 'success'],
                        ['label' => 'Total Bills', 'value' => $paginator->total(), 'icon' => 'fas fa-file-invoice-dollar', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];

            case 'customerwise-itemwise-sales':
            case 'itemwise-customerwise-sales':
                $query = DB::table('sales_bill_items')
                    ->join('sales_bills', 'sales_bill_items.sales_bill_id', '=', 'sales_bills.id')
                    ->join('items', 'sales_bill_items.item_id', '=', 'items.id')
                    ->leftJoin('customers', 'sales_bills.customer_id', '=', 'customers.id')
                    ->leftJoin('branches', 'sales_bills.branch_id', '=', 'branches.id')
                    ->whereBetween('sales_bills.bill_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('sales_bills.branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('customers.name', 'like', "%{$search}%")->orWhere('items.name', 'like', "%{$search}%")->orWhere('sales_bills.bill_number', 'like', "%{$search}%"))
                    ->selectRaw("customers.name as customer_name, customers.mobile as customer_mobile, sales_bills.bill_number, sales_bills.bill_date, items.item_code, items.name as item_name, sales_bill_items.qty, sales_bill_items.sell_price, sales_bill_items.net_amount")
                    ->orderBy('sales_bills.bill_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->customer_name ?: 'Walk-in Customer') . '</strong>',
                        e($item->customer_mobile ?: '-'),
                        '<code>' . e($item->bill_number) . '</code>',
                        '<code>' . e($item->item_code) . '</code>',
                        e($item->item_name),
                        number_format($item->qty),
                        '₹ ' . number_format($item->sell_price, 2),
                        '<strong>₹ ' . number_format($item->net_amount, 2) . '</strong>'
                    ]
                ]);
                return [
                    'title' => 'Customer-wise Item-wise Sales Report',
                    'subtitle' => 'Detailed product lines purchased by each customer with selling price and net totals',
                    'columns' => ['#', 'Customer Name', 'Mobile', 'Bill No', 'Item Code', 'Item Description', 'Qty', 'Unit Rate', 'Net Amount'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Lines', 'value' => $paginator->total(), 'icon' => 'fas fa-list', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];

            case 'sales-order-summary':
            case 'quotation-details':
                $query = SalesOrder::with(['customer', 'branch'])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('order_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")))
                    ->orderBy('order_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->order_number) . '</strong>',
                        $item->order_date ? date('d M Y', strtotime($item->order_date)) : '-',
                        e($item->customer?->name ?: 'Customer'),
                        e($item->branch?->name ?: '-'),
                        '₹ ' . number_format($item->total_gst, 2),
                        '<strong>₹ ' . number_format($item->total, 2) . '</strong>',
                        '₹ ' . number_format($item->advance_amount, 2),
                        '<span class="badge badge-info">' . e($item->status ?: 'Pending') . '</span>'
                    ]
                ]);
                return [
                    'title' => 'Sales Order & Quotation Details Report',
                    'subtitle' => 'Customer advance orders, quotations, and booking pipeline',
                    'columns' => ['#', 'Order / Quote No', 'Date', 'Customer Name', 'Branch', 'GST Amount', 'Total Order Value', 'Advance Paid', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Orders Count', 'value' => $paginator->total(), 'icon' => 'fas fa-shopping-basket', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'sales-deliverynote-summary':
            case 'sales-deliverynote-detail':
                $query = SalesDeliveryNote::with(['customer', 'branch'])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('delivery_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")))
                    ->orderBy('delivery_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->delivery_number) . '</strong>',
                        $item->delivery_date ? date('d M Y', strtotime($item->delivery_date)) : '-',
                        e($item->customer?->name ?: 'Customer'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->total_ordered_qty),
                        number_format($item->total_dispatched_qty),
                        '₹ ' . number_format($item->total_amount, 2),
                        '<span class="badge badge-primary">' . e($item->status ?: 'Dispatched') . '</span>'
                    ]
                ]);
                return [
                    'title' => 'Sales Delivery Note Summary Report',
                    'subtitle' => 'Home delivery notes, dispatched quantities, and vehicle dispatch logs',
                    'columns' => ['#', 'Delivery Note No', 'Date', 'Customer', 'Branch', 'Ordered Qty', 'Dispatched Qty', 'Total Value', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Deliveries', 'value' => $paginator->total(), 'icon' => 'fas fa-truck', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'sale-return-customerwise':
            case 'sales-return-itemwise':
            case 'sale-return-datewise':
            case 'sale-return-monthwise':
                $query = SalesReturn::with(['customer', 'branch', 'salesBill'])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('return_number', 'like', "%{$search}%")->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")))
                    ->orderBy('return_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->return_number) . '</strong>',
                        $item->return_date ? date('d M Y', strtotime($item->return_date)) : '-',
                        e($item->customer?->name ?: 'Customer'),
                        e($item->salesBill?->bill_number ?: '-'),
                        e($item->branch?->name ?: '-'),
                        '₹ ' . number_format($item->total_gst, 2),
                        '<strong>₹ ' . number_format($item->total, 2) . '</strong>',
                        '<span class="badge badge-danger">' . e($item->return_mode ?: 'Credit') . '</span>'
                    ]
                ]);
                return [
                    'title' => 'Sales Return Transaction Report',
                    'subtitle' => 'Customer merchandise returns, credit notes, and refund analysis',
                    'columns' => ['#', 'Return Number', 'Date', 'Customer', 'Original Bill', 'Branch', 'GST Reversal', 'Refund Amount', 'Return Mode'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Returns Recorded', 'value' => $paginator->total(), 'icon' => 'fas fa-undo', 'color' => 'danger'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            default:
                // General fallback for remaining sales reports
                $query = SalesBill::with(['customer', 'branch'])
                    ->whereBetween('bill_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('bill_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->bill_number) . '</strong>',
                        date('d M Y', strtotime($item->bill_date)),
                        e($item->customer?->name ?: 'Walk-in Customer'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->total_gst, 2),
                        '<strong>₹ ' . number_format($item->total, 2) . '</strong>',
                        '<span class="badge badge-success">' . e($item->status ?: 'Completed') . '</span>'
                    ]
                ]);
                return [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)) . ' Report',
                    'subtitle' => 'Retail counter sales summary and performance metrics',
                    'columns' => ['#', 'Bill Reference', 'Date', 'Customer', 'Branch', 'Quantity', 'GST Amount', 'Total Revenue', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Transactions', 'value' => $paginator->total(), 'icon' => 'fas fa-cash-register', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];
        }
    }

    /* ----------------------------------------------------------------------
     * 5. INVENTORY HANDLER
     * ---------------------------------------------------------------------- */
    private function handleInventoryReport(string $slug, string $search, ?string $branchId, string $from, string $to): array
    {
        switch ($slug) {
            case 'closing-stock':
            case 'itemwise-stock-statement':
            case 'itemwise-stock-sales-detail':
                $query = ItemStock::with(['item.brand', 'item.categoryValue', 'branch'])
                    ->where('quantity', '>', 0)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->when($search, fn ($q) => $q->whereHas('item', fn ($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('item_code', 'like', "%{$search}%")))
                    ->orderBy('branch_id')
                    ->orderBy('quantity', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<code>' . e($item->item?->item_code ?: $item->item_id) . '</code>',
                        e($item->item?->name ?: '-'),
                        e($item->item?->brand?->name ?: '-'),
                        e($item->branch?->name ?: '-'),
                        e($item->batch_number ?: 'Default'),
                        e($item->expiry_date ? date('d M Y', strtotime($item->expiry_date)) : 'N/A'),
                        '<strong>' . number_format($item->quantity) . '</strong>',
                        '₹ ' . number_format($item->cost_price ?: $item->item?->cost_price, 2),
                        '₹ ' . number_format($item->sell_price ?: $item->item?->sell_price, 2),
                        '<strong>₹ ' . number_format($item->quantity * ($item->cost_price ?: $item->item?->cost_price), 2) . '</strong>'
                    ]
                ]);
                $totalStockVal = ItemStock::where('quantity', '>', 0)->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum(DB::raw('quantity * cost_price'));
                return [
                    'title' => 'Closing Stock & Valuation Report',
                    'subtitle' => 'Live on-hand inventory quantities per batch with cost valuation and expiry dates',
                    'columns' => ['#', 'Item Code', 'Item Description', 'Brand', 'Branch', 'Batch No', 'Expiry Date', 'Closing Stock', 'Cost Price', 'Selling Price', 'Valuation (Cost)'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-left', 'text-left', 'text-center', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Inventory Value', 'value' => '₹ ' . number_format($totalStockVal ?: 0, 2), 'icon' => 'fas fa-warehouse', 'color' => 'success'],
                        ['label' => 'In-Stock Batches', 'value' => $paginator->total(), 'icon' => 'fas fa-boxes', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'stock-update-detail':
                $query = DB::table('stock_update_items')
                    ->join('stock_updates', 'stock_update_items.stock_update_id', '=', 'stock_updates.id')
                    ->join('items', 'stock_update_items.item_id', '=', 'items.id')
                    ->leftJoin('branches', 'stock_updates.branch_id', '=', 'branches.id')
                    ->when($branchId, fn ($q) => $q->where('stock_updates.branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('stock_updates.update_number', 'like', "%{$search}%")->orWhere('items.name', 'like', "%{$search}%")->orWhere('items.item_code', 'like', "%{$search}%"))
                    ->selectRaw("stock_updates.update_number, stock_updates.entry_date, branches.name as branch_name, items.item_code, items.name as item_name, stock_update_items.system_qty_at_entry as sys_qty, stock_update_items.physical_qty as phys_qty, stock_update_items.delta_qty, stock_updates.remarks, stock_updates.status")
                    ->orderBy('stock_updates.entry_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->update_number) . '</strong>',
                        $item->entry_date ? date('d M Y', strtotime($item->entry_date)) : '-',
                        e($item->branch_name ?: '-'),
                        '<code>' . e($item->item_code) . '</code>',
                        e($item->item_name),
                        number_format($item->sys_qty),
                        number_format($item->phys_qty),
                        '<span class="' . ($item->delta_qty < 0 ? 'text-danger font-weight-bold' : ($item->delta_qty > 0 ? 'text-success font-weight-bold' : '')) . '">' . ($item->delta_qty > 0 ? '+' : '') . number_format($item->delta_qty) . '</span>',
                        e($item->remarks ?: '-'),
                        '<span class="badge badge-success">' . e($item->status ?: 'Completed') . '</span>'
                    ]
                ]);
                return [
                    'title' => 'Stock Physical Verification & Update Detail Report',
                    'subtitle' => 'Audit log of physical stock count reconciliations, system variance deltas, and inventory adjustments',
                    'columns' => ['#', 'Update Number', 'Entry Date', 'Branch', 'Item Code', 'Item Description', 'System Qty', 'Physical Count', 'Discrepancy (Delta)', 'Remarks', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-left', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Audited Items', 'value' => DB::table('stock_update_items')->count(), 'icon' => 'fas fa-clipboard-check', 'color' => 'primary'],
                        ['label' => 'Physical Count Sessions', 'value' => StockUpdate::count(), 'icon' => 'fas fa-sync-alt', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'wastage-damage-stock-detail':
                $query = DB::table('damage_stock_items')
                    ->join('damage_stocks', 'damage_stock_items.damage_stock_id', '=', 'damage_stocks.id')
                    ->join('items', 'damage_stock_items.item_id', '=', 'items.id')
                    ->leftJoin('branches', 'damage_stocks.branch_id', '=', 'branches.id')
                    ->when($branchId, fn ($q) => $q->where('damage_stocks.branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('damage_stocks.damage_number', 'like', "%{$search}%")->orWhere('items.name', 'like', "%{$search}%"))
                    ->selectRaw("damage_stocks.damage_number, damage_stocks.entry_date, branches.name as branch_name, items.item_code, items.name as item_name, damage_stock_items.qty, damage_stock_items.cost_price, (damage_stock_items.qty * damage_stock_items.cost_price) as loss_amount, damage_stocks.remarks, damage_stocks.status")
                    ->orderBy('damage_stocks.entry_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->damage_number) . '</strong>',
                        $item->entry_date ? date('d M Y', strtotime($item->entry_date)) : '-',
                        e($item->branch_name ?: '-'),
                        '<code>' . e($item->item_code) . '</code>',
                        e($item->item_name),
                        number_format($item->qty),
                        '₹ ' . number_format($item->cost_price, 2),
                        '<strong class="text-danger">₹ ' . number_format($item->loss_amount, 2) . '</strong>',
                        e($item->remarks ?: 'Damaged / Expired'),
                        '<span class="badge badge-secondary">' . e($item->status ?: 'Processed') . '</span>'
                    ]
                ]);
                $totalLoss = DamageStock::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('total_cost');
                return [
                    'title' => 'Wastage / Damage Stock Detail Report',
                    'subtitle' => 'Damaged, expired, leakage or broken product write-offs with total cost valuation loss',
                    'columns' => ['#', 'Damage Entry No', 'Date', 'Branch', 'Item Code', 'Item Description', 'Damaged Qty', 'Unit Cost', 'Loss Value (₹)', 'Reason / Remarks', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-left', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Write-off Loss', 'value' => '₹ ' . number_format($totalLoss ?: 0, 2), 'icon' => 'fas fa-dumpster-fire', 'color' => 'danger'],
                        ['label' => 'Damage Entries', 'value' => DamageStock::count(), 'icon' => 'fas fa-exclamation-triangle', 'color' => 'warning'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'opening-stock-detail':
                $query = DB::table('opening_stock_items')
                    ->join('opening_stocks', 'opening_stock_items.opening_stock_id', '=', 'opening_stocks.id')
                    ->join('items', 'opening_stock_items.item_id', '=', 'items.id')
                    ->leftJoin('branches', 'opening_stocks.branch_id', '=', 'branches.id')
                    ->when($branchId, fn ($q) => $q->where('opening_stocks.branch_id', $branchId))
                    ->when($search, fn ($q) => $q->where('opening_stocks.entry_number', 'like', "%{$search}%")->orWhere('items.name', 'like', "%{$search}%"))
                    ->selectRaw("opening_stocks.entry_number, opening_stocks.entry_date, branches.name as branch_name, items.item_code, items.name as item_name, opening_stock_items.qty, opening_stock_items.cost_price, opening_stock_items.sell_price, (opening_stock_items.qty * opening_stock_items.cost_price) as total_val")
                    ->orderBy('opening_stocks.entry_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->entry_number) . '</strong>',
                        $item->entry_date ? date('d M Y', strtotime($item->entry_date)) : '-',
                        e($item->branch_name ?: '-'),
                        '<code>' . e($item->item_code) . '</code>',
                        e($item->item_name),
                        number_format($item->qty),
                        '₹ ' . number_format($item->cost_price, 2),
                        '₹ ' . number_format($item->sell_price, 2),
                        '<strong>₹ ' . number_format($item->total_val, 2) . '</strong>'
                    ]
                ]);
                return [
                    'title' => 'Opening Stock Detail Report',
                    'subtitle' => 'Initial year-begin inventory balances and baseline cost entries',
                    'columns' => ['#', 'Opening Entry No', 'Date', 'Branch', 'Item Code', 'Item Description', 'Opening Qty', 'Cost Rate', 'Selling Rate', 'Total Valuation'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Opening Stock Records', 'value' => $paginator->total(), 'icon' => 'fas fa-door-open', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            case 'stock-transferin-summary':
            case 'stock-transferout-detail':
            case 'stock-transferin-detail':
                $query = StockTransfer::with(['fromBranch', 'toBranch'])
                    ->when($search, fn ($q) => $q->where('transfer_number', 'like', "%{$search}%"))
                    ->orderBy('transfer_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->transfer_number) . '</strong>',
                        $item->transfer_date ? date('d M Y', strtotime($item->transfer_date)) : '-',
                        e($item->fromBranch?->name ?: '-'),
                        e($item->toBranch?->name ?: '-'),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->total_value, 2),
                        '<span class="badge badge-primary">' . e($item->status ?: 'Transferred') . '</span>',
                        e($item->remarks ?: '-')
                    ]
                ]);
                return [
                    'title' => 'Inter-Branch Stock Movement Report',
                    'subtitle' => 'Transfer-Out and Transfer-In transit movements between Central Warehouse and Branch Stores',
                    'columns' => ['#', 'Transfer Number', 'Date', 'Origin Store', 'Destination Store', 'Total Qty', 'Total Valuation', 'Status', 'Remarks'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-center', 'text-left'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Transfers', 'value' => StockTransfer::count(), 'icon' => 'fas fa-dolly', 'color' => 'info'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            case 'categorywise-storewise-current-stock':
                $query = ItemStock::join('items', 'item_stocks.item_id', '=', 'items.id')
                    ->leftJoin('item_category_values', 'items.category_value_id', '=', 'item_category_values.id')
                    ->leftJoin('branches', 'item_stocks.branch_id', '=', 'branches.id')
                    ->where('item_stocks.quantity', '>', 0)
                    ->when($branchId, fn ($q) => $q->where('item_stocks.branch_id', $branchId))
                    ->groupBy('branches.name', 'item_category_values.name')
                    ->selectRaw("branches.name as branch_name, COALESCE(item_category_values.name, 'General Goods') as category_name, COUNT(item_stocks.id) as sku_count, SUM(item_stocks.quantity) as total_qty, SUM(item_stocks.quantity * items.cost_price) as cost_val, SUM(item_stocks.quantity * items.sell_price) as sell_val")
                    ->orderBy('branches.name')
                    ->orderBy('cost_val', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->branch_name) . '</strong>',
                        e($item->category_name),
                        number_format($item->sku_count),
                        number_format($item->total_qty),
                        '₹ ' . number_format($item->cost_val, 2),
                        '<strong>₹ ' . number_format($item->sell_val, 2) . '</strong>'
                    ]
                ]);
                return [
                    'title' => 'Category-wise Store-wise Current Stock Summary',
                    'subtitle' => 'Inventory concentration and retail valuation aggregated by department category and store',
                    'columns' => ['#', 'Store Location', 'Category Group', 'SKUs Count', 'Total Stock Qty', 'Cost Valuation', 'Retail Sales Valuation'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Categories Analyzed', 'value' => $paginator->total(), 'icon' => 'fas fa-layer-group', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];

            default:
                // General fallback for remaining inventory reports
                $query = ItemStock::with(['item.brand', 'branch'])
                    ->where('quantity', '>', 0)
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('quantity', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<code>' . e($item->item?->item_code ?: $item->item_id) . '</code>',
                        e($item->item?->name ?: '-'),
                        e($item->branch?->name ?: '-'),
                        number_format($item->quantity),
                        '₹ ' . number_format($item->sell_price ?: $item->item?->sell_price, 2),
                        '<span class="badge badge-success">In Stock</span>'
                    ]
                ]);
                return [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)) . ' Report',
                    'subtitle' => 'Stock levels, movement trails, and warehouse replenishment alerts',
                    'columns' => ['#', 'Item Code', 'Item Description', 'Branch', 'Quantity', 'Rate', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-left', 'text-left', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Active SKUs', 'value' => $paginator->total(), 'icon' => 'fas fa-boxes', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => true,
                ];
        }
    }

    /* ----------------------------------------------------------------------
     * 6. AUXILIARY / MY REPORTS / PRODUCTION / MORE HANDLER
     * ---------------------------------------------------------------------- */
    private function handleAuxReport(string $slug, string $search, ?string $branchId, string $from, string $to): array
    {
        switch ($slug) {
            case 'monthly-transaction-summary':
                // Monthly aggregate overview
                $query = SalesBill::selectRaw("DATE_FORMAT(bill_date, '%Y-%m') as trans_month, COUNT(*) as sales_count, SUM(total) as sales_amount")
                    ->groupBy('trans_month')
                    ->orderBy('trans_month', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . date('F Y', strtotime($item->trans_month . '-01')) . '</strong>',
                        number_format($item->sales_count),
                        '₹ ' . number_format($item->sales_amount, 2),
                        '₹ 0.00',
                        '₹ ' . number_format($item->sales_amount, 2),
                        '<span class="badge badge-success">Balanced</span>'
                    ]
                ]);
                return [
                    'title' => 'Monthly Transaction Summary Report',
                    'subtitle' => 'Comprehensive monthly operational transaction volume and financial aggregates',
                    'columns' => ['#', 'Period / Month', 'Total Sales Bills', 'Sales Turnover', 'Purchase Turnover', 'Net Financial Movement', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-right', 'text-right', 'text-right', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Total Sales Revenue', 'value' => '₹ ' . number_format(SalesBill::sum('total'), 2), 'icon' => 'fas fa-chart-line', 'color' => 'success'],
                    ],
                    'hasDateFilter' => false,
                    'hasBranchFilter' => false,
                ];

            default:
                // Universal fallback for miscellaneous specialized reports
                $query = SalesBill::with(['customer', 'branch'])
                    ->whereBetween('bill_date', [$from, $to])
                    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                    ->orderBy('bill_date', 'desc');
                $paginator = $query->paginate(50)->withQueryString();
                $rows = $paginator->through(fn ($item) => [
                    'cells' => [
                        '<strong>' . e($item->bill_number) . '</strong>',
                        date('d M Y', strtotime($item->bill_date)),
                        e($item->customer?->name ?: 'Customer'),
                        e($item->branch?->name ?: '-'),
                        '₹ ' . number_format($item->total, 2),
                        '<span class="badge badge-success">' . e($item->status ?: 'Active') . '</span>'
                    ]
                ]);
                return [
                    'title' => ucwords(str_replace(['-', '_'], ' ', $slug)) . ' Report',
                    'subtitle' => 'UrbanPOS SmartReport analytical data feed',
                    'columns' => ['#', 'Ref Code', 'Date', 'Party', 'Branch', 'Amount', 'Status'],
                    'column_alignments' => ['text-center', 'text-left', 'text-center', 'text-left', 'text-left', 'text-right', 'text-center'],
                    'rows' => $rows,
                    'kpis' => [
                        ['label' => 'Records in Period', 'value' => $paginator->total(), 'icon' => 'fas fa-table', 'color' => 'primary'],
                    ],
                    'hasDateFilter' => true,
                    'hasBranchFilter' => true,
                ];
        }
    }
}
