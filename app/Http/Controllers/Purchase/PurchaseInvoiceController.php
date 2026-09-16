<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceiptNote;
use App\Models\Supplier;
use App\Services\Accounting\CreditLimitGuard;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private LedgerPostingService $ledgerPosting,
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
        private CreditLimitGuard $creditLimitGuard,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = PurchaseInvoice::with(['supplier', 'branch', 'purchaseOrder'])->latest('invoice_date');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('invoice_number', 'like', "%{$term}%")
                    ->orWhere('supplier_inv_no', 'like', "%{$term}%")
                    ->orWhere('grn_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', function ($sq) use ($term) {
                        $sq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('invoice_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('invoice_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        if ($request->filled('purchase_type')) {
            $query->where('purchase_type', $request->input('purchase_type'));
        }

        $purchaseInvoices = $query->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::orderBy('name')->pluck('name', 'id');
        $purchaseTypes = ['Local', 'Interstate'];

        return view('purchase.purchase-invoices.index', compact('purchaseInvoices', 'branches', 'suppliers', 'purchaseTypes'));
    }

    public function create(Request $request)
    {
        $sourceReceiptNote = null;
        $convertedItems = collect();

        if ($request->filled('from_receipt_note')) {
            $sourceReceiptNote = PurchaseReceiptNote::with(['items.item.gstTax', 'supplier', 'branch', 'purchaseOrder'])
                ->findOrFail($request->from_receipt_note);
            $convertedItems = $sourceReceiptNote->items->map(function ($rnItem) {
                return [
                    'item_id' => $rnItem->item_id,
                    'exp_date' => $rnItem->exp_date ? $rnItem->exp_date->format('Y-m-d') : null,
                    'qty' => (float) $rnItem->accepted_qty,
                    'free_qty' => 0,
                    'cost_price' => (float) $rnItem->unit_cost,
                    'sell_price' => (float) ($rnItem->item->sell_price ?? 0),
                    'mrp' => (float) ($rnItem->mrp ?? $rnItem->item->mrp ?? 0),
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => (float) ($rnItem->item->gstTax?->percentage ?? 0),
                    'item' => $rnItem->item,
                ];
            })->filter(fn ($line) => $line['qty'] > 0)->values();
        }

        $options = $this->formOptions(null, $convertedItems);
        if ($sourceReceiptNote) {
            $options['sourceReceiptNote'] = $sourceReceiptNote;
            $options['convertedItems'] = $convertedItems;
        }

        return view('purchase.purchase-invoices.create', array_merge($options, [
            'sourceReceiptNote' => $sourceReceiptNote,
            'convertedItems' => $convertedItems,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['invoice_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = PurchaseInvoice::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('purchase.purchase-invoices.index')->with('status', "Purchase Invoice {$existing->invoice_number} created successfully.");
            }
        }

        $purchaseInvoice = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $this->assertSupplierInvAmountMatchesTotal($data['header'], $totals);
            $this->assertCreditLimit((int) $data['header']['supplier_id'], $totals['total']);

            $purchaseInvoice = PurchaseInvoice::create(array_merge($data['header'], $totals, [
                'invoice_number' => $this->nextNumber(),
            ]));

            $purchaseInvoice->items()->createMany($lines);

            // Stock posting: if converted from a PurchaseReceiptNote, physical stock
            // was already posted by the GRN. We only update item master prices
            // without duplicating stock in stock_ledger.
            if (!empty($data['header']['purchase_receipt_note_id'])) {
                $rn = PurchaseReceiptNote::find($data['header']['purchase_receipt_note_id']);
                if ($rn) {
                    $rn->update([
                        'status' => 'Invoiced',
                        'purchase_invoice_id' => $purchaseInvoice->id,
                    ]);
                }
                foreach ($lines as $line) {
                    Item::whereKey($line['item_id'])->update(array_filter([
                        'cost_price' => $line['cost_price'],
                        'sell_price' => $line['sell_price'] ?: null,
                        'mrp' => $line['mrp'] ?: null,
                    ], fn ($v) => $v !== null));
                }
            } else {
                $this->postStockAndItemMaster($purchaseInvoice, $lines);
            }

            $this->ledgerPosting->postPurchaseInvoice($purchaseInvoice);

            return $purchaseInvoice;
        });

        return redirect()->route('purchase.purchase-invoices.index')->with('status', "Purchase Invoice {$purchaseInvoice->invoice_number} created successfully.");
    }

    public function edit(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load('items');

        return view('purchase.purchase-invoices.edit', array_merge(['purchaseInvoice' => $purchaseInvoice], $this->formOptions($purchaseInvoice)));
    }

    public function update(Request $request, PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['invoice_date']);
        $oldSupplierId = $purchaseInvoice->supplier_id;
        $oldTotal = (float) $purchaseInvoice->total;

        DB::transaction(function () use ($data, $purchaseInvoice, $oldSupplierId, $oldTotal) {
            // Reverse the previous version's stock/ledger effect instead of deleting it.
            $this->stockLedger->reverseByReference(PurchaseInvoice::class, $purchaseInvoice->id);

            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $this->assertSupplierInvAmountMatchesTotal($data['header'], $totals);
            $this->assertCreditLimit((int) $data['header']['supplier_id'], $totals['total'], $oldSupplierId, $oldTotal);

            $purchaseInvoice->update(array_merge($data['header'], $totals));
            $purchaseInvoice->items()->delete();
            $purchaseInvoice->items()->createMany($lines);

            $this->postStockAndItemMaster($purchaseInvoice, $lines);
            $this->ledgerPosting->postPurchaseInvoice($purchaseInvoice);
        });

        return redirect()->route('purchase.purchase-invoices.index')->with('status', "Purchase Invoice {$purchaseInvoice->invoice_number} updated successfully.");
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        // No assertEditable() guard here: destroy() already IS the cancel/reversal action.
        $oldValues = $purchaseInvoice->only(['invoice_number', 'invoice_date', 'supplier_id', 'branch_id', 'total', 'status']);

        DB::transaction(function () use ($purchaseInvoice, $oldValues) {
            $this->stockLedger->reverseByReference(PurchaseInvoice::class, $purchaseInvoice->id);
            $this->ledgerPosting->reverse(PurchaseInvoice::class, $purchaseInvoice->id);
            $this->auditLogger->log('cancel', $purchaseInvoice, $oldValues, null);
            $purchaseInvoice->delete();
        });

        return redirect()->route('purchase.purchase-invoices.index')->with('status', 'Purchase Invoice deleted and stock reversed.');
    }

    /**
     * Posts the PURCHASE_RECEIPT movement for every line at its own cost (the moving
     * weighted average is recomputed inside StockLedgerService::post()), then snapshots
     * the item master's price fields forward — that snapshot is intentionally never
     * reversed on edit/delete, only ever overwritten by a newer purchase.
     */
    private function postStockAndItemMaster(PurchaseInvoice $purchaseInvoice, array $lines): void
    {
        foreach ($lines as $line) {
            $qtyIn = (float) $line['qty'] + (float) $line['free_qty'];

            if ($qtyIn > 0) {
                $this->stockLedger->post(
                    itemId: $line['item_id'],
                    branchId: $purchaseInvoice->branch_id,
                    movementType: 'PURCHASE_RECEIPT',
                    qtyDelta: $qtyIn,
                    unitCost: $line['cost_price'],
                    referenceType: PurchaseInvoice::class,
                    referenceId: $purchaseInvoice->id,
                    documentDate: $purchaseInvoice->invoice_date->toDateString(),
                    expDate: $line['exp_date'],
                );
            }

            Item::whereKey($line['item_id'])->update(array_filter([
                'cost_price' => $line['cost_price'],
                'sell_price' => $line['sell_price'] ?: null,
                'mrp' => $line['mrp'] ?: null,
            ], fn ($v) => $v !== null));
        }
    }

    /**
     * Checks the credit-limit delta this save would introduce against the supplier, not
     * the raw new total — mirrors SalesBillController::assertCreditLimit(). On an edit,
     * the old total is already reflected in the supplier's live ledger balance, so only
     * the CHANGE matters; if the supplier was switched, the old supplier's contribution
     * is removed (a negative delta, never blocking) and the new supplier is checked
     * against the full new total.
     */
    private function assertCreditLimit(int $newSupplierId, float $newTotal, ?int $oldSupplierId = null, float $oldTotal = 0.0): void
    {
        if ($oldSupplierId !== null && $oldSupplierId === $newSupplierId) {
            $this->creditLimitGuard->assertWithinLimit(Supplier::findOrFail($newSupplierId), $newTotal - $oldTotal);

            return;
        }

        if ($oldSupplierId !== null) {
            $this->creditLimitGuard->assertWithinLimit(Supplier::findOrFail($oldSupplierId), -$oldTotal);
        }

        $this->creditLimitGuard->assertWithinLimit(Supplier::findOrFail($newSupplierId), $newTotal);
    }

    private function assertSupplierInvAmountMatchesTotal(array $header, array $totals): void
    {
        if (empty($header['supplier_inv_amount'])) {
            return;
        }

        $supplierInvAmt = round((float) $header['supplier_inv_amount'], 2);
        $finalAmount = round((float) ($totals['total'] ?? 0), 2);

        if ($supplierInvAmt > 0 && abs($supplierInvAmt - $finalAmount) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'supplier_inv_amount' => "Inv Amount (Supplier) [₹" . number_format($supplierInvAmt, 2) . "] and Final Amount [₹" . number_format($finalAmount, 2) . "] same ho to hi save hoga (Difference: ₹" . number_format($supplierInvAmt - $finalAmount, 2) . ").",
            ]);
        }
    }

    private function nextNumber(): string
    {
        $next = (PurchaseInvoice::max('id') ?? 0) + 1;

        return 'PINV'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function itemList(Request $request)
    {
        $branchId = (int) ($request->input('branch_id') ?: session('active_branch_id', auth()->user()?->branch_id ?? 3));
        $search   = trim((string) $request->input('search', ''));
        $expiry   = trim((string) $request->input('expiry', ''));
        $code     = trim((string) $request->input('code', ''));

        // Require at least 1 character to avoid loading 8000+ items on initial empty state
        $hasFilter = $search !== '' || $code !== '' || $expiry !== '';
        if (! $hasFilter) {
            return response()->json(['items' => [], 'hint' => 'Type to search items…']);
        }

        // --- Single optimised query: items LEFT JOINed with stock & earliest expiry ---
        $limit  = 100;
        $where  = ['i.status = 1'];
        $params = [$branchId, $branchId];

        $orderSql    = 'i.name ASC';
        $orderParams = [];

        if ($search !== '') {
            $sWild   = "%{$search}%";
            $sExact  = $search;
            $sPrefix = "{$search}%";

            // Barcode (ean_upc_code) only matches exact or prefix — never substring in middle of 13-digit barcode!
            // Item code matches exact or prefix
            // Item name matches substring
            $where[] = '(i.name LIKE ? OR i.item_code = ? OR i.item_code LIKE ? OR i.ean_upc_code = ? OR i.ean_upc_code LIKE ?)';
            $params  = array_merge($params, [$sWild, $sExact, $sPrefix, $sExact, $sPrefix]);

            // Rank exact code / barcode match first, then prefix, then name
            $orderSql = "
                CASE
                    WHEN i.item_code = ? THEN 1
                    WHEN i.ean_upc_code = ? THEN 2
                    WHEN i.item_code LIKE ? THEN 3
                    WHEN i.ean_upc_code LIKE ? THEN 4
                    WHEN i.name LIKE ? THEN 5
                    ELSE 6
                END ASC,
                i.name ASC
            ";
            $orderParams = [$sExact, $sExact, $sPrefix, $sPrefix, $sPrefix];
        }

        if ($code !== '') {
            $cExact  = $code;
            $cPrefix = "{$code}%";
            $where[] = '(i.item_code = ? OR i.item_code LIKE ? OR i.ean_upc_code = ? OR i.ean_upc_code LIKE ?)';
            $params  = array_merge($params, [$cExact, $cPrefix, $cExact, $cPrefix]);

            if ($search === '') {
                $orderSql = "
                    CASE
                        WHEN i.item_code = ? THEN 1
                        WHEN i.ean_upc_code = ? THEN 2
                        WHEN i.item_code LIKE ? THEN 3
                        WHEN i.ean_upc_code LIKE ? THEN 4
                        ELSE 5
                    END ASC,
                    i.name ASC
                ";
                $orderParams = [$cExact, $cExact, $cPrefix, $cPrefix];
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                i.id,
                i.name,
                COALESCE(i.item_code, '')  AS item_code,
                COALESCE(i.ean_upc_code, '') AS ean_upc_code,
                COALESCE(st.quantity, 0)   AS qty,
                COALESCE(
                    NULLIF(ei.cost_price, 0),
                    NULLIF(i.cost_price, 0),
                    0
                )                          AS cost_price,
                COALESCE(
                    NULLIF(ei.sell_price, 0),
                    NULLIF(i.sell_price, 0),
                    0
                )                          AS sell_price,
                COALESCE(
                    NULLIF(ei.mrp, 0),
                    NULLIF(i.mrp, 0),
                    0
                )                          AS mrp,
                ei.exp_date,
                COALESCE(i.batch_expiry_details, 'Not Required') AS batch_expiry_details,
                i.shelf_life_days,
                i.minimum_shelf_life_days,
                COALESCE(gt.percentage, 0) AS gst_percent
            FROM items i
            LEFT JOIN item_stocks st
                   ON st.item_id = i.id AND st.branch_id = ?
            LEFT JOIN (
                SELECT pi2.item_id,
                       MIN(pi2.exp_date) AS exp_date,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.cost_price ORDER BY pih.invoice_date DESC, pi2.id DESC SEPARATOR ','), ',', 1) AS cost_price,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.sell_price ORDER BY pih.invoice_date DESC, pi2.id DESC SEPARATOR ','), ',', 1) AS sell_price,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.mrp        ORDER BY pih.invoice_date DESC, pi2.id DESC SEPARATOR ','), ',', 1) AS mrp
                FROM purchase_invoice_items pi2
                INNER JOIN purchase_invoices pih
                        ON pih.id = pi2.purchase_invoice_id AND pih.branch_id = ?
                WHERE pi2.exp_date IS NOT NULL
                  AND CAST(pi2.exp_date AS CHAR) NOT IN ('', '0000-00-00')
                GROUP BY pi2.item_id
            ) ei ON ei.item_id = i.id
            LEFT JOIN gst_taxes gt ON gt.id = i.gst_tax_id
            {$whereClause}
            ORDER BY {$orderSql}
            LIMIT {$limit}
        ";

        $finalParams = array_merge($params, $orderParams);
        $rows = DB::select($sql, $finalParams);

        // Cross-branch fallback for exp_date/pricing if not found in branch
        $noExpIds = collect($rows)->filter(fn ($r) => empty($r->exp_date))->pluck('id')->all();
        $expiryFallback = [];
        if (! empty($noExpIds)) {
            $ph = implode(',', array_fill(0, count($noExpIds), '?'));
            $fbSql = "
                SELECT pi2.item_id,
                       MIN(pi2.exp_date) AS exp_date,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.cost_price ORDER BY pi2.id DESC SEPARATOR ','), ',', 1) AS cost_price,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.sell_price ORDER BY pi2.id DESC SEPARATOR ','), ',', 1) AS sell_price,
                       SUBSTRING_INDEX(GROUP_CONCAT(pi2.mrp        ORDER BY pi2.id DESC SEPARATOR ','), ',', 1) AS mrp
                FROM purchase_invoice_items pi2
                WHERE pi2.item_id IN ({$ph})
                  AND pi2.exp_date IS NOT NULL
                  AND CAST(pi2.exp_date AS CHAR) NOT IN ('', '0000-00-00')
                GROUP BY pi2.item_id
            ";
            foreach (DB::select($fbSql, $noExpIds) as $fb) {
                $expiryFallback[$fb->item_id] = $fb;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $expRaw = $row->exp_date ?? null;
            if (empty($expRaw) && isset($expiryFallback[$row->id])) {
                $fb     = $expiryFallback[$row->id];
                $expRaw = $fb->exp_date;
                if ((float) ($fb->cost_price ?? 0) > 0 && (float) $row->cost_price <= 0) $row->cost_price = $fb->cost_price;
                if ((float) ($fb->sell_price ?? 0) > 0 && (float) $row->sell_price <= 0) $row->sell_price = $fb->sell_price;
                if ((float) ($fb->mrp ?? 0) > 0        && (float) $row->mrp <= 0)        $row->mrp        = $fb->mrp;
            }

            $exp = null;
            if (! empty($expRaw) && $expRaw !== '0000-00-00') {
                try {
                    $exp = \Carbon\Carbon::parse($expRaw)->format('Y-m-d');
                } catch (\Exception $e) {
                    $exp = substr((string) $expRaw, 0, 10) ?: null;
                }
            }

            if ($expiry !== '' && (! $exp || strpos($exp, $expiry) === false)) continue;

            $result[] = [
                'id'                      => (int) $row->id,
                'name'                    => $row->name,
                'code'                    => $row->item_code ?: ($row->ean_upc_code ?: ''),
                'qty'                     => (float) $row->qty,
                'cost_price'              => (float) $row->cost_price,
                'sell_price'              => (float) $row->sell_price,
                'mrp'                     => (float) $row->mrp,
                'exp_date'                => $exp,
                'batch_expiry_details'    => $row->batch_expiry_details ?? 'Not Required',
                'shelf_life_days'         => $row->shelf_life_days ? (int) $row->shelf_life_days : null,
                'minimum_shelf_life_days' => $row->minimum_shelf_life_days ? (int) $row->minimum_shelf_life_days : null,
                'gst_percent'             => (float) $row->gst_percent,
            ];
        }

        return response()->json(['items' => $result]);
    }

    public function lookupItem(Request $request)
    {
        $itemId = $request->input('item_id');
        $query  = trim((string) $request->input('query', ''));
        $branchId = (int) ($request->input('branch_id') ?: session('active_branch_id', auth()->user()?->branch_id ?? 3));

        $item = null;
        if (! empty($itemId)) {
            $item = Item::where('status', true)->with('gstTax:id,percentage')->find($itemId);
        }

        if (! $item && $query !== '') {
            $item = Item::where('status', true)->with('gstTax:id,percentage')
                ->where(function ($q) use ($query) {
                    $q->where('item_code', $query)
                        ->orWhere('ean_upc_code', $query)
                        ->orWhere('name', 'like', "%{$query}%");
                })
                ->first();

            if (! $item && is_numeric($query)) {
                $item = Item::where('status', true)->with('gstTax:id,percentage')->find($query);
            }
        }

        if (! $item) {
            return response()->json(null);
        }

        $stock = (float) (ItemStock::where('item_id', $item->id)->where('branch_id', $branchId)->value('quantity') ?? 0);

        // Check if there is recent purchase invoice exp_date
        $lastExp = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->where('pii.item_id', $item->id)
            ->whereNotNull('pii.exp_date')
            ->whereRaw("CAST(pii.exp_date AS CHAR) NOT IN ('', '0000-00-00')")
            ->orderBy('pi.invoice_date', 'desc')
            ->value('pii.exp_date');

        return response()->json([
            'id'                      => $item->id,
            'name'                    => $item->name,
            'item_code'               => $item->item_code,
            'ean_upc_code'            => $item->ean_upc_code,
            'cost_price'              => (float) ($item->cost_price ?? 0),
            'sell_price'              => (float) ($item->sell_price ?? 0),
            'mrp'                     => (float) ($item->mrp ?? 0),
            'stock'                   => $stock,
            'exp_date'                => $lastExp ? \Carbon\Carbon::parse($lastExp)->format('Y-m-d') : null,
            'gst_percent'             => (float) ($item->gstTax?->percentage ?? 0),
            'batch_expiry_details'    => $item->batch_expiry_details ?? 'Not Required',
            'shelf_life_days'         => $item->shelf_life_days ? (int) $item->shelf_life_days : null,
            'minimum_shelf_life_days' => $item->minimum_shelf_life_days ? (int) $item->minimum_shelf_life_days : null,
        ]);
    }

    public function itemDetails(Item $item)
    {
        $item->loadMissing('gstTax:id,percentage');

        return response()->json([
            'id' => $item->id,
            'name' => $item->name,
            'item_code' => $item->item_code,
            'ean_upc_code' => $item->ean_upc_code,
            'cost_price' => (float) ($item->cost_price ?? 0),
            'sell_price' => (float) ($item->sell_price ?? 0),
            'mrp' => (float) ($item->mrp ?? 0),
            'gst_percent' => (float) ($item->gstTax?->percentage ?? 0),
            'batch_expiry_details' => $item->batch_expiry_details ?? 'Not Required',
            'shelf_life_days' => $item->shelf_life_days ? (int) $item->shelf_life_days : null,
            'minimum_shelf_life_days' => $item->minimum_shelf_life_days ? (int) $item->minimum_shelf_life_days : null,
        ]);
    }

    private function formOptions(?PurchaseInvoice $purchaseInvoice = null, $convertedItems = null): array
    {
        $existingItemIds = collect($purchaseInvoice?->items ?? ($convertedItems ?? []))->pluck('item_id')->filter()->unique();
        $items = $existingItemIds->isNotEmpty()
            ? Item::whereIn('id', $existingItemIds)->with('gstTax:id,percentage')->get([
                'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id',
                'batch_expiry_details', 'shelf_life_days', 'minimum_shelf_life_days'
            ])
            : collect();

        return [
            'suppliers' => Supplier::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items' => $items,
            'purchaseOrders' => PurchaseOrder::orderBy('po_number')->pluck('po_number', 'id'),
        ];
    }

    private function computeLines(array $items, array $header): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');
        $isInterstate = ($header['purchase_type'] ?? null) === 'Interstate';

        $totalHeaderDiscount = (float) ($header['scheme_item_disc_amt'] ?? 0) + (float) ($header['other_disc_amt'] ?? 0);

        // Pre-calculate line base after item-level discount
        $lineBases = [];
        $totalBaseAfterItemDisc = 0.0;

        foreach ($items as $idx => $line) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $base = $qty * $costPrice;

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);

            // Synchronize discount percentage and amount
            if ($discAmount <= 0 && $discPercent > 0 && $base > 0) {
                $discAmount = round($base * $discPercent / 100, 2);
            } elseif ($discAmount > 0 && $discPercent <= 0 && $base > 0) {
                $discPercent = round(($discAmount / $base) * 100, 2);
            }

            $baseAfterDisc = max(0, $base - $discAmount);
            $lineBases[$idx] = [
                'base' => $base,
                'disc_percent' => $discPercent,
                'disc_amount' => $discAmount,
                'base_after_disc' => $baseAfterDisc,
            ];
            $totalBaseAfterItemDisc += $baseAfterDisc;
        }

        // Allocate header discount (Scheme ItemDiscAmt + OtherDiscAmt) on basic cost without GST
        $allocatedExtraDeductions = [];
        $remainingDiscount = $totalHeaderDiscount;
        $count = count($items);
        $i = 0;

        foreach ($items as $idx => $line) {
            $i++;
            $baseAfterDisc = $lineBases[$idx]['base_after_disc'];
            if ($totalBaseAfterItemDisc > 0 && $totalHeaderDiscount > 0) {
                if ($i === $count) {
                    $extra = round($remainingDiscount, 2);
                } else {
                    $extra = round(($baseAfterDisc / $totalBaseAfterItemDisc) * $totalHeaderDiscount, 2);
                    $remainingDiscount -= $extra;
                }
            } else {
                $extra = 0.0;
            }
            $allocatedExtraDeductions[$idx] = max(0, $extra);
        }

        return collect($items)->map(function ($line, $idx) use ($itemsById, $isInterstate, $lineBases, $allocatedExtraDeductions) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $item = $itemsById[$line['item_id']];
            $baseInfo = $lineBases[$idx];
            $extraDeduction = $allocatedExtraDeductions[$idx] ?? 0.0;

            $tax = $this->taxEngine->calculate(
                $qty,
                $costPrice,
                $item,
                $baseInfo['disc_percent'],
                $baseInfo['disc_amount'],
                $extraDeduction,
                $isInterstate,
                isTaxInclusive: false
            );

            $effectiveDiscPercent = $tax['disc_amount'] > 0 && $baseInfo['base'] > 0
                ? round(($tax['disc_amount'] / $baseInfo['base']) * 100, 2)
                : $baseInfo['disc_percent'];

            return [
                'item_id' => $line['item_id'],
                'exp_date' => $this->normalizeDate($line['exp_date'] ?? null),
                'qty' => $qty,
                'free_qty' => (float) ($line['free_qty'] ?? 0),
                'cost_price' => $costPrice,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
                'disc_percent' => $effectiveDiscPercent,
                'disc_amount' => $tax['disc_amount'],
                'gst_percent' => $tax['gst_percent'],
                'gst_tax_amount' => $tax['gst_tax_amount'],
                'cgst_amount' => $tax['cgst_amount'],
                'sgst_amount' => $tax['sgst_amount'],
                'igst_amount' => $tax['igst_amount'],
                'net_amount' => $tax['net_amount'],
            ];
        })->all();
    }

    private function computeTotals(array $lines, array $data): array
    {
        $collection = collect($lines);
        $freight = (float) ($data['header']['freight'] ?? 0);
        $roundOff = (float) ($data['header']['round_off'] ?? 0);
        $tcsAmount = (float) ($data['header']['tcs_amount'] ?? 0);

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total_cgst' => $collection->sum('cgst_amount'),
            'total_sgst' => $collection->sum('sgst_amount'),
            'total_igst' => $collection->sum('igst_amount'),
            'total_qty' => $collection->sum('qty') + $collection->sum('free_qty'),
            'total' => round($collection->sum('net_amount') + $freight + $roundOff + $tcsAmount, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'invoice_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'purchase_receipt_note_id' => ['nullable', 'exists:purchase_receipt_notes,id'],
            'grn_number' => ['nullable', 'string', 'max:100'],
            'grn_date' => ['nullable', 'date'],
            'supplier_inv_no' => ['nullable', 'string', 'max:100'],
            'supplier_inv_date' => ['nullable', 'date'],
            'supplier_inv_amount' => ['nullable', 'numeric', 'min:0'],
            'purchase_type' => ['required', 'in:Local,Interstate'],
            'c_form' => ['required', 'in:Against C-Form,No Forms'],
            'freight' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric'],
            'scheme_item_disc_amt' => ['nullable', 'numeric', 'min:0'],
            'other_disc_amt' => ['nullable', 'numeric', 'min:0'],
            'total_extra_cess' => ['nullable', 'numeric', 'min:0'],
            'tcs_amount' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ]);

        $header['invoice_date'] = $this->normalizeDate($header['invoice_date']);
        $header['grn_date'] = $this->normalizeDate($header['grn_date'] ?? null);
        $header['supplier_inv_date'] = $this->normalizeDate($header['supplier_inv_date'] ?? null);

        $header['freight'] = (float) ($header['freight'] ?? 0);
        $header['round_off'] = (float) ($header['round_off'] ?? 0);
        $header['scheme_item_disc_amt'] = (float) ($header['scheme_item_disc_amt'] ?? 0);
        $header['other_disc_amt'] = (float) ($header['other_disc_amt'] ?? 0);
        $header['total_extra_cess'] = (float) ($header['total_extra_cess'] ?? 0);
        $header['tcs_amount'] = (float) ($header['tcs_amount'] ?? 0);
        $header['total_weight'] = (float) ($header['total_weight'] ?? 0);

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.free_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validator->after(function ($v) use ($request) {
            $rawItems = $request->input('items', []);
            if (! is_array($rawItems)) {
                return;
            }

            $itemIds = collect($rawItems)->pluck('item_id')->filter()->unique();
            $itemsMap = Item::whereIn('id', $itemIds)->get()->keyBy('id');

            foreach ($rawItems as $idx => $line) {
                $itemId = $line['item_id'] ?? null;
                $itemModel = $itemsMap->get($itemId);
                if (! $itemModel) {
                    continue;
                }

                $batchExpiry = $itemModel->batch_expiry_details ?? 'Not Required';
                if (in_array($batchExpiry, ['Mandatory', 'Days', 'Month'], true)) {
                    if (empty($line['exp_date'])) {
                        $rowNum = $idx + 1;
                        $v->errors()->add(
                            "items.{$idx}.exp_date",
                            "Expiry date is mandatory for item '{$itemModel->name}' (Row #{$rowNum}) because its Batch/Expiry setting is '{$batchExpiry}'."
                        );
                    }
                }

                // Rule: Sell Price must be greater than Cost Price
                $costPrice = (float) ($line['cost_price'] ?? 0);
                $mrp = (float) ($line['mrp'] ?? 0);
                if (isset($line['sell_price']) && $line['sell_price'] !== null && $line['sell_price'] !== '') {
                    $sellPrice = (float) $line['sell_price'];
                    if ($costPrice > 0 && $sellPrice <= $costPrice) {
                        $rowNum = $idx + 1;
                        $v->errors()->add(
                            "items.{$idx}.sell_price",
                            "Item '{$itemModel->name}' (Row #{$rowNum}): Sell price (₹{$sellPrice}) must be greater than cost price (₹{$costPrice})."
                        );
                    }
                    if ($mrp > 0 && $sellPrice > $mrp) {
                        $rowNum = $idx + 1;
                        $v->errors()->add(
                            "items.{$idx}.sell_price",
                            "Item '{$itemModel->name}' (Row #{$rowNum}): Sell price (₹{$sellPrice}) MRP (₹{$mrp}) se zyada nahi hona chahiye (Sell price must be <= MRP)."
                        );
                    }
                }
            }
        });

        $validated = $validator->validate();

        return ['header' => $header, 'items' => $validated['items']];
    }
}
