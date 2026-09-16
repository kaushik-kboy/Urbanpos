<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesDeliveryNote;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesDeliveryNoteController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private AuditLogger $auditLogger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = SalesDeliveryNote::with(['customer', 'branch', 'salesOrder', 'salesBill', 'createdBy']);

        // Scope to branch if user has branch_id assigned and not an Owner
        if ($user && $user->branch_id && !$user->hasRole('Owner')) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('delivery_number', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('transporter_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhere('lr_no', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })
                    ->orWhereHas('salesOrder', function ($oq) use ($search) {
                        $oq->where('order_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('delivery_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('delivery_date', '<=', $request->date_to);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $deliveryNotes = $query->latest('delivery_date')->latest('id')->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $customers = Customer::where('status', true)->orderBy('name')->pluck('name', 'id');
        $statuses = ['Dispatched', 'Invoiced', 'Cancelled'];

        return view('sales.delivery-notes.index', compact('deliveryNotes', 'branches', 'customers', 'statuses'));
    }

    public function create(Request $request)
    {
        $options = $this->formOptions($request);
        $sourceOrder = null;
        $convertedItems = collect();

        if ($request->filled('from_order')) {
            $sourceOrder = SalesOrder::with(['items.item', 'customer', 'branch'])->findOrFail($request->from_order);
            if ($sourceOrder->status === 'Cancelled') {
                return redirect()->route('sales.sales-orders.index')
                    ->withErrors(['sales_order' => "Cannot create delivery note from cancelled Sales Order {$sourceOrder->order_number}."]);
            }

            $convertedItems = $sourceOrder->items->map(function ($soItem) {
                $pendingQty = max(0, (float) $soItem->qty - (float) ($soItem->dispatched_qty ?? 0));
                return [
                    'sales_order_item_id' => $soItem->id,
                    'item_id' => $soItem->item_id,
                    'item' => $soItem->item,
                    'ordered_qty' => (float) $soItem->qty,
                    'pending_qty' => $pendingQty,
                    'dispatched_qty' => $pendingQty,
                    'unit_price' => (float) $soItem->sell_price,
                    'mrp' => (float) ($soItem->mrp ?? $soItem->item->mrp ?? 0),
                    'batch_no' => '',
                    'exp_date' => null,
                    'remarks' => '',
                ];
            })->filter(fn ($line) => $line['pending_qty'] > 0)->values();
        }

        return view('sales.delivery-notes.create', array_merge($options, [
            'sourceOrder' => $sourceOrder,
            'convertedItems' => $convertedItems,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['delivery_date']);

        if (!empty($data['header']['posting_key'])) {
            $existing = SalesDeliveryNote::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('sales.delivery-notes.index')
                    ->with('status', "Delivery Note {$existing->delivery_number} already recorded.");
            }
        }

        // Check stock availability
        $branchId = (int) $data['header']['branch_id'];
        foreach ($data['items'] as $line) {
            $dispatchedQty = (float) ($line['dispatched_qty'] ?? 0);
            if ($dispatchedQty > 0) {
                $stock = ItemStock::where('item_id', $line['item_id'])->where('branch_id', $branchId)->first();
                $avail = $stock ? (float) $stock->quantity : 0;
                if ($avail < $dispatchedQty) {
                    $item = Item::find($line['item_id']);
                    $itemName = $item ? $item->name : "Item #{$line['item_id']}";
                    throw ValidationException::withMessages([
                        'items' => "Insufficient stock for '{$itemName}' in this branch. Available: {$avail}, requested dispatch: {$dispatchedQty}.",
                    ]);
                }
            }
        }

        $deliveryNote = DB::transaction(function () use ($data, $request) {
            $itemsData = $data['items'];

            $totOrdered = 0;
            $totDispatched = 0;
            $totAmount = 0;

            foreach ($itemsData as $line) {
                $totOrdered += (float) ($line['ordered_qty'] ?? 0);
                $totDispatched += (float) ($line['dispatched_qty'] ?? 0);
                $totAmount += round((float) ($line['dispatched_qty'] ?? 0) * (float) ($line['unit_price'] ?? 0), 2);
            }

            $deliveryNumber = $this->nextNumber();

            $deliveryNote = SalesDeliveryNote::create(array_merge($data['header'], [
                'delivery_number' => $deliveryNumber,
                'total_ordered_qty' => $totOrdered,
                'total_dispatched_qty' => $totDispatched,
                'total_amount' => $totAmount,
                'status' => 'Dispatched',
                'created_by_id' => $request->user()?->id,
            ]));

            // Deduct physical stock & create items
            foreach ($itemsData as $line) {
                $dispatchedQty = (float) ($line['dispatched_qty'] ?? 0);
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                $costAtDispatch = 0.0;

                if ($dispatchedQty > 0) {
                    $ledgerRow = $this->stockLedger->post(
                        itemId: (int) $line['item_id'],
                        branchId: (int) $deliveryNote->branch_id,
                        movementType: 'SALES_DELIVERY',
                        qtyDelta: -1 * $dispatchedQty,
                        unitCost: null,
                        referenceType: SalesDeliveryNote::class,
                        referenceId: $deliveryNote->id,
                        documentDate: $deliveryNote->delivery_date->toDateString(),
                        userId: $request->user()?->id,
                        expDate: $line['exp_date'] ?? null,
                    );
                    $costAtDispatch = (float) $ledgerRow->unit_cost;
                }

                $dnItem = $deliveryNote->items()->create([
                    'sales_order_item_id' => $line['sales_order_item_id'] ?? null,
                    'item_id' => $line['item_id'],
                    'ordered_qty' => (float) ($line['ordered_qty'] ?? 0),
                    'dispatched_qty' => $dispatchedQty,
                    'unit_price' => $unitPrice,
                    'cost_at_dispatch' => $costAtDispatch,
                    'mrp' => !empty($line['mrp']) ? (float) $line['mrp'] : null,
                    'batch_no' => $line['batch_no'] ?? null,
                    'exp_date' => $line['exp_date'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ]);

                // If linked to SO line, update dispatched_qty
                if (!empty($line['sales_order_item_id'])) {
                    $soItem = SalesOrderItem::find($line['sales_order_item_id']);
                    if ($soItem) {
                        $soItem->increment('dispatched_qty', $dispatchedQty);
                    }
                }
            }

            // If linked to SO, re-evaluate SO status
            if ($deliveryNote->sales_order_id) {
                $so = SalesOrder::with('items')->find($deliveryNote->sales_order_id);
                if ($so && $so->status !== 'Cancelled') {
                    $allDispatched = $so->items->every(fn ($item) => (float) ($item->dispatched_qty ?? 0) >= (float) $item->qty);
                    $anyDispatched = $so->items->some(fn ($item) => (float) ($item->dispatched_qty ?? 0) > 0);
                    $newStatus = $allDispatched ? 'Partially Fulfilled' : ($anyDispatched ? 'Partially Fulfilled' : 'Open');
                    $so->update(['status' => $newStatus]);
                }
            }

            $this->auditLogger->log('create', $deliveryNote, [], $deliveryNote->toArray(), 'Sales Delivery Note created');

            return $deliveryNote;
        });

        return redirect()->route('sales.delivery-notes.show', $deliveryNote)
            ->with('status', "Delivery Note {$deliveryNote->delivery_number} created and goods dispatched.");
    }

    public function show(SalesDeliveryNote $deliveryNote)
    {
        $salesDeliveryNote = $deliveryNote;
        $salesDeliveryNote->load(['customer', 'branch', 'salesOrder', 'salesBill', 'items.item', 'createdBy', 'cancelledBy']);

        return view('sales.delivery-notes.show', compact('salesDeliveryNote'));
    }

    public function print(SalesDeliveryNote $deliveryNote)
    {
        $salesDeliveryNote = $deliveryNote;
        $salesDeliveryNote->load(['customer', 'branch', 'salesOrder', 'items.item']);

        return view('sales.delivery-notes.print', compact('salesDeliveryNote'));
    }

    public function destroy(Request $request, SalesDeliveryNote $deliveryNote)
    {
        $salesDeliveryNote = $deliveryNote;
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($salesDeliveryNote->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'delivery_note' => "Delivery Note {$salesDeliveryNote->delivery_number} is already cancelled.",
            ]);
        }

        if ($salesDeliveryNote->status === 'Invoiced' || $salesDeliveryNote->sales_bill_id) {
            throw ValidationException::withMessages([
                'delivery_note' => "Cannot cancel Delivery Note {$salesDeliveryNote->delivery_number} because a Sales Bill has already been generated against it.",
            ]);
        }

        $oldValues = $salesDeliveryNote->only(['status']);

        DB::transaction(function () use ($salesDeliveryNote, $data, $request, $oldValues) {
            // Reverse stock from StockLedgerService
            $this->stockLedger->reverseByReference(SalesDeliveryNote::class, $salesDeliveryNote->id);

            // Revert SO dispatched quantities if linked
            $salesDeliveryNote->load('items');
            foreach ($salesDeliveryNote->items as $dnItem) {
                if ($dnItem->sales_order_item_id && (float) $dnItem->dispatched_qty > 0) {
                    $soItem = SalesOrderItem::find($dnItem->sales_order_item_id);
                    if ($soItem) {
                        $newDispQty = max(0, (float) $soItem->dispatched_qty - (float) $dnItem->dispatched_qty);
                        $soItem->update(['dispatched_qty' => $newDispQty]);
                    }
                }
            }

            if ($salesDeliveryNote->sales_order_id) {
                $so = SalesOrder::with('items')->find($salesDeliveryNote->sales_order_id);
                if ($so && $so->status !== 'Cancelled') {
                    $anyDispatched = $so->items->some(fn ($item) => (float) ($item->dispatched_qty ?? 0) > 0);
                    $so->update(['status' => $anyDispatched ? 'Partially Fulfilled' : 'Open']);
                }
            }

            $salesDeliveryNote->update([
                'status' => 'Cancelled',
                'cancellation_reason' => $data['reason'],
                'cancelled_at' => now(),
                'cancelled_by_id' => $request->user()?->id,
            ]);

            $this->auditLogger->log('cancel', $salesDeliveryNote, $oldValues, ['status' => 'Cancelled'], $data['reason']);
        });

        return redirect()->route('sales.delivery-notes.show', $salesDeliveryNote)
            ->with('status', "Delivery Note {$salesDeliveryNote->delivery_number} has been cancelled and stock restored.");
    }

    private function nextNumber(): string
    {
        $next = (SalesDeliveryNote::max('id') ?? 0) + 1;

        return 'SDN'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(Request $request): array
    {
        $user = $request->user();
        $branchQuery = Branch::where('status', true)->orderBy('name');
        if ($user && $user->branch_id && !$user->hasRole('Owner')) {
            $branchQuery->where('id', $user->branch_id);
        }

        return [
            'customers' => Customer::options(),
            'branches' => $branchQuery->pluck('name', 'id'),
            'items' => Item::where('status', true)->orderBy('name')->get(['id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp']),
            'salesOrders' => SalesOrder::whereNotIn('status', ['Cancelled', 'Converted'])->latest('order_date')->pluck('order_number', 'id'),
        ];
    }

    private function validateData(Request $request): array
    {
        $user = $request->user();
        $header = $request->validate([
            'delivery_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'transporter_name' => ['nullable', 'string', 'max:100'],
            'vehicle_no' => ['nullable', 'string', 'max:50'],
            'lr_no' => ['nullable', 'string', 'max:100'],
            'lr_date' => ['nullable', 'date'],
            'delivery_address' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string'],
        ]);

        if ($user && $user->branch_id && !$user->hasRole('Owner')) {
            $header['branch_id'] = $user->branch_id;
        }

        $items = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.sales_order_item_id' => ['nullable', 'exists:sales_order_items,id'],
            'items.*.ordered_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.dispatched_qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
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
