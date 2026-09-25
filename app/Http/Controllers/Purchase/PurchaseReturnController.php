<?php

namespace App\Http\Controllers\Purchase;

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
        $query = PurchaseReturn::with(['supplier', 'branch', 'purchaseInvoice'])->latest('return_date');

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
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['return_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = PurchaseReturn::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('purchase.purchase-returns.index')
                    ->with('status', "Purchase Return {$existing->return_number} created successfully.");
            }
        }

        $this->assertStockAvailable($data['items'], (int) $data['header']['branch_id']);

        $purchaseReturn = DB::transaction(function () use ($data) {
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

        // Sum prior returns for this purchase invoice per item
        $alreadyReturnedByItem = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.purchase_invoice_id', $purchaseInvoice->id)
            ->when($ignoreReturnId, fn ($q) => $q->where('pr.id', '!=', $ignoreReturnId))
            ->groupBy('pri.item_id')
            ->select('pri.item_id', DB::raw('SUM(pri.qty) as returned_qty'))
            ->pluck('returned_qty', 'item_id')
            ->all();

        $items = $purchaseInvoice->items()->with('item')->get()->map(function ($line) use ($alreadyReturnedByItem) {
            $originalQty = (float) $line->qty;
            $alreadyReturned = (float) ($alreadyReturnedByItem[$line->item_id] ?? 0);
            $remainingQty = max(0, round($originalQty - $alreadyReturned, 4));

            return [
                'item_id' => $line->item_id,
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

        // Case 1: Specific Purchase Invoice selected -> ONLY items from that invoice
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
                    'pii.cost_price',
                    'i.sell_price',
                    'i.mrp',
                    'pii.disc_percent',
                    'pii.disc_amount',
                    DB::raw('COALESCE(pii.gst_percent, gt.percentage, 0) as gst_percent'),
                    'pii.exp_date',
                    'pii.qty as invoiced_qty',
                ]);
        } else {
            // Case 2: Supplier selected (No invoice) -> ONLY products of this supplier
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
                ]);
        }

        if ($search !== '') {
            $query->where('i.name', 'like', "%{$search}%");
        }

        if ($code !== '') {
            $query->where(function ($cq) use ($code) {
                $cq->where('i.item_code', 'like', "%{$code}%")
                    ->orWhere('i.ean_upc_code', 'like', "%{$code}%");
            });
        }

        $ignoreReturnId = $request->integer('ignore_return_id');

        $alreadyReturnedByItem = [];
        if (! empty($invoiceId)) {
            $alreadyReturnedByItem = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pr.purchase_invoice_id', $invoiceId)
                ->when($ignoreReturnId, fn ($q) => $q->where('pr.id', '!=', $ignoreReturnId))
                ->groupBy('pri.item_id')
                ->select('pri.item_id', DB::raw('SUM(pri.qty) as returned_qty'))
                ->pluck('returned_qty', 'item_id')
                ->all();
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
            $invoicedQty = $row->invoiced_qty !== null ? (float) $row->invoiced_qty : null;
            $alreadyReturned = !empty($invoiceId) ? (float) ($alreadyReturnedByItem[$row->id] ?? 0) : 0;
            $remainingQty = $invoicedQty !== null ? max(0, round($invoicedQty - $alreadyReturned, 4)) : null;

            $result[] = [
                'id'               => (int) $row->id,
                'name'             => $row->name,
                'code'             => ($row->item_code ?? '') ?: (($row->ean_upc_code ?? '') ?: ''),
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
            'source' => !empty($invoiceId) ? 'invoice' : 'supplier',
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
                $originalCost = PurchaseInvoiceItem::where('purchase_invoice_id', $purchaseReturn->purchase_invoice_id)
                    ->where('item_id', $itemLine->item_id)
                    ->value('cost_price');
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
            );

            $itemLine->update(['cost_at_return' => $ledgerRow->unit_cost]);
        }
    }

    private function nextNumber(): string
    {
        $next = (int) (PurchaseReturn::max('id') ?? 0);
        $lastRet = PurchaseReturn::where('return_number', 'like', 'PRN%')->orderByDesc('id')->value('return_number');
        if ($lastRet && preg_match('/^PRN(\d+)$/', $lastRet, $matches)) {
            $next = max($next, (int) $matches[1]);
        }
        do {
            $next++;
            $prn = 'PRN'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        } while (PurchaseReturn::where('return_number', $prn)->exists());

        return $prn;
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

            return [
                'item_id' => $line['item_id'],
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
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.disc_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.disc_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Server-side strict boundary checks
        if (!empty($header['purchase_invoice_id'])) {
            $invoiceId = (int) $header['purchase_invoice_id'];
            $invoiceItems = DB::table('purchase_invoice_items')
                ->where('purchase_invoice_id', $invoiceId)
                ->groupBy('item_id')
                ->select('item_id', DB::raw('SUM(qty) as original_qty'))
                ->pluck('original_qty', 'item_id')
                ->all();

            $validItemIds = array_keys($invoiceItems);

            // Group requested return quantities by item_id
            $totalQtyByItem = [];
            foreach ($validated['items'] as $line) {
                $itemId = (int) $line['item_id'];
                if (!in_array($itemId, $validItemIds)) {
                    $itemModel = Item::find($itemId);
                    $name = $itemModel?->name ?? "Item #{$itemId}";
                    throw ValidationException::withMessages([
                        'items' => "The item '{$name}' does not belong to the selected Purchase Invoice.",
                    ]);
                }
                $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0.0) + (float) $line['qty'];
            }

            // Calculate already returned quantities (excluding current return if editing)
            $currentReturnId = $request->route('purchase_return') 
                ? (is_object($request->route('purchase_return')) ? $request->route('purchase_return')->id : (int)$request->route('purchase_return')) 
                : null;

            $alreadyReturnedByItem = DB::table('purchase_return_items as pri')
                ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
                ->where('pr.purchase_invoice_id', $invoiceId)
                ->when($currentReturnId, fn ($q) => $q->where('pr.id', '!=', $currentReturnId))
                ->whereIn('pri.item_id', array_keys($totalQtyByItem))
                ->groupBy('pri.item_id')
                ->select('pri.item_id', DB::raw('SUM(pri.qty) as returned_qty'))
                ->pluck('returned_qty', 'item_id')
                ->all();

            foreach ($totalQtyByItem as $itemId => $requestedQty) {
                $originalQty = (float) ($invoiceItems[$itemId] ?? 0);
                $alreadyReturned = (float) ($alreadyReturnedByItem[$itemId] ?? 0);
                $remaining = max(0, round($originalQty - $alreadyReturned, 4));

                if (round($requestedQty, 4) > round($remaining, 4)) {
                    $remainingDisplay = ($remaining == (int)$remaining) ? (int)$remaining : $remaining;
                    if ($alreadyReturned > 0) {
                        throw ValidationException::withMessages([
                            'items' => "Return quantity cannot exceed the remaining returnable quantity of {$remainingDisplay}.",
                        ]);
                    } else {
                        throw ValidationException::withMessages([
                            'items' => "Return quantity cannot be greater than the available purchase quantity.",
                        ]);
                    }
                }
            }
        } elseif (!empty($header['supplier_id'])) {
            $supplierId = $header['supplier_id'];
            foreach ($validated['items'] as $line) {
                $belongsToSupplier = DB::table('items')
                    ->where('id', $line['item_id'])
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

                if (!$belongsToSupplier) {
                    $itemModel = Item::find($line['item_id']);
                    $name = $itemModel?->name ?? "Item #{$line['item_id']}";
                    throw ValidationException::withMessages([
                        'items' => "The item '{$name}' does not belong to the selected Supplier.",
                    ]);
                }
            }
        }

        return ['header' => $header, 'items' => $validated['items']];
    }

    private function assertStockAvailable(array $items, int $branchId): void
    {
        $totalQtyByItem = [];
        foreach ($items as $line) {
            $itemId = (int) $line['item_id'];
            $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0.0) + (float) $line['qty'];
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
    }
}
