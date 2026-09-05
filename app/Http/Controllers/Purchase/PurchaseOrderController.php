<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        $purchaseOrders = PurchaseOrder::with(['supplier', 'branch'])->latest('po_date')->paginate(20);

        return view('purchase.purchase-orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        return view('purchase.purchase-orders.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $purchaseOrder = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);
            $totals = $this->computeTotals($lines, $data);

            $purchaseOrder = PurchaseOrder::create(array_merge($data['header'], $totals, [
                'po_number' => $this->nextNumber(),
            ]));

            $purchaseOrder->items()->createMany($lines);

            return $purchaseOrder;
        });

        return redirect()->route('purchase.purchase-orders.index')->with('status', "Purchase Order {$purchaseOrder->po_number} created successfully.");
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('items');

        return view('purchase.purchase-orders.edit', array_merge(['purchaseOrder' => $purchaseOrder], $this->formOptions()));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $purchaseOrder) {
            $lines = $this->computeLines($data['items']);
            $totals = $this->computeTotals($lines, $data);

            $purchaseOrder->update(array_merge($data['header'], $totals));
            $purchaseOrder->items()->delete();
            $purchaseOrder->items()->createMany($lines);
        });

        return redirect()->route('purchase.purchase-orders.index')->with('status', "Purchase Order {$purchaseOrder->po_number} updated successfully.");
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->delete();

        return redirect()->route('purchase.purchase-orders.index')->with('status', 'Purchase Order deleted.');
    }

    private function nextNumber(): string
    {
        $next = (PurchaseOrder::max('id') ?? 0) + 1;

        return 'PO'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
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

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total_qty' => $collection->sum('qty') + $collection->sum('free_qty'),
            'total' => round($collection->sum('net_amount') + $freight + $roundOff - $otherDiscAmt - $schemeDiscAmt, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'po_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'purchase_type' => ['required', 'in:Local,Interstate'],
            'c_form' => ['required', 'in:Against C-Form,No Forms'],
            'freight' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric'],
            'scheme_item_disc_amt' => ['nullable', 'numeric', 'min:0'],
            'other_disc_amt' => ['nullable', 'numeric', 'min:0'],
            'total_extra_cess' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'status' => ['required', 'in:Open,Closed,Cancelled'],
        ]);

        $header['po_date'] = $this->normalizeDate($header['po_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
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
