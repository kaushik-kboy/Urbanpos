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

        $user = $request->user();
        if ($user && $user->branch_id && ! $user->hasRole('Owner') && $user->email !== 'admin@urbanpos.com') {
            $query->where('branch_id', $user->branch_id);
        } else {
            if ($request->filled('branch_id') && $request->input('branch_id') !== 'all') {
                $query->where('branch_id', $request->input('branch_id'));
            }
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
        $branches = ($user && $user->branch_id && ! $user->hasRole('Owner') && $user->email !== 'admin@urbanpos.com')
            ? Branch::where('id', $user->branch_id)->pluck('name', 'id')
            : Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
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
                    'return_number' => $this->nextNumber($data['header']['branch_id'] ?? null, $data['header']['return_date'] ?? null),
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

            // Reversal of existing return's stock delta MUST occur before checking stock availability for updated quantities
            $this->stockLedger->reverseByReference(PurchaseReturn::class, $purchaseReturn->id);

            $this->assertReturnableEligibility($data['header'], $data['items'], $purchaseReturn->id);
            $this->assertStockAvailable($data['items'], (int) $data['header']['branch_id']);

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
        $branchId = (int) ($request->input('branch_id') ?: $purchaseInvoice->branch_id);
        $batchService = app(\App\Services\Inventory\BatchStockService::class);

        // Sum prior returns for this purchase invoice per item, batch, exp_date, and cost
        $priorReturns = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.purchase_invoice_id', $purchaseInvoice->id)
            ->when($ignoreReturnId, fn ($q) => $q->where('pr.id', '!=', $ignoreReturnId))
            ->groupBy('pri.item_id', 'pri.batch_no', DB::raw('DATE(pri.exp_date)'), 'pri.cost_price')
            ->select(
                'pri.item_id',
                'pri.batch_no',
                DB::raw('DATE(pri.exp_date) as exp_val'),
                'pri.cost_price',
                DB::raw('SUM(pri.qty) as returned_qty')
            )
            ->get();

        $alreadyReturnedMap = [];
        foreach ($priorReturns as $pr) {
            $bNo = !empty($pr->batch_no) ? trim($pr->batch_no) : '';
            $expVal = !empty($pr->exp_val) ? trim($pr->exp_val) : '';
            $costVal = round((float) $pr->cost_price, 2);

            $key = "{$pr->item_id}___{$bNo}___{$expVal}___{$costVal}";
            $alreadyReturnedMap[$key] = (float) $pr->returned_qty;

            $fbKey = "{$pr->item_id}___{$bNo}";
            $alreadyReturnedMap[$fbKey] = ($alreadyReturnedMap[$fbKey] ?? 0.0) + (float) $pr->returned_qty;
        }

        $ignoredReturnItems = [];
        if ($ignoreReturnId) {
            $ignoredRows = DB::table('purchase_return_items as pri')
                ->where('pri.purchase_return_id', $ignoreReturnId)
                ->select('pri.item_id', 'pri.batch_no', DB::raw('DATE(pri.exp_date) as exp_val'), 'pri.cost_price', 'pri.qty')
                ->get();
            foreach ($ignoredRows as $irr) {
                $b = !empty($irr->batch_no) ? trim($irr->batch_no) : '';
                $e = !empty($irr->exp_val) ? trim($irr->exp_val) : '';
                $c = round((float) $irr->cost_price, 2);
                $k = "{$irr->item_id}___{$b}___{$e}___{$c}";
                $ignoredReturnItems[$k] = ($ignoredReturnItems[$k] ?? 0.0) + (float) $irr->qty;

                $fb = "{$irr->item_id}___{$b}";
                $ignoredReturnItems[$fb] = ($ignoredReturnItems[$fb] ?? 0.0) + (float) $irr->qty;

                $itK = (string) $irr->item_id;
                $ignoredReturnItems[$itK] = ($ignoredReturnItems[$itK] ?? 0.0) + (float) $irr->qty;
            }
        }

        $itemIds = $purchaseInvoice->items->pluck('item_id')->unique()->all();
        $totalStocks = ItemStock::where('branch_id', $branchId)
            ->whereIn('item_id', $itemIds)
            ->pluck('quantity', 'item_id');

        $allocatedStockMap = [];

        $items = $purchaseInvoice->items()->with('item')->get()->map(function ($line) use (
            $alreadyReturnedMap,
            $ignoredReturnItems,
            $batchService,
            $branchId,
            $totalStocks,
            &$allocatedStockMap
        ) {
            $originalQty = (float) $line->qty;
            $bNo = !empty($line->batch_no) ? trim($line->batch_no) : '';
            $expStr = $line->exp_date ? $line->exp_date->format('Y-m-d') : null;
            $costVal = round((float) $line->cost_price, 2);

            $key = "{$line->item_id}___{$bNo}___{$expStr}___{$costVal}";
            $alreadyReturned = (float) ($alreadyReturnedMap[$key] ?? ($alreadyReturnedMap["{$line->item_id}___{$bNo}"] ?? 0));
            $invoiceRemaining = max(0, round($originalQty - $alreadyReturned, 4));

            // Find batch stock in the returning branch
            $bStock = $batchService->getBatchStock((int) $line->item_id, $branchId, $bNo, $expStr, (float) $line->mrp);
            $totalItemStock = max(0, (float) ($totalStocks[$line->item_id] ?? 0));

            $batchAvail = $bStock ? max(0, (float) ($bStock['remaining_qty'] ?? 0)) : $totalItemStock;
            $batchAvail = min($batchAvail, $totalItemStock);

            $ignoredQty = (float) ($ignoredReturnItems[$key] ?? ($ignoredReturnItems["{$line->item_id}___{$bNo}"] ?? ($ignoredReturnItems[(string)$line->item_id] ?? 0)));

            $allocKey = "{$line->item_id}___" . ($bStock['batch_no'] ?? $bNo) . "___" . ($expStr ?? '') . "___" . round((float) $line->mrp, 2);
            $alreadyAllocated = (float) ($allocatedStockMap[$allocKey] ?? 0);
            $effectiveStock = max(0, round($batchAvail + $ignoredQty - $alreadyAllocated, 4));

            // Returnable quantity cannot exceed remaining invoice quantity AND cannot exceed available physical stock!
            $returnableQty = min($invoiceRemaining, $effectiveStock);
            $allocatedStockMap[$allocKey] = $alreadyAllocated + $returnableQty;

            $resolvedBatchNo = !empty($line->batch_no) ? (string) $line->batch_no : '';

            return [
                'item_id' => $line->item_id,
                'batch_no' => $resolvedBatchNo,
                'item_name' => $line->item?->name ?? 'Unknown',
                'item_code' => $line->item?->item_code ?? $line->item?->ean_upc_code ?? '',
                'exp_date' => $expStr,
                'original_qty' => $originalQty,
                'already_returned' => $alreadyReturned,
                'invoice_remaining_qty' => $invoiceRemaining,
                'available_stock' => $effectiveStock,
                'remaining_qty' => $returnableQty,
                'qty' => $returnableQty,
                'cost_price' => (float) $line->cost_price,
                'mrp' => (float) $line->mrp,
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
    public function supplierInvoices(Supplier $supplier, Request $request)
    {
        $query = PurchaseInvoice::where('supplier_id', $supplier->id)
            ->where('status', '!=', 'Cancelled');

        if ($branchId = $request->input('branch_id')) {
            $query->where('branch_id', $branchId);
        }

        $invoices = $query->latest('invoice_date')
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
        $invoiceObj = $invoiceId ? PurchaseInvoice::find($invoiceId) : null;
        $branchId   = (int) ($request->input('branch_id') ?: ($invoiceObj?->branch_id ?: session('active_branch_id', auth()->user()?->branch_id ?: 1)));
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
                    $cq->where('i.item_code', $code)
                        ->orWhere('i.ean_upc_code', $code);
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
            $batchService = app(\App\Services\Inventory\BatchStockService::class);

            $result = [];
            foreach ($rows as $row) {
                $stock = (float) ($stocks[$row->id] ?? 0);
                $bNo = !empty($row->batch_no) ? trim($row->batch_no) : '';
                $expDate = $row->exp_date ? \Carbon\Carbon::parse($row->exp_date)->format('Y-m-d') : null;
                $bStock = $batchService->getBatchStock((int) $row->id, $branchId, $bNo, $expDate, (float) $row->mrp);
                $batchStock = $bStock ? (float) ($bStock['remaining_qty'] ?? 0) : $stock;

                $ignoredQty = 0.0;
                if ($ignoreReturnId) {
                    $ignoredQty = (float) DB::table('purchase_return_items')
                        ->where('purchase_return_id', $ignoreReturnId)
                        ->where('item_id', $row->id)
                        ->sum('qty');
                }
                $effectiveStock = max(0, min($batchStock, $stock) + $ignoredQty);

                $invoicedQty = (float) ($row->invoiced_qty ?? 0);
                $alreadyReturned = (float) ($alreadyReturnedMap["{$row->id}___{$bNo}"] ?? 0);
                $remainingQty = max(0, round($invoicedQty - $alreadyReturned, 4));
                $returnableQty = min($remainingQty, $effectiveStock);

                $result[] = [
                    'id'               => (int) $row->id,
                    'name'             => $row->name,
                    'code'             => ($row->item_code ?? '') ?: (($row->ean_upc_code ?? '') ?: ''),
                    'batch_no'         => !empty($row->batch_no) ? (string) $row->batch_no : '',
                    'qty'              => $effectiveStock,
                    'available_stock'  => $effectiveStock,
                    'invoiced_qty'     => $invoicedQty,
                    'original_qty'     => $invoicedQty,
                    'already_returned' => $alreadyReturned,
                    'remaining_qty'    => $returnableQty,
                    'cost_price'       => (float) $row->cost_price,
                    'sell_price'       => (float) $row->sell_price,
                    'mrp'              => (float) $row->mrp,
                    'disc_percent'     => (float) $row->disc_percent,
                    'disc_amount'      => (float) $row->disc_amount,
                    'gst_percent'      => (float) $row->gst_percent,
                    'exp_date'         => $expDate,
                ];
            }

            return response()->json([
                'items' => $result,
                'source' => 'invoice',
            ]);
        }

        // Case 2: Supplier selected (No invoice) -> items belonging to this supplier (either item.supplier_id or via purchase history)
        $query = DB::table('items as i')
            ->leftJoin('gst_taxes as gt', 'gt.id', '=', 'i.gst_tax_id')
            ->where('i.status', true)
            ->where(function ($sq) use ($supplierId) {
                $sq->where('i.supplier_id', $supplierId)
                    ->orWhereExists(function ($sub) use ($supplierId) {
                        $sub->select(DB::raw(1))
                            ->from('purchase_invoice_items as pii')
                            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
                            ->whereColumn('pii.item_id', 'i.id')
                            ->where('pi.supplier_id', $supplierId);
                    });
            })
            ->select([
                'i.id',
                'i.name',
                'i.item_code',
                'i.ean_upc_code',
                'i.cost_price',
                'i.sell_price',
                'i.mrp',
                DB::raw('0 as disc_percent'),
                DB::raw('0 as disc_amount'),
                DB::raw('COALESCE(gt.percentage, 0) as gst_percent'),
                DB::raw('NULL as exp_date'),
                DB::raw('NULL as invoiced_qty'),
                DB::raw('NULL as batch_no'),
            ]);

        if ($search !== '') {
            $query->where('i.name', 'like', "%{$search}%");
        }
        if ($code !== '') {
            $query->where(function ($cq) use ($code) {
                $cq->where('i.item_code', $code)
                    ->orWhere('i.ean_upc_code', $code);
            });
        }

        $rows = $query->limit(100)->get();

        $itemIds = $rows->pluck('id')->all();
        $stocks = DB::table('item_stocks')
            ->where('branch_id', $branchId)
            ->whereIn('item_id', $itemIds)
            ->pluck('quantity', 'item_id');

        $result = [];
        foreach ($rows as $row) {
            $stock = (float) ($stocks[$row->id] ?? 0);
            $result[] = [
                'id'               => (int) $row->id,
                'name'             => $row->name,
                'code'             => ($row->item_code ?? '') ?: (($row->ean_upc_code ?? '') ?: ''),
                'batch_no'         => null,
                'qty'              => $stock,
                'available_stock'  => $stock,
                'invoiced_qty'     => null,
                'original_qty'     => null,
                'already_returned' => 0,
                'remaining_qty'    => null,
                'cost_price'       => (float) $row->cost_price,
                'sell_price'       => (float) $row->sell_price,
                'mrp'              => (float) $row->mrp,
                'disc_percent'     => (float) $row->disc_percent,
                'disc_amount'      => (float) $row->disc_amount,
                'gst_percent'      => (float) $row->gst_percent,
                'exp_date'         => null,
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

        if (empty($items)) {
            $msg = ! empty($invoiceId)
                ? "Item '{$query}' was not found in the selected Purchase Invoice."
                : "Item '{$query}' does not belong to the selected Supplier.";
            return response()->json(['success' => false, 'message' => $msg], 404);
        }

        // Return exact match if found, otherwise first match of exact code
        $matched = null;
        foreach ($items as $it) {
            if (strcasecmp((string)($it['code'] ?? ''), $query) === 0 ||
                strcasecmp((string)($it['item_code'] ?? ''), $query) === 0 ||
                strcasecmp((string)($it['ean_upc_code'] ?? ''), $query) === 0) {
                $matched = $it;
                break;
            }
        }

        return response()->json(array_merge(['success' => true], $matched ?? $items[0]));
    }

    public function print(PurchaseReturn $purchaseReturn)
    {
        $user = auth()->user();
        if ($user && $user->branch_id && ! $user->hasRole('Owner') && $user->email !== 'admin@urbanpos.com') {
            if ((int) $purchaseReturn->branch_id !== (int) $user->branch_id) {
                abort(403, 'You do not have access to this branch.');
            }
        }

        $purchaseReturn->load(['supplier', 'branch', 'purchaseInvoice', 'items.item']);

        return view('purchase.purchase-returns.print', compact('purchaseReturn'));
    }

    private function postStock($createdItems, PurchaseReturn $purchaseReturn): void
    {
        foreach ($createdItems as $itemLine) {
            $originalCost = null;
            $originalMrp = null;
            if ($purchaseReturn->purchase_invoice_id) {
                $q = PurchaseInvoiceItem::where('purchase_invoice_id', $purchaseReturn->purchase_invoice_id)
                    ->where('item_id', $itemLine->item_id);
                if (!empty($itemLine->batch_no)) {
                    $q->where('batch_no', $itemLine->batch_no);
                }
                $originalCost = $q->value('cost_price');
                $originalMrp = $q->value('mrp');
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
                mrp: (float) (($itemLine->mrp && (float)$itemLine->mrp > 0) ? $itemLine->mrp : ($originalMrp ?: ($itemLine->item?->mrp ?? 0))),
            );

            $itemLine->update(['cost_at_return' => $ledgerRow->unit_cost]);
        }
    }

    private function nextNumber(?int $branchId = null, ?string $date = null): string
    {
        $branchId = $branchId ?: session('active_branch_id', auth()->user()?->branch_id);

        return app(\App\Services\Accounting\DocumentNumberingService::class)->generate(
            'purchase_return',
            $branchId ? (int) $branchId : null,
            $date
        );
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

        $activeBranchId = session('active_branch_id', auth()->user()?->branch_id);
        $invoiceQuery = PurchaseInvoice::latest('invoice_date');
        if ($activeBranchId) {
            $invoiceQuery->where('branch_id', $activeBranchId);
        }

        return [
            'suppliers'        => Supplier::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'branches'         => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items'            => $items,
            'purchaseInvoices' => $invoiceQuery->take(100)->pluck('invoice_number', 'id'),
        ];
    }

    private function computeLines(array $items, array $header): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');
        $supplier = isset($header['supplier_id']) ? Supplier::find($header['supplier_id']) : null;
        $isInterstate = (($header['purchase_type'] ?? null) === 'Interstate') || ($supplier && $supplier->isInterstate());

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

                // If specific batch requested, must exist on invoice or match unbatched invoice item
                $origPurchased = $invoiceQtyMap[$key] ?? null;
                $effectiveKey = $key;
                if ($origPurchased === null) {
                    // Check if item exists on invoice under empty batch (unbatched purchase)
                    if (isset($invoiceQtyMap["{$itemId}___"])) {
                        $origPurchased = $invoiceQtyMap["{$itemId}___"];
                        $effectiveKey = "{$itemId}___";
                    } else {
                        // Check if item exists on invoice under another batch
                        $itemExists = false;
                        foreach ($invoiceQtyMap as $ik => $iq) {
                            if (str_starts_with($ik, "{$itemId}___")) {
                                $itemExists = true;
                                break;
                            }
                        }
                        if (! $itemExists) {
                            throw ValidationException::withMessages([
                                'items' => "The item '{$itemName}' does not belong to the selected Purchase Invoice.",
                            ]);
                        } else {
                            throw ValidationException::withMessages([
                                'items' => "The batch '{$batchNo}' for item '{$itemName}' was not found in Purchase Invoice #{$invoice->invoice_number}.",
                            ]);
                        }
                    }
                }

                $alreadyReturned = $alreadyReturnedMap[$key] ?? ($alreadyReturnedMap[$effectiveKey] ?? 0.0);
                $remaining = max(0, round($origPurchased - $alreadyReturned, 4));

                if (round($requestedQty, 4) > round($remaining, 4)) {
                    $remDisp = ($remaining == (int) $remaining) ? (int) $remaining : $remaining;
                    if ($alreadyReturned > 0) {
                        throw ValidationException::withMessages([
                            'items' => "Return quantity cannot exceed the remaining returnable quantity of {$remDisp}.",
                        ]);
                    }
                    throw ValidationException::withMessages([
                        'items' => "Return quantity cannot be greater than the available purchase quantity.",
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

            $belongsToSupplier = DB::table('items')
                ->where('id', $itemId)
                ->where(function ($q) use ($supplierId) {
                    $q->where('supplier_id', $supplierId)
                        ->orWhereExists(function ($sub) use ($supplierId) {
                            $sub->select(DB::raw(1))
                                ->from('purchase_invoice_items as pii')
                                ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
                                ->whereColumn('pii.item_id', 'items.id')
                                ->where('pi.supplier_id', $supplierId);
                        });
                })
                ->exists();

            if (! $belongsToSupplier) {
                throw ValidationException::withMessages([
                    'items' => "The item '{$itemName}' does not belong to the selected Supplier.",
                ]);
            }

            if ($totalPurchasedFromSupplier > 0 && round($requestedQty, 4) > round($eligibleSupplierQty, 4)) {
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
            'items.*.qty' => ['required', 'integer', 'min:1'],
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
        $batchLines = [];
        $batchService = app(\App\Services\Inventory\BatchStockService::class);

        foreach ($items as $line) {
            $itemId = (int) $line['item_id'];
            $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0.0) + (float) $line['qty'];

            $batchNo = !empty($line['batch_no']) ? trim($line['batch_no']) : '';
            $expDate = !empty($line['exp_date']) ? substr(trim($line['exp_date']), 0, 10) : '';
            $mrp = isset($line['mrp']) ? (float)$line['mrp'] : null;

            $bKey = "{$itemId}___{$batchNo}___{$expDate}___" . ($mrp ? round($mrp, 2) : '0');
            if (!isset($batchLines[$bKey])) {
                $batchLines[$bKey] = [
                    'item_id' => $itemId,
                    'batch_no' => $batchNo,
                    'exp_date' => $expDate ?: null,
                    'mrp' => $mrp,
                    'qty' => 0.0,
                ];
            }
            $batchLines[$bKey]['qty'] += (float) $line['qty'];
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

        foreach ($batchLines as $bInfo) {
            $itemId = $bInfo['item_id'];
            $batchNo = $bInfo['batch_no'];
            $expDate = $bInfo['exp_date'];
            $mrp = $bInfo['mrp'];
            $requestedBatchQty = $bInfo['qty'];

            if ($batchNo !== '' || $expDate !== null) {
                $bStock = $batchService->getBatchStock($itemId, $branchId, $batchNo, $expDate, $mrp);
                if ($bStock) {
                    $bAvail = (float) ($bStock['remaining_qty'] ?? 0);
                    if (round($requestedBatchQty, 4) > round($bAvail, 4)) {
                        $item = Item::find($itemId);
                        $itemName = $item ? $item->name : "Item #{$itemId}";
                        $label = $batchNo !== '' ? "Batch: {$batchNo}" : ($expDate ? "Exp: {$expDate}" : '');
                        throw ValidationException::withMessages([
                            'items' => "Insufficient batch stock for \"{$itemName}\" ({$label}) at branch: available {$bAvail}, requested {$requestedBatchQty}.",
                        ]);
                    }
                }
            }
        }
    }
}
