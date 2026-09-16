<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptNote;
use App\Models\Supplier;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReceiptNoteController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private AuditLogger $auditLogger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = PurchaseReceiptNote::with(['supplier', 'branch', 'purchaseOrder', 'purchaseInvoice', 'createdBy']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('supplier_challan_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('purchaseOrder', function ($pq) use ($search) {
                        $pq->where('po_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('receipt_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('receipt_date', '<=', $request->date_to);
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

        $receiptNotes = $query->latest('receipt_date')->latest('id')->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $suppliers = Supplier::where('status', true)->orderBy('name')->pluck('name', 'id');
        $statuses = ['Received', 'Invoiced', 'Cancelled'];

        return view('purchase.receipt-notes.index', compact('receiptNotes', 'branches', 'suppliers', 'statuses'));
    }

    public function create(Request $request)
    {
        $options = $this->formOptions();
        $sourceOrder = null;
        $convertedItems = collect();

        if ($request->filled('from_po')) {
            $sourceOrder = PurchaseOrder::with(['items.item', 'supplier', 'branch'])->findOrFail($request->from_po);
            if ($sourceOrder->status === 'Cancelled') {
                return redirect()->route('purchase.purchase-orders.index')
                    ->withErrors(['purchase_order' => "Cannot create receipt note from cancelled PO {$sourceOrder->po_number}."]);
            }

            $convertedItems = $sourceOrder->items->map(function ($poItem) {
                $pendingQty = max(0, (float) $poItem->qty - (float) ($poItem->received_qty ?? 0));
                return [
                    'purchase_order_item_id' => $poItem->id,
                    'item_id' => $poItem->item_id,
                    'item' => $poItem->item,
                    'ordered_qty' => (float) $poItem->qty,
                    'pending_qty' => $pendingQty,
                    'received_qty' => $pendingQty,
                    'accepted_qty' => $pendingQty,
                    'rejected_qty' => 0,
                    'unit_cost' => (float) $poItem->cost_price,
                    'mrp' => (float) ($poItem->mrp ?? $poItem->item->mrp ?? 0),
                    'batch_no' => '',
                    'exp_date' => null,
                    'remarks' => '',
                ];
            })->filter(fn ($line) => $line['pending_qty'] > 0)->values();
        }

        return view('purchase.receipt-notes.create', array_merge($options, [
            'sourceOrder' => $sourceOrder,
            'convertedItems' => $convertedItems,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['receipt_date']);

        if (!empty($data['header']['posting_key'])) {
            $existing = PurchaseReceiptNote::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('purchase.purchase-receipt-notes.index')
                    ->with('status', "Receipt Note {$existing->receipt_number} already recorded.");
            }
        }

        $receiptNote = DB::transaction(function () use ($data, $request) {
            $itemsData = $data['items'];

            $totOrdered = 0;
            $totReceived = 0;
            $totAccepted = 0;
            $totRejected = 0;
            $totAmount = 0;

            foreach ($itemsData as $line) {
                $totOrdered += (float) ($line['ordered_qty'] ?? 0);
                $totReceived += (float) ($line['received_qty'] ?? 0);
                $totAccepted += (float) ($line['accepted_qty'] ?? 0);
                $totRejected += (float) ($line['rejected_qty'] ?? 0);
                $totAmount += round((float) ($line['accepted_qty'] ?? 0) * (float) ($line['unit_cost'] ?? 0), 2);
            }

            $receiptNumber = $this->nextNumber();

            $receiptNote = PurchaseReceiptNote::create(array_merge($data['header'], [
                'receipt_number' => $receiptNumber,
                'total_ordered_qty' => $totOrdered,
                'total_received_qty' => $totReceived,
                'total_accepted_qty' => $totAccepted,
                'total_rejected_qty' => $totRejected,
                'total_amount' => $totAmount,
                'status' => 'Received',
                'created_by_id' => $request->user()?->id,
            ]));

            // Create items & post to stock ledger
            foreach ($itemsData as $line) {
                $acceptedQty = (float) ($line['accepted_qty'] ?? 0);
                $unitCost = (float) ($line['unit_cost'] ?? 0);

                $rnItem = $receiptNote->items()->create([
                    'purchase_order_item_id' => $line['purchase_order_item_id'] ?? null,
                    'item_id' => $line['item_id'],
                    'ordered_qty' => (float) ($line['ordered_qty'] ?? 0),
                    'received_qty' => (float) ($line['received_qty'] ?? 0),
                    'accepted_qty' => $acceptedQty,
                    'rejected_qty' => (float) ($line['rejected_qty'] ?? 0),
                    'unit_cost' => $unitCost,
                    'mrp' => !empty($line['mrp']) ? (float) $line['mrp'] : null,
                    'batch_no' => $line['batch_no'] ?? null,
                    'exp_date' => $line['exp_date'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ]);

                // Physical stock inward: only post if accepted_qty > 0
                if ($acceptedQty > 0) {
                    $this->stockLedger->post(
                        itemId: (int) $line['item_id'],
                        branchId: (int) $receiptNote->branch_id,
                        movementType: 'PURCHASE_RECEIPT',
                        qtyDelta: $acceptedQty,
                        unitCost: $unitCost,
                        referenceType: PurchaseReceiptNote::class,
                        referenceId: $receiptNote->id,
                        documentDate: $receiptNote->receipt_date->toDateString(),
                        userId: $request->user()?->id,
                        expDate: $line['exp_date'] ?? null,
                    );
                }

                // If linked to PO line, update received_qty
                if (!empty($line['purchase_order_item_id'])) {
                    $poItem = PurchaseOrderItem::find($line['purchase_order_item_id']);
                    if ($poItem) {
                        $poItem->increment('received_qty', $acceptedQty);
                    }
                }
            }

            // If linked to PO, re-evaluate PO status
            if ($receiptNote->purchase_order_id) {
                $po = PurchaseOrder::with('items')->find($receiptNote->purchase_order_id);
                if ($po && $po->status !== 'Cancelled') {
                    $allReceived = $po->items->every(fn ($item) => (float) ($item->received_qty ?? 0) >= (float) $item->qty);
                    $po->update(['status' => $allReceived ? 'Closed' : 'Open']);
                }
            }

            $this->auditLogger->log('create', $receiptNote, [], $receiptNote->toArray(), 'Goods Receipt Note created');

            return $receiptNote;
        });

        return redirect()->route('purchase.purchase-receipt-notes.show', $receiptNote)
            ->with('status', "Receipt Note {$receiptNote->receipt_number} created and inventory updated.");
    }

    public function show(PurchaseReceiptNote $purchaseReceiptNote)
    {
        $purchaseReceiptNote->load(['supplier', 'branch', 'purchaseOrder', 'purchaseInvoice', 'items.item', 'createdBy', 'cancelledBy']);

        return view('purchase.receipt-notes.show', compact('purchaseReceiptNote'));
    }

    public function print(PurchaseReceiptNote $purchaseReceiptNote)
    {
        $purchaseReceiptNote->load(['supplier', 'branch', 'purchaseOrder', 'items.item']);

        return view('purchase.receipt-notes.print', compact('purchaseReceiptNote'));
    }

    public function destroy(Request $request, PurchaseReceiptNote $purchaseReceiptNote)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($purchaseReceiptNote->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'receipt_note' => "Receipt Note {$purchaseReceiptNote->receipt_number} is already cancelled.",
            ]);
        }

        if ($purchaseReceiptNote->status === 'Invoiced' || $purchaseReceiptNote->purchase_invoice_id) {
            throw ValidationException::withMessages([
                'receipt_note' => "Cannot cancel Receipt Note {$purchaseReceiptNote->receipt_number} because a Purchase Invoice has already been raised against it.",
            ]);
        }

        $oldValues = $purchaseReceiptNote->only(['status']);

        DB::transaction(function () use ($purchaseReceiptNote, $data, $request, $oldValues) {
            // Reverse stock from StockLedgerService
            $this->stockLedger->reverseByReference(PurchaseReceiptNote::class, $purchaseReceiptNote->id);

            // Revert PO received quantities if linked
            $purchaseReceiptNote->load('items');
            foreach ($purchaseReceiptNote->items as $rnItem) {
                if ($rnItem->purchase_order_item_id && (float) $rnItem->accepted_qty > 0) {
                    $poItem = PurchaseOrderItem::find($rnItem->purchase_order_item_id);
                    if ($poItem) {
                        $newRecQty = max(0, (float) $poItem->received_qty - (float) $rnItem->accepted_qty);
                        $poItem->update(['received_qty' => $newRecQty]);
                    }
                }
            }

            if ($purchaseReceiptNote->purchase_order_id) {
                $po = PurchaseOrder::with('items')->find($purchaseReceiptNote->purchase_order_id);
                if ($po && $po->status !== 'Cancelled') {
                    $allReceived = $po->items->every(fn ($item) => (float) ($item->received_qty ?? 0) >= (float) $item->qty);
                    $po->update(['status' => $allReceived ? 'Closed' : 'Open']);
                }
            }

            $purchaseReceiptNote->update([
                'status' => 'Cancelled',
                'cancellation_reason' => $data['reason'],
                'cancelled_at' => now(),
                'cancelled_by_id' => $request->user()?->id,
            ]);

            $this->auditLogger->log('cancel', $purchaseReceiptNote, $oldValues, ['status' => 'Cancelled'], $data['reason']);
        });

        return redirect()->route('purchase.purchase-receipt-notes.show', $purchaseReceiptNote)
            ->with('status', "Receipt Note {$purchaseReceiptNote->receipt_number} has been cancelled and stock reversed.");
    }

    private function nextNumber(): string
    {
        $next = (PurchaseReceiptNote::max('id') ?? 0) + 1;

        return 'GRN'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'suppliers' => Supplier::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items' => Item::where('status', true)->orderBy('name')->get(['id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp']),
            'purchaseOrders' => PurchaseOrder::whereNotIn('status', ['Cancelled', 'Closed'])->latest('po_date')->pluck('po_number', 'id'),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'receipt_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_challan_no' => ['nullable', 'string', 'max:100'],
            'supplier_challan_date' => ['nullable', 'date'],
            'vehicle_no' => ['nullable', 'string', 'max:50'],
            'transporter_name' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string'],
        ]);

        $items = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.ordered_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.accepted_qty' => ['required', 'numeric', 'min:0'],
            'items.*.rejected_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ])['items'];

        return [
            'header' => $header,
            'items' => $items,
        ];
    }
}
