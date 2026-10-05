<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\HasSorting;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\ItemStock;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends Controller
{
    use HasSorting;
    public function __construct(
        private LedgerPostingService $ledgerPosting,
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = PurchaseReturn::with(['supplier', 'branch', 'purchaseInvoice']);

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('return_number', 'like', "%{$term}%")
                    ->orWhere('supplier_debit_note_no', 'like', "%{$term}%")
                    ->orWhereHas('purchaseInvoice', fn ($iq) => $iq->where('invoice_number', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', function ($sq) use ($term) {
                        $sq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('return_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('return_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $allowedSorts = [
            'return_number' => 'return_number',
            'return_date' => 'return_date',
            'supplier_debit_note_no' => 'supplier_debit_note_no',
            'supplier' => fn ($q, $dir) => $q->orderBy(
                Supplier::select('name')->whereColumn('suppliers.id', 'purchase_returns.supplier_id'),
                $dir
            ),
            'invoice_number' => fn ($q, $dir) => $q->orderBy(
                PurchaseInvoice::select('invoice_number')->whereColumn('purchase_invoices.id', 'purchase_returns.purchase_invoice_id'),
                $dir
            ),
            'total_amount' => 'total_amount',
            'id' => 'id',
        ];
        $this->applySorting($query, $allowedSorts, ['return_date' => 'desc', 'id' => 'desc']);

        $purchaseReturns = $query->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('purchase.purchase-returns.index', compact('purchaseReturns', 'branches', 'suppliers'));
    }

    public function create()
    {
        return view('purchase.purchase-returns.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        // Idempotency FIRST: a retry / double-click / refresh of a submit that already committed must
        // get that document back, not a quantity-validation error caused by its own first submit.
        if ($postingKey = $request->input('posting_key')) {
            if ($existing = PurchaseReturn::where('posting_key', $postingKey)->first()) {
                return redirect()->route('purchase.purchase-returns.index')->with('status', "Purchase Return {$existing->return_number} created successfully.");
            }
        }

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['return_date']);

        $this->assertReturnableEligibility($data['header'], $data['items'], null);
        $this->assertStockAvailable($data['items'], (int) $data['header']['branch_id']);

        try {
            $purchaseReturn = DB::transaction(function () use ($data) {
                // Mutex lock: Per-invoice or Per-supplier. MUST be the first statement of the transaction
                // so consistent reads see every return committed by concurrent transactions.
                if (! empty($data['header']['purchase_invoice_id'])) {
                    \App\Models\PurchaseInvoice::whereKey($data['header']['purchase_invoice_id'])->lockForUpdate()->first();
                } else {
                    \App\Models\Supplier::whereKey($data['header']['supplier_id'])->lockForUpdate()->first();
                }

                // Double-click / retry with the same posting_key queued behind the first submit:
                // return the document that submit created instead of failing.
                if (! empty($data['header']['posting_key'])
                    && ($duplicate = PurchaseReturn::where('posting_key', $data['header']['posting_key'])->first())) {
                    return $duplicate;
                }

                // Authoritative re-check under lock
                $this->assertReturnableEligibility($data['header'], $data['items'], null);
                $this->assertStockAvailable($data['items'], (int) $data['header']['branch_id']);

                $lines = $this->computeLines($data['items'], $data['header']);
                $totals = $this->computeTotals($lines, $data);

                $purchaseReturn = PurchaseReturn::create(array_merge($data['header'], $totals, [
                    'return_number' => $this->nextNumber(),
                ]));

                $createdItems = $purchaseReturn->items()->createMany($lines);
                $this->postStock($createdItems, $purchaseReturn);
                $this->ledgerPosting->postPurchaseReturn($purchaseReturn);

                return $purchaseReturn;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Same posting_key raced past the pre-check (no invoice to lock on): the unique index
            // guarantees only one committed - return that one rather than a 500.
            $existing = ! empty($data['header']['posting_key'])
                ? PurchaseReturn::where('posting_key', $data['header']['posting_key'])->first()
                : null;
            if (! $existing) {
                throw $e;
            }
            $purchaseReturn = $existing;
        }

        return redirect()->route('purchase.purchase-returns.index')
            ->with('status', "Purchase Return {$purchaseReturn->return_number} created successfully.");
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['supplier', 'branch', 'purchaseInvoice', 'items.item']);

        return view('purchase.purchase-returns.show', compact('purchaseReturn'));
    }

    public function edit(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->assertEditable();
        $purchaseReturn->load('items.item');

        return view('purchase.purchase-returns.edit', array_merge(['purchaseReturn' => $purchaseReturn], $this->formOptions($purchaseReturn)));
    }

    public function update(Request $request, PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['return_date']);

        DB::transaction(function () use ($data, $purchaseReturn) {
            if (! empty($data['header']['purchase_invoice_id'])) {
                \App\Models\PurchaseInvoice::whereKey($data['header']['purchase_invoice_id'])->lockForUpdate()->first();
            } else {
                \App\Models\Supplier::whereKey($data['header']['supplier_id'])->lockForUpdate()->first();
            }

            $this->assertReturnableEligibility($data['header'], $data['items'], $purchaseReturn->id);
            $this->assertStockAvailable($data['items'], (int) $data['header']['branch_id']);

            $this->stockLedger->reverseByReference(PurchaseReturn::class, $purchaseReturn->id);

            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $purchaseReturn->update(array_merge($data['header'], $totals));
            $purchaseReturn->items()->delete();
            $createdItems = $purchaseReturn->items()->createMany($lines);

            $this->postStock($createdItems, $purchaseReturn);
            $this->ledgerPosting->postPurchaseReturn($purchaseReturn);
        });

        return redirect()->route('purchase.purchase-returns.index')
            ->with('status', "Purchase Return {$purchaseReturn->return_number} updated successfully.");
    }

    public function destroy(PurchaseReturn $purchaseReturn)
    {
        $oldValues = $purchaseReturn->only(['return_number', 'return_date', 'supplier_id', 'branch_id', 'total', 'status']);

        DB::transaction(function () use ($purchaseReturn, $oldValues) {
            $this->stockLedger->reverseByReference(PurchaseReturn::class, $purchaseReturn->id);
            $this->ledgerPosting->reverse(PurchaseReturn::class, $purchaseReturn->id);
            $this->auditLogger->log('cancel', $purchaseReturn, $oldValues, null);
            $purchaseReturn->delete();
        });

        return redirect()->route('purchase.purchase-returns.index')
            ->with('status', 'Purchase Return cancelled and stock reversed successfully.');
    }

    /**
     * AJAX endpoint: return items from a purchase invoice for quick population.
     */
    public function invoiceItems(PurchaseInvoice $purchaseInvoice, Request $request)
    {
        $ignoreReturnId = $request->integer('ignore_return_id');

        // Sum prior returns for this purchase invoice per item and batch
        $priorReturns = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.purchase_invoice_id', $purchaseInvoice->id)
            ->when($ignoreReturnId, fn ($q) => $q->where('pr.id', '!=', $ignoreReturnId))
            ->groupBy('pri.item_id', 'pri.batch_no')
            ->select('pri.item_id', 'pri.batch_no', DB::raw('SUM(pri.qty) as returned_qty'))
            ->get();

        $alreadyReturnedMap = [];
        foreach ($priorReturns as $pr) {
            $bNo = !empty($pr->batch_no) ? trim($pr->batch_no) : '';
            $alreadyReturnedMap["{$pr->item_id}___{$bNo}"] = (float) $pr->returned_qty;
        }

        $items = $purchaseInvoice->items()->with('item')->get()->map(function ($line) use ($alreadyReturnedMap) {
            $originalQty = (float) $line->qty;
            $bNo = !empty($line->batch_no) ? trim($line->batch_no) : '';
            $alreadyReturned = (float) ($alreadyReturnedMap["{$line->item_id}___{$bNo}"] ?? 0);
            $remainingQty = max(0, round($originalQty - $alreadyReturned, 4));

            return [
                'item_id' => $line->item_id,
                'batch_no' => $line->batch_no ?? '',
                'item_name' => $line->item?->name ?? 'Unknown',
                'item_code' => $line->item?->item_code ?? $line->item?->ean_upc_code ?? '',
                'exp_date' => $line->exp_date ? $line->exp_date->format('Y-m-d') : null,
                'original_qty' => $originalQty,
                'already_returned' => $alreadyReturned,
                'remaining_qty' => $remainingQty,
                'qty' => $remainingQty,
                'cost_price' => (float) $line->cost_price,
                'disc_percent' => (float) $line->disc_percent,
                'disc_amount' => (float) $line->disc_amount,
                'gst_percent' => (float) $line->gst_percent,
                'net_amount' => (float) $line->net_amount,
            ];
        });

        return response()->json([
            'supplier_id' => $purchaseInvoice->supplier_id,
            'branch_id' => $purchaseInvoice->branch_id,
            'purchase_type' => $purchaseInvoice->purchase_type,
            'items' => $items,
        ]);
    }

    /**
     * AJAX endpoint: return purchase invoices for a supplier.
     */
    public function supplierInvoices(Supplier $supplier)
    {
        $invoices = PurchaseInvoice::where('supplier_id', $supplier->id)
            ->latest('invoice_date')
            ->get(['id', 'invoice_number', 'invoice_date', 'total'])
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'invoice_date' => $inv->invoice_date?->format('d-m-Y'),
                'total' => number_format((float) $inv->total, 2),
            ]);

        return response()->json(['invoices' => $invoices]);
    }

    /**
     * AJAX endpoint: return items strictly filtered by the selected supplier or purchase invoice.
     */
    public function itemList(Request $request)
    {
        $supplierId = $request->input('supplier_id');
        $invoiceId  = $request->input('purchase_invoice_id');
        $branchId   = (int) ($request->input('branch_id') ?: 3);
        $search     = trim((string) $request->input('search', ''));
        $code       = trim((string) $request->input('code', ''));

        if (! $supplierId && ! $invoiceId) {
            return response()->json([
                'items' => [],
                'message' => 'Please select a Supplier first.',
            ]);
        }

        $ignoreReturnId = $request->integer('ignore_return_id');

        // Case 1: Specific Purchase Invoice selected -> ONLY items/batches from that invoice
        if (! empty($invoiceId)) {
            $query = DB::table('purchase_invoice_items as pii')
                ->join('items as i', 'i.id', '=', 'pii.item_id')
                ->leftJoin('gst_taxes as gt', 'gt.id', '=', 'i.gst_tax_id')
                ->where('pii.purchase_invoice_id', $invoiceId)
                ->select([
                    'i.id',
                    'i.name',
                    'i.item_code',
                    'i.ean_upc_code',
                    'pii.batch_no',
                    'pii.cost_price',
                    'i.sell_price',
                    'i.mrp',
                    'pii.disc_percent',
                    'pii.disc_amount',
                    DB::raw('COALESCE(pii.gst_percent, gt.percentage, 0) as gst_percent'),
                    'pii.exp_date',
                    'pii.qty as invoiced_qty',
                ]);

            if ($search !== '') {
                $tokens = array_values(array_filter(preg_split('/\s+/', $search), fn($t) => $t !== ''));
                foreach ($tokens as $token) {
                    if (preg_match('/^(\d+(?:\.\d+)?)([a-zA-Z]+)$/', $token, $m)) {
                        $num = $m[1];
                        $unit = $m[2];
                        $query->where(function ($q) use ($token, $num, $unit) {
                            $q->where('i.name', 'like', "%{$token}%")
                              ->orWhere(function ($q2) use ($num, $unit) {
                                  $q2->where('i.name', 'like', "%{$num}%")
                                     ->where('i.name', 'like', "%{$unit}%");
                              });
                        });
                    } else {
                        $query->where('i.name', 'like', "%{$token}%");
                    }
                }
            }
            if ($code !== '') {
                $query->where(function ($cq) use ($code) {
                    $cq->where('i.item_code', 'like', "%{$code}%")
                        ->orWhere('i.ean_upc_code', 'like', "%{$code}%");
                });
            }

            $rows = $query->limit(100)->get();

            // Prior returns for this invoice
            $priorReturns = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pr.purchase_invoice_id', $invoiceId)
                ->when($ignoreReturnId, fn ($q) => $q->where('pr.id', '!=', $ignoreReturnId))
                ->groupBy('pri.item_id', 'pri.batch_no')
                ->select('pri.item_id', 'pri.batch_no', DB::raw('SUM(pri.qty) as returned_qty'))
                ->get();

            $alreadyReturnedMap = [];
            foreach ($priorReturns as $pr) {
                $bNo = !empty($pr->batch_no) ? trim($pr->batch_no) : '';
                $alreadyReturnedMap["{$pr->item_id}___{$bNo}"] = (float) $pr->returned_qty;
            }

            $itemIds = $rows->pluck('id')->all();
            $stocks = DB::table('item_stocks')
                ->where('branch_id', $branchId)
                ->whereIn('item_id', $itemIds)
                ->pluck('quantity', 'item_id');

            $result = [];
            foreach ($rows as $row) {
                $stock = (float) ($stocks[$row->id] ?? 0);
                $bNo = !empty($row->batch_no) ? trim($row->batch_no) : '';
                $invoicedQty = (float) ($row->invoiced_qty ?? 0);
                $alreadyReturned = (float) ($alreadyReturnedMap["{$row->id}___{$bNo}"] ?? 0);
                $remainingQty = max(0, round($invoicedQty - $alreadyReturned, 4));

                $result[] = [
                    'id'               => (int) $row->id,
                    'name'             => $row->name,
                    'code'             => ($row->item_code ?? '') ?: (($row->ean_upc_code ?? '') ?: ''),
                    'batch_no'         => $row->batch_no,
                    'qty'              => $stock,
                    'invoiced_qty'     => $invoicedQty,
                    'original_qty'     => $invoicedQty,
                    'already_returned' => $alreadyReturned,
                    'remaining_qty'    => $remainingQty,
                    'cost_price'       => (float) $row->cost_price,
                    'sell_price'       => (float) $row->sell_price,
                    'mrp'              => (float) $row->mrp,
                    'disc_percent'     => (float) $row->disc_percent,
                    'disc_amount'      => (float) $row->disc_amount,
                    'gst_percent'      => (float) $row->gst_percent,
                    'exp_date'         => $row->exp_date ? \Carbon\Carbon::parse($row->exp_date)->format('Y-m-d') : null,
                ];
            }

            return response()->json([
                'items' => $result,
                'source' => 'invoice',
            ]);
        }

        // Case 2: Supplier selected (No invoice) -> ONLY products purchased from THIS supplier
        $query = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->join('items as i', 'i.id', '=', 'pii.item_id')
            ->leftJoin('gst_taxes as gt', 'gt.id', '=', 'i.gst_tax_id')
            ->where('pi.supplier_id', $supplierId)
            ->where('pi.status', '!=', 'Cancelled')
            ->where('i.status', true)
            ->groupBy('i.id', 'pii.batch_no', 'pii.exp_date', 'i.name', 'i.item_code', 'i.ean_upc_code', 'i.cost_price', 'i.sell_price', 'i.mrp', 'gt.percentage')
            ->select([
                'i.id',
                'i.name',
                'i.item_code',
                'i.ean_upc_code',
                'pii.batch_no',
                'pii.exp_date',
                DB::raw('COALESCE(MAX(pii.cost_price), i.cost_price, 0) as cost_price'),
                'i.sell_price',
                'i.mrp',
                DB::raw('0 as disc_percent'),
                DB::raw('0 as disc_amount'),
                DB::raw('COALESCE(MAX(pii.gst_percent), gt.percentage, 0) as gst_percent'),
                DB::raw('SUM(pii.qty) as invoiced_qty'),
            ]);

        if ($search !== '') {
            $query->where('i.name', 'like', "%{$search}%");
        }
        if ($code !== '') {
            $query->where(function ($cq) use ($code) {
                $cq->where('i.item_code', 'like', "%{$code}%")
                    ->orWhere('i.ean_upc_code', 'like', "%{$code}%");
            });
        }

        $rows = $query->limit(100)->get();

        // Prior returns to this supplier
        $priorReturns = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.supplier_id', $supplierId)
            ->when($ignoreReturnId, fn ($q) => $q->where('pr.id', '!=', $ignoreReturnId))
            ->groupBy('pri.item_id', 'pri.batch_no')
            ->select('pri.item_id', 'pri.batch_no', DB::raw('SUM(pri.qty) as returned_qty'))
            ->get();

        $alreadyReturnedMap = [];
        foreach ($priorReturns as $pr) {
            $bNo = !empty($pr->batch_no) ? trim($pr->batch_no) : '';
            $alreadyReturnedMap["{$pr->item_id}___{$bNo}"] = (float) $pr->returned_qty;
        }

        $itemIds = $rows->pluck('id')->all();
        $stocks = DB::table('item_stocks')
            ->where('branch_id', $branchId)
            ->whereIn('item_id', $itemIds)
            ->pluck('quantity', 'item_id');

        $result = [];
        foreach ($rows as $row) {
            $stock = (float) ($stocks[$row->id] ?? 0);
            $bNo = !empty($row->batch_no) ? trim($row->batch_no) : '';
            $invoicedQty = (float) ($row->invoiced_qty ?? 0);
            $alreadyReturned = (float) ($alreadyReturnedMap["{$row->id}___{$bNo}"] ?? 0);
            $remainingQty = max(0, round($invoicedQty - $alreadyReturned, 4));

            $result[] = [
                'id'               => (int) $row->id,
                'name'             => $row->name,
                'code'             => ($row->item_code ?? '') ?: (($row->ean_upc_code ?? '') ?: ''),
                'batch_no'         => $row->batch_no,
                'qty'              => $stock,
                'invoiced_qty'     => $invoicedQty,
                'original_qty'     => $invoicedQty,
                'already_returned' => $alreadyReturned,
                'remaining_qty'    => $remainingQty,
                'cost_price'       => (float) $row->cost_price,
                'sell_price'       => (float) $row->sell_price,
                'mrp'              => (float) $row->mrp,
                'disc_percent'     => (float) $row->disc_percent,
                'disc_amount'      => (float) $row->disc_amount,
                'gst_percent'      => (float) $row->gst_percent,
                'exp_date'         => $row->exp_date ? \Carbon\Carbon::parse($row->exp_date)->format('Y-m-d') : null,
            ];
        }

        return response()->json([
            'items' => $result,
            'source' => 'supplier',
        ]);
    }

    /**
     * AJAX endpoint: lookup a single item by code/query strictly within supplier/invoice scope.
     */
    public function lookupItem(Request $request)
    {
        $supplierId = $request->input('supplier_id');
        $invoiceId  = $request->input('purchase_invoice_id');
        $query      = trim((string) $request->input('query', ''));

        if (! $supplierId && ! $invoiceId) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a Supplier first.',
            ], 422);
        }

        if ($query === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please provide an item code or barcode.',
            ], 422);
        }

        $subReq = Request::create('', 'GET', array_merge($request->all(), [
            'code' => $query,
        ]));
        $items = $this->itemList($subReq)->getData(true)['items'] ?? [];

        $exactMatchOnly = $request->boolean('exact_match_only');

        if (empty($items) && ! $exactMatchOnly) {
            $subReq = Request::create('', 'GET', array_merge($request->all(), [
                'code' => '',
                'search' => $query,
            ]));
            $items = $this->itemList($subReq)->getData(true)['items'] ?? [];
        }

        if (empty($items)) {
            $msg = ! empty($invoiceId)
                ? "Item '{$query}' was not found in the selected Purchase Invoice."
                : "Item '{$query}' does not belong to the selected Supplier.";
            return response()->json(['success' => false, 'message' => $msg], 404);
        }

        return response()->json(array_merge(['success' => true], $items[0]));
    }

    public function print(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['supplier', 'branch', 'purchaseInvoice', 'items.item']);

        return view('purchase.purchase-returns.print', compact('purchaseReturn'));
    }

    private function postStock($createdItems, PurchaseReturn $purchaseReturn): void
    {
        foreach ($createdItems as $itemLine) {
            $originalCost = null;
            if ($purchaseReturn->purchase_invoice_id) {
                $q = PurchaseInvoiceItem::where('purchase_invoice_id', $purchaseReturn->purchase_invoice_id)
                    ->where('item_id', $itemLine->item_id);
                if (!empty($itemLine->batch_no)) {
                    $q->where('batch_no', $itemLine->batch_no);
                }
                $originalCost = $q->value('cost_price');
            }

            // Negative delta for stock out
            $ledgerRow = $this->stockLedger->post(
                itemId: $itemLine->item_id,
                branchId: $purchaseReturn->branch_id,
                movementType: 'PURCHASE_RETURN',
                qtyDelta: -abs((float) $itemLine->qty),
                unitCost: $originalCost !== null ? (float) $originalCost : (float) $itemLine->cost_price,
                referenceType: PurchaseReturn::class,
                referenceId: $purchaseReturn->id,
                documentDate: $purchaseReturn->return_date->toDateString(),
                expDate: $itemLine->exp_date?->toDateString(),
                batchNo: $itemLine->batch_no ?? null,
            );

            $itemLine->update(['cost_at_return' => $ledgerRow->unit_cost]);
        }
    }

    private function nextNumber(): string
    {
        // Atomic: serialised on a counter row inside the store transaction (was max(id)+1, racy).
        return app(\App\Services\Accounting\DocumentNumberingService::class)
            ->nextPrefixed('PRN', \App\Models\PurchaseReturn::class, 'return_number', 6);
    }

    private function formOptions(?PurchaseReturn $purchaseReturn = null): array
    {
        // Only load items already in this return (edit/reload) — never the full item catalogue.
        $oldItems    = old('items');
        $oldItemIds  = is_array($oldItems) ? collect($oldItems)->pluck('item_id')->filter() : collect();
        $existingIds = collect($purchaseReturn?->items ?? [])->pluck('item_id')->merge($oldItemIds)->filter()->unique();
        $items       = $existingIds->isNotEmpty()
            ? Item::whereIn('id', $existingIds)->orderBy('name')->pluck('name', 'id')
            : collect();

        return [
            'suppliers'        => Supplier::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'branches'         => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items'            => $items,
            'purchaseInvoices' => PurchaseInvoice::latest('invoice_date')->take(100)->pluck('invoice_number', 'id'),
        ];
    }

    private function computeLines(array $items, array $header): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');
        $isInterstate = ($header['purchase_type'] ?? null) === 'Interstate';

        return collect($items)->map(function ($line) use ($itemsById, $isInterstate) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $item = $itemsById[$line['item_id']];

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);

            $tax = $this->taxEngine->calculate(
                $qty,
                $costPrice,
                $item,
                $discPercent,
                $discAmount,
                0.0,
                $isInterstate,
                isTaxInclusive: false
            );

            $batchNo = !empty($line['batch_no']) ? trim($line['batch_no']) : null;
            if (!$batchNo && !empty($header['purchase_invoice_id'])) {
                $batchNo = PurchaseInvoiceItem::where('purchase_invoice_id', $header['purchase_invoice_id'])
                    ->where('item_id', $line['item_id'])
                    ->value('batch_no');
            }

            return [
                'item_id' => $line['item_id'],
                'batch_no' => $batchNo,
                'exp_date' => !empty($line['exp_date']) ? $line['exp_date'] : null,
                'qty' => $qty,
                'cost_price' => $costPrice,
                'disc_percent' => $discPercent,
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
        $roundOff = (float) ($data['header']['round_off'] ?? 0);

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total_cgst' => $collection->sum('cgst_amount'),
            'total_sgst' => $collection->sum('sgst_amount'),
            'total_igst' => $collection->sum('igst_amount'),
            'total' => round($collection->sum('net_amount') + $roundOff, 2),
            'round_off' => $roundOff,
        ];
    }

    /**
     * Server-side returnable-quantity rules against the purchase invoice OR supplier:
     *   - If Invoice selected: invoice must belong to selected supplier and not be cancelled.
     *     Items/batches must exist on the invoice.
     *     Requested qty per (item, batch) <= original invoice purchased qty - previously returned qty.
     *   - If No Invoice selected: item/batch must have been purchased from this supplier across non-cancelled invoices.
     *     Requested qty per (item, batch) <= total supplier purchased qty - total previously returned to supplier.
     */
    private function assertReturnableEligibility(array $header, array $items, ?int $currentReturnId): void
    {
        $supplierId = (int) ($header['supplier_id'] ?? 0);
        $invoiceId = (int) ($header['purchase_invoice_id'] ?? 0);
        $supplier = Supplier::find($supplierId);

        if (! $supplier) {
            throw ValidationException::withMessages(['supplier_id' => 'Invalid supplier selected.']);
        }

        // Case 1: Specific Purchase Invoice selected
        if ($invoiceId > 0) {
            $invoice = \App\Models\PurchaseInvoice::find($invoiceId);
            if (! $invoice) {
                throw ValidationException::withMessages(['purchase_invoice_id' => 'Selected Purchase Invoice does not exist.']);
            }
            if ((int) $invoice->supplier_id !== $supplierId) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => "Purchase Invoice #{$invoice->invoice_number} does not belong to the selected supplier {$supplier->name}.",
                ]);
            }
            if ($invoice->status === 'Cancelled') {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => "Purchase Invoice #{$invoice->invoice_number} is cancelled and cannot be returned against.",
                ]);
            }

            $invoiceLines = DB::table('purchase_invoice_items')
                ->where('purchase_invoice_id', $invoiceId)
                ->get(['item_id', 'batch_no', 'qty']);

            // Group invoice items by item_id and normalized batch_no
            $invoiceQtyMap = [];
            foreach ($invoiceLines as $invLine) {
                $bNo = !empty($invLine->batch_no) ? trim($invLine->batch_no) : '';
                $key = "{$invLine->item_id}___{$bNo}";
                $invoiceQtyMap[$key] = ($invoiceQtyMap[$key] ?? 0.0) + (float) $invLine->qty;
            }

            // Prior returns for this invoice
            $priorReturnLines = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pr.purchase_invoice_id', $invoiceId)
                ->when($currentReturnId, fn ($q) => $q->where('pr.id', '!=', $currentReturnId))
                ->get(['pri.item_id', 'pri.batch_no', 'pri.qty']);

            $alreadyReturnedMap = [];
            foreach ($priorReturnLines as $prLine) {
                $bNo = !empty($prLine->batch_no) ? trim($prLine->batch_no) : '';
                $key = "{$prLine->item_id}___{$bNo}";
                $alreadyReturnedMap[$key] = ($alreadyReturnedMap[$key] ?? 0.0) + (float) $prLine->qty;
            }

            // Sum requested by item & batch
            $requestedMap = [];
            foreach ($items as $line) {
                $itemId = (int) $line['item_id'];
                $batchNo = !empty($line['batch_no']) ? trim($line['batch_no']) : '';
                $key = "{$itemId}___{$batchNo}";
                $requestedMap[$key] = ($requestedMap[$key] ?? 0.0) + (float) $line['qty'];
            }

            foreach ($requestedMap as $key => $requestedQty) {
                [$itemId, $batchNo] = explode('___', $key);
                $itemId = (int) $itemId;
                $item = Item::find($itemId);
                $itemName = $item ? $item->name : "Item #{$itemId}";
                $batchLabel = $batchNo !== '' ? " (Batch: {$batchNo})" : '';

                // If specific batch requested, must exist on invoice
                $origPurchased = $invoiceQtyMap[$key] ?? null;
                if ($origPurchased === null) {
                    // Check if item exists on invoice under empty batch or another batch
                    $itemExists = false;
                    foreach ($invoiceQtyMap as $ik => $iq) {
                        if (str_starts_with($ik, "{$itemId}___")) {
                            $itemExists = true;
                            break;
                        }
                    }
                    if (! $itemExists) {
                        throw ValidationException::withMessages([
                            'items' => "The item '{$itemName}' does not belong to Purchase Invoice #{$invoice->invoice_number}.",
                        ]);
                    } else {
                        throw ValidationException::withMessages([
                            'items' => "The batch '{$batchNo}' for item '{$itemName}' was not found in Purchase Invoice #{$invoice->invoice_number}.",
                        ]);
                    }
                }

                $alreadyReturned = $alreadyReturnedMap[$key] ?? 0.0;
                $remaining = max(0, round($origPurchased - $alreadyReturned, 4));

                if (round($requestedQty, 4) > round($remaining, 4)) {
                    $remDisp = ($remaining == (int) $remaining) ? (int) $remaining : $remaining;
                    if ($alreadyReturned > 0) {
                        throw ValidationException::withMessages([
                            'items' => "Return quantity for '{$itemName}'{$batchLabel} cannot exceed the remaining returnable quantity of {$remDisp} (Purchased: {$origPurchased}, Previously Returned: {$alreadyReturned}).",
                        ]);
                    }
                    throw ValidationException::withMessages([
                        'items' => "Return quantity for '{$itemName}'{$batchLabel} cannot exceed the purchased quantity of {$origPurchased} in Invoice #{$invoice->invoice_number}.",
                    ]);
                }
            }
            return;
        }

        // Case 2: Direct Supplier Return (No invoice selected)
        // Group requested items by item_id and batch_no
        $requestedMap = [];
        foreach ($items as $line) {
            $itemId = (int) $line['item_id'];
            $batchNo = !empty($line['batch_no']) ? trim($line['batch_no']) : '';
            $key = "{$itemId}___{$batchNo}";
            $requestedMap[$key] = ($requestedMap[$key] ?? 0.0) + (float) $line['qty'];
        }

        foreach ($requestedMap as $key => $requestedQty) {
            [$itemId, $batchNo] = explode('___', $key);
            $itemId = (int) $itemId;
            $item = Item::find($itemId);
            $itemName = $item ? $item->name : "Item #{$itemId}";
            $batchLabel = $batchNo !== '' ? " (Batch: {$batchNo})" : '';

            // Calculate total purchased from this supplier across non-cancelled purchase invoices
            $purchasedQ = DB::table('purchase_invoice_items as pii')
                ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
                ->where('pi.supplier_id', $supplierId)
                ->where('pi.status', '!=', 'Cancelled')
                ->where('pii.item_id', $itemId);

            if ($batchNo !== '') {
                $purchasedQ->where('pii.batch_no', $batchNo);
            }
            $totalPurchasedFromSupplier = (float) $purchasedQ->sum('pii.qty');

            // Calculate total previously returned to this supplier across purchase returns
            $returnedQ = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pr.supplier_id', $supplierId)
                ->where('pri.item_id', $itemId)
                ->when($currentReturnId, fn ($q) => $q->where('pr.id', '!=', $currentReturnId));

            if ($batchNo !== '') {
                $returnedQ->where('pri.batch_no', $batchNo);
            }
            $totalReturnedToSupplier = (float) $returnedQ->sum('pri.qty');

            $eligibleSupplierQty = max(0, round($totalPurchasedFromSupplier - $totalReturnedToSupplier, 4));

            if ($totalPurchasedFromSupplier <= 0) {
                throw ValidationException::withMessages([
                    'items' => "The item '{$itemName}'{$batchLabel} has never been purchased from supplier '{$supplier->name}'. Returns must be against items actually purchased from the selected supplier.",
                ]);
            }

            if (round($requestedQty, 4) > round($eligibleSupplierQty, 4)) {
                $eligDisp = ($eligibleSupplierQty == (int) $eligibleSupplierQty) ? (int) $eligibleSupplierQty : $eligibleSupplierQty;
                throw ValidationException::withMessages([
                    'items' => "Return quantity ({$requestedQty}) for '{$itemName}'{$batchLabel} exceeds the eligible returnable quantity ({$eligDisp}) purchased from supplier '{$supplier->name}' (Purchased: {$totalPurchasedFromSupplier}, Previously Returned: {$totalReturnedToSupplier}).",
                ]);
            }
        }
    }

    private function validateData(Request $request): array
    {
        $headerRules = [
            'return_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'purchase_invoice_id' => ['nullable', 'exists:purchase_invoices,id'],
            'supplier_debit_note_no' => ['nullable', 'string', 'max:50'],
            'supplier_debit_note_date' => ['nullable', 'date'],
            'purchase_type' => ['required', 'in:Local,Interstate'],
            'round_off' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('purchase_returns', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.disc_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.disc_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        foreach ($validated['items'] as $index => $itemLine) {
            $qty = (float) ($itemLine['qty'] ?? 0);
            $costPrice = (float) ($itemLine['cost_price'] ?? 0);
            $discAmount = (float) ($itemLine['disc_amount'] ?? 0);
            $discPercent = (float) ($itemLine['disc_percent'] ?? 0);
            $lineGross = round($qty * $costPrice, 2);

            if ($discPercent > 100) {
                throw ValidationException::withMessages([
                    "items.{$index}.disc_percent" => "Item #".($index + 1).": Discount percentage cannot exceed 100%.",
                ]);
            }
            if ($discAmount > ($lineGross + 0.01)) {
                throw ValidationException::withMessages([
                    "items.{$index}.disc_amount" => "Item #".($index + 1).": Discount amount (₹{$discAmount}) cannot exceed line total cost (₹{$lineGross}).",
                ]);
            }
        }

        $currentReturnId = $request->route('purchase_return')
            ? (is_object($request->route('purchase_return')) ? $request->route('purchase_return')->id : (int) $request->route('purchase_return'))
            : null;

        // Authoritative validation of returnable quantities against supplier & batch
        $this->assertReturnableEligibility($header, $validated['items'], $currentReturnId);

        return ['header' => $header, 'items' => $validated['items']];
    }

    private function assertStockAvailable(array $items, int $branchId): void
    {
        $totalQtyByItem = [];
        $totalQtyByBatch = [];
        $batchService = app(\App\Services\Inventory\BatchStockService::class);

        foreach ($items as $line) {
            $itemId = (int) $line['item_id'];
            $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0.0) + (float) $line['qty'];

            $batchNo = !empty($line['batch_no']) ? trim($line['batch_no']) : null;
            if ($batchNo) {
                $bKey = "{$itemId}___{$batchNo}";
                $totalQtyByBatch[$bKey] = ($totalQtyByBatch[$bKey] ?? 0.0) + (float) $line['qty'];
            }
        }

        foreach ($totalQtyByItem as $itemId => $totalRequested) {
            $item = Item::find($itemId);
            if (! $item) {
                continue;
            }

            $available = (float) (ItemStock::where('item_id', $itemId)->where('branch_id', $branchId)->value('quantity') ?? 0);
            if (round($totalRequested, 4) > round($available, 4)) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for \"{$item->name}\" at branch: available {$available}, requested {$totalRequested}.",
                ]);
            }
        }

        foreach ($totalQtyByBatch as $bKey => $requestedBatchQty) {
            [$itemId, $batchNo] = explode('___', $bKey);
            $itemId = (int) $itemId;
            $bStock = $batchService->getBatchStock($itemId, $branchId, $batchNo);
            $bAvail = (float) ($bStock['remaining_qty'] ?? 0);
            if (round($requestedBatchQty, 4) > round($bAvail, 4)) {
                $item = Item::find($itemId);
                $itemName = $item ? $item->name : "Item #{$itemId}";
                throw ValidationException::withMessages([
                    'items' => "Insufficient batch stock for \"{$itemName}\" (Batch: {$batchNo}) at branch: available {$bAvail}, requested {$requestedBatchQty}.",
                ]);
            }
        }
    }
}
