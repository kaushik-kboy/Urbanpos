<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceController extends Controller
{
    public function __construct(private LedgerPostingService $ledgerPosting)
    {
    }

    public function index()
    {
        $purchaseInvoices = PurchaseInvoice::with(['supplier', 'branch'])->latest('invoice_date')->paginate(20);

        return view('purchase.purchase-invoices.index', compact('purchaseInvoices'));
    }

    public function create()
    {
        return view('purchase.purchase-invoices.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $purchaseInvoice = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);
            $totals = $this->computeTotals($lines, $data);

            $purchaseInvoice = PurchaseInvoice::create(array_merge($data['header'], $totals, [
                'invoice_number' => $this->nextNumber(),
            ]));

            $purchaseInvoice->items()->createMany($lines);

            $this->applyStockAndItemMaster($lines, $purchaseInvoice->branch_id, +1);
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
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $purchaseInvoice) {
            // Reverse the stock effect of the previous version of this invoice.
            $oldLines = $purchaseInvoice->items->map->only(['item_id', 'qty', 'free_qty'])->all();
            $this->applyStockAndItemMaster($oldLines, $purchaseInvoice->branch_id, -1);

            $lines = $this->computeLines($data['items']);
            $totals = $this->computeTotals($lines, $data);

            $purchaseInvoice->update(array_merge($data['header'], $totals));
            $purchaseInvoice->items()->delete();
            $purchaseInvoice->items()->createMany($lines);

            $this->applyStockAndItemMaster($lines, $purchaseInvoice->branch_id, +1);
            $this->ledgerPosting->postPurchaseInvoice($purchaseInvoice);
        });

        return redirect()->route('purchase.purchase-invoices.index')->with('status', "Purchase Invoice {$purchaseInvoice->invoice_number} updated successfully.");
    }

    public function destroy(PurchaseInvoice $purchaseInvoice)
    {
        DB::transaction(function () use ($purchaseInvoice) {
            $lines = $purchaseInvoice->items->map->only(['item_id', 'qty', 'free_qty'])->all();
            $this->applyStockAndItemMaster($lines, $purchaseInvoice->branch_id, -1);
            $this->ledgerPosting->reverse(PurchaseInvoice::class, $purchaseInvoice->id);
            $purchaseInvoice->delete();
        });

        return redirect()->route('purchase.purchase-invoices.index')->with('status', 'Purchase Invoice deleted and stock reversed.');
    }

    /**
     * $direction +1 adds the line quantities/master values (on create), -1 reverses only
     * the stock quantity (on edit/delete) — item master price fields are a snapshot and
     * are only ever overwritten forward, never reversed.
     */
    private function applyStockAndItemMaster(array $lines, int $branchId, int $direction): void
    {
        foreach ($lines as $line) {
            $qtyDelta = ((float) $line['qty'] + (float) $line['free_qty']) * $direction;
            ItemStock::adjust($line['item_id'], $branchId, $qtyDelta);

            if ($direction > 0 && isset($line['cost_price'])) {
                Item::whereKey($line['item_id'])->update(array_filter([
                    'cost_price' => $line['cost_price'],
                    'sell_price' => $line['sell_price'] ?: null,
                    'mrp' => $line['mrp'] ?: null,
                ], fn ($v) => $v !== null));
            }
        }
    }

    private function nextNumber(): string
    {
        $next = (PurchaseInvoice::max('id') ?? 0) + 1;

        return 'PINV'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
            'purchaseOrders' => PurchaseOrder::orderBy('po_number')->pluck('po_number', 'id'),
        ];
    }

    private function computeLines(array $items): array
    {
        return collect($items)->map(function ($line) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $base = $qty * $costPrice;

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);
            if ($discAmount <= 0 && $discPercent > 0) {
                $discAmount = round($base * $discPercent / 100, 2);
            }

            $gstPercent = (float) ($line['gst_percent'] ?? 0);
            $gstTaxAmount = round(($base - $discAmount) * $gstPercent / 100, 2);
            $netAmount = round(($base - $discAmount) + $gstTaxAmount, 2);

            return [
                'item_id' => $line['item_id'],
                'exp_date' => $this->normalizeDate($line['exp_date'] ?: null),
                'qty' => $qty,
                'free_qty' => (float) ($line['free_qty'] ?? 0),
                'cost_price' => $costPrice,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
                'disc_percent' => $discPercent,
                'disc_amount' => $discAmount,
                'gst_percent' => $gstPercent,
                'gst_tax_amount' => $gstTaxAmount,
                'net_amount' => $netAmount,
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
        ]);

        $header['invoice_date'] = $this->normalizeDate($header['invoice_date']);
        $header['grn_date'] = $this->normalizeDate($header['grn_date'] ?? null);
        $header['supplier_inv_date'] = $this->normalizeDate($header['supplier_inv_date'] ?? null);

        $validated = $request->validate([
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
        ]);

        return ['header' => $header, 'items' => $validated['items']];
    }
}
