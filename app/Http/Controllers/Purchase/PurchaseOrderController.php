<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'branch']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('po_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('po_date', '<=', $request->date_to);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $purchaseOrders = $query->latest('po_date')->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();
        $statuses = PurchaseOrder::select('status')->distinct()->whereNotNull('status')->pluck('status');

        return view('purchase.purchase-orders.index', compact('purchaseOrders', 'branches', 'suppliers', 'statuses'));
    }

    public function create(Request $request)
    {
        $indent = null;
        $initialItems = null;

        if ($request->filled('from_indent')) {
            $indent = \App\Models\PurchaseIndent::with(['items.item', 'branch'])->findOrFail($request->from_indent);
            if ($indent->status !== 'Approved') {
                return redirect()->route('purchase.purchase-indents.show', $indent)
                    ->with('error', 'Only approved indents can be converted to Purchase Orders.');
            }

            $initialItems = $indent->items->map(function ($line) {
                $qty = (float) ($line->approved_qty !== null ? $line->approved_qty : $line->requested_qty);
                $costPrice = (float) ($line->estimated_cost > 0 ? $line->estimated_cost : ($line->item->cost_price ?? 0));

                return [
                    'item_id' => $line->item_id,
                    'qty' => $qty > 0 ? $qty : 1,
                    'free_qty' => 0,
                    'cost_price' => $costPrice,
                    'sell_price' => (float) ($line->item->sell_price ?? 0),
                    'mrp' => (float) ($line->item->mrp ?? 0),
                    'disc_percent' => 0,
                    'disc_amount' => 0,
                    'gst_percent' => (float) ($line->item->tax_rate ?? 0),
                ];
            });
        }

        $options = $this->formOptions(null, $initialItems);

        return view('purchase.purchase-orders.create', array_merge($options, [
            'indent' => $indent,
            'initialItems' => $initialItems,
        ]));
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

            if (!empty($data['header']['purchase_indent_id'])) {
                $indent = \App\Models\PurchaseIndent::find($data['header']['purchase_indent_id']);
                if ($indent && $indent->status === 'Approved') {
                    $indent->update([
                        'status' => 'Converted',
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);
                }
            }

            return $purchaseOrder;
        });

        return redirect()->route('purchase.purchase-orders.index')->with('status', "Purchase Order {$purchaseOrder->po_number} created successfully.");
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'branch', 'items.item']);

        return view('purchase.purchase-orders.show', compact('purchaseOrder'));
    }

    public function print(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['supplier', 'branch', 'items.item']);

        return view('purchase.purchase-orders.print', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load('items.item.gstTax');

        return view('purchase.purchase-orders.edit', array_merge(['purchaseOrder' => $purchaseOrder], $this->formOptions($purchaseOrder)));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'purchase_order' => "Purchase Order {$purchaseOrder->po_number} is cancelled and cannot be edited.",
            ]);
        }

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

    /**
     * PO Cancel — like every other document in this app, this IS the cancel action, not
     * a hard delete: the row stays, status moves to Cancelled with a reason on record.
     */
    public function destroy(Request $request, PurchaseOrder $purchaseOrder)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($purchaseOrder->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'purchase_order' => "Purchase Order {$purchaseOrder->po_number} is already cancelled.",
            ]);
        }

        if ($purchaseOrder->purchaseInvoices()->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => "Cannot cancel Purchase Order {$purchaseOrder->po_number} — it already has Purchase Invoice(s) raised against it.",
            ]);
        }

        $oldValues = $purchaseOrder->only(['status']);

        $purchaseOrder->update([
            'status' => 'Cancelled',
            'cancellation_reason' => $data['reason'],
            'cancelled_at' => now(),
            'cancelled_by_id' => $request->user()->id,
        ]);

        if ($purchaseOrder->purchase_indent_id) {
            $indent = \App\Models\PurchaseIndent::find($purchaseOrder->purchase_indent_id);
            if ($indent && $indent->status === 'Converted') {
                $indent->update([
                    'status' => 'Approved',
                    'purchase_order_id' => null,
                ]);
            }
        }

        $this->auditLogger->log('cancel', $purchaseOrder, $oldValues, ['status' => 'Cancelled'], $data['reason']);

        return redirect()->route('purchase.purchase-orders.index')->with('status', "Purchase Order {$purchaseOrder->po_number} cancelled.");
    }

    public function openBySupplier(Request $request)
    {
        $supplierId = $request->input('supplier_id');
        if (! $supplierId) {
            return response()->json(['purchase_orders' => []]);
        }

        $orders = PurchaseOrder::where('supplier_id', $supplierId)
            ->where('status', 'Open')
            ->orderByDesc('po_date')
            ->get(['id', 'po_number', 'po_date', 'total'])
            ->map(function ($po) {
                return [
                    'id' => $po->id,
                    'po_number' => $po->po_number,
                    'po_date' => $po->po_date ? $po->po_date->format('d-m-Y') : '',
                    'total' => (float) $po->total,
                    'label' => $po->po_number . ($po->po_date ? ' (' . $po->po_date->format('d-m-Y') . ')' : ''),
                ];
            });

        return response()->json(['purchase_orders' => $orders]);
    }

    private function nextNumber(): string
    {
        $branchId = session('active_branch_id', auth()->user()?->branch_id);

        return app(\App\Services\Accounting\DocumentNumberingService::class)->generate(
            'purchase_order',
            $branchId ? (int) $branchId : null
        );
    }

    private function formOptions(?PurchaseOrder $purchaseOrder = null, $initialItems = null): array
    {
        $existingItemIds = collect($purchaseOrder?->items ?? ($initialItems ?? []))->pluck('item_id')->filter()->unique();
        $items = $existingItemIds->isNotEmpty()
            ? Item::whereIn('id', $existingItemIds)->with('gstTax:id,percentage')->get([
                'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id'
            ])
            : collect();

        return [
            'suppliers' => Supplier::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items' => $items,
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
        $freight      = (float) ($data['header']['freight']              ?? 0);
        $roundOff     = (float) ($data['header']['round_off']            ?? 0);
        $otherDiscAmt = (float) ($data['header']['other_disc_amt']       ?? 0);
        $schemeDiscAmt= (float) ($data['header']['scheme_item_disc_amt'] ?? 0);
        $totalExtCess = (float) ($data['header']['total_extra_cess']     ?? 0);
        $totalWeight  = (float) ($data['header']['total_weight']         ?? 0);

        return [
            'item_disc_amount'     => round($collection->sum('disc_amount'), 2),
            'disc_amount'         => round($collection->sum('disc_amount'), 2),
            'total_gst'           => round($collection->sum('gst_tax_amount'), 2),
            'total_qty'           => $collection->sum('qty') + $collection->sum('free_qty'),
            'total'               => round($collection->sum('net_amount') + $freight + $roundOff - $otherDiscAmt - $schemeDiscAmt, 2),
            // Normalize nullable numeric fields to 0 so MySQL strict mode doesn't reject null
            'freight'             => $freight,
            'round_off'           => $roundOff,
            'other_disc_amt'      => $otherDiscAmt,
            'scheme_item_disc_amt'=> $schemeDiscAmt,
            'total_extra_cess'    => $totalExtCess,
            'total_weight'        => $totalWeight,
        ];
    }

    private function validateData(Request $request): array
    {
        $headerRules = [
            'po_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'purchase_indent_id' => ['nullable', 'exists:purchase_indents,id'],
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
            // Cancelled is deliberately excluded here — cancellation only happens through
            // the guarded destroy() action (requires a reason, blocks if already invoiced,
            // writes an audit log), never silently via this form's status dropdown.
            'status' => ['required', 'in:Open,Closed'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('purchase_orders', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

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
