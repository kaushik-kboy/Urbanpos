<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
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

    public function create()
    {
        return view('purchase.purchase-invoices.create', $this->formOptions());
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

            $this->postStockAndItemMaster($purchaseInvoice, $lines);
            $this->ledgerPosting->postPurchaseInvoice($purchaseInvoice);

            return $purchaseInvoice;
        });

        return redirect()->route('purchase.purchase-invoices.index')->with('status', "Purchase Invoice {$purchaseInvoice->invoice_number} created successfully.");
    }

    public function edit(PurchaseInvoice $purchaseInvoice)
    {
        $purchaseInvoice->load('items');

        return view('purchase.purchase-invoices.edit', array_merge(['purchaseInvoice' => $purchaseInvoice], $this->formOptions()));
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

    public function lookupItem(Request $request)
    {
        $query = trim($request->input('query', ''));
        if ($query === '') {
            return response()->json(null);
        }

        $item = Item::with('gstTax:id,percentage')
            ->where('item_code', $query)
            ->orWhere('ean_upc_code', $query)
            ->orWhere('name', 'like', "%{$query}%")
            ->first();

        if (! $item) {
            return response()->json(null);
        }

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

    private function formOptions(): array
    {
        $items = Item::with('gstTax:id,percentage')->orderBy('name')->get([
            'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id',
            'batch_expiry_details', 'shelf_life_days', 'minimum_shelf_life_days'
        ]);

        return [
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => $items,
            'purchaseOrders' => PurchaseOrder::orderBy('po_number')->pluck('po_number', 'id'),
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
            $base = $qty * $costPrice;

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);

            // Synchronize discount percentage and amount
            if ($discAmount <= 0 && $discPercent > 0 && $base > 0) {
                $discAmount = round($base * $discPercent / 100, 2);
            } elseif ($discAmount > 0 && $discPercent <= 0 && $base > 0) {
                $discPercent = round(($discAmount / $base) * 100, 2);
            }

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

            $effectiveDiscPercent = $tax['disc_amount'] > 0 && $base > 0
                ? round(($tax['disc_amount'] / $base) * 100, 2)
                : $discPercent;

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
        $otherDiscAmt = (float) ($data['header']['other_disc_amt'] ?? 0);
        $schemeDiscAmt = (float) ($data['header']['scheme_item_disc_amt'] ?? 0);
        $tcsAmount = (float) ($data['header']['tcs_amount'] ?? 0);

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total_cgst' => $collection->sum('cgst_amount'),
            'total_sgst' => $collection->sum('sgst_amount'),
            'total_igst' => $collection->sum('igst_amount'),
            'total_qty' => $collection->sum('qty') + $collection->sum('free_qty'),
            'total' => round($collection->sum('net_amount') + $freight + $roundOff + $tcsAmount - $otherDiscAmt - $schemeDiscAmt, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'invoice_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
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
            }
        });

        $validated = $validator->validate();

        return ['header' => $header, 'items' => $validated['items']];
    }
}
