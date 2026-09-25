<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Services\Audit\AuditLogger;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function __construct(
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'branch'])->latest('order_date');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('order_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->input('date_to'));
        }

        $branchFilter = $request->has('branch_id')
            ? $request->input('branch_id')
            : session('active_branch_id', auth()->user()?->branch_id);

        if (!empty($branchFilter) && $branchFilter !== 'all') {
            $query->where('branch_id', $branchFilter);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);
        $statuses = ['Open', 'Partially Fulfilled', 'Converted', 'Cancelled'];

        return view('sales.sales-orders.index', compact('orders', 'branches', 'customers', 'statuses'));
    }

    public function create(Request $request)
    {
        $options = $this->formOptions();

        if ($request->filled('from_quotation')) {
            $quotation = SalesQuotation::with(['items.item.gstTax', 'customer', 'branch'])
                ->findOrFail($request->input('from_quotation'));
            $options['sourceQuotation'] = $quotation;
            $options['convertedItems'] = $quotation->items;
            // merge in converted item ids so the _item-row can resolve names
            $convertedItemIds = $quotation->items->pluck('item_id')->filter()->unique();
            if ($convertedItemIds->isNotEmpty()) {
                $options['items'] = Item::whereIn('id', $convertedItemIds)->with('gstTax:id,percentage')->get([
                    'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id',
                ]);
            }
        }

        return view('sales.sales-orders.create', $options);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $order = DB::transaction(function () use ($data, $request) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $order = SalesOrder::create(array_merge($data['header'], $totals, [
                'order_number' => $this->nextNumber(),
            ]));

            $order->items()->createMany($lines);

            if ($request->filled('from_quotation_id')) {
                SalesQuotation::where('id', $request->input('from_quotation_id'))
                    ->update(['status' => 'Accepted']);
            }

            return $order;
        });

        return redirect()->route('sales.sales-orders.show', $order)
            ->with('status', "Sales Order {$order->order_number} created successfully.");
    }

    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load(['customer', 'branch', 'items.item.gstTax', 'salesBill']);

        return view('sales.sales-orders.show', compact('salesOrder'));
    }

    public function edit(SalesOrder $salesOrder)
    {
        if (in_array($salesOrder->status, ['Converted', 'Cancelled'])) {
            throw ValidationException::withMessages([
                'status' => "Cannot edit sales order {$salesOrder->order_number} because its status is {$salesOrder->status}.",
            ]);
        }

        $salesOrder->load('items');

        return view('sales.sales-orders.edit', array_merge(['salesOrder' => $salesOrder], $this->formOptions($salesOrder)));
    }

    public function update(Request $request, SalesOrder $salesOrder)
    {
        if (in_array($salesOrder->status, ['Converted', 'Cancelled'])) {
            throw ValidationException::withMessages([
                'status' => "Cannot edit sales order {$salesOrder->order_number} because its status is {$salesOrder->status}.",
            ]);
        }

        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $salesOrder) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $salesOrder->update(array_merge($data['header'], $totals));
            $salesOrder->items()->delete();
            $salesOrder->items()->createMany($lines);
        });

        return redirect()->route('sales.sales-orders.show', $salesOrder)
            ->with('status', "Sales Order {$salesOrder->order_number} updated successfully.");
    }

    public function destroy(Request $request, SalesOrder $salesOrder)
    {
        if ($salesOrder->status === 'Converted') {
            throw ValidationException::withMessages([
                'status' => "Cannot cancel sales order {$salesOrder->order_number} as it has already been converted to a sales bill.",
            ]);
        }

        $oldValues = $salesOrder->only(['status']);
        $salesOrder->update(['status' => 'Cancelled']);

        $this->auditLogger->log('cancel', $salesOrder, $oldValues, ['status' => 'Cancelled'], $request->input('reason', 'Sales order cancelled'));

        return redirect()->route('sales.sales-orders.index')
            ->with('status', "Sales Order {$salesOrder->order_number} cancelled.");
    }

    private function nextNumber(): string
    {
        $branchId = session('active_branch_id', auth()->user()?->branch_id);

        return app(\App\Services\Accounting\DocumentNumberingService::class)->generate(
            'sales_order',
            $branchId ? (int) $branchId : null
        );
    }

    private function formOptions(?SalesOrder $salesOrder = null, $convertedItems = null): array
    {
        // Only load items already in this order (for edit/reload) — never load the full catalogue.
        $oldItems    = old('items');
        $oldItemIds  = is_array($oldItems) ? collect($oldItems)->pluck('item_id')->filter() : collect();
        $existingIds = collect($salesOrder?->items ?? ($convertedItems ?? []))->pluck('item_id')->merge($oldItemIds)->filter()->unique();
        $items       = $existingIds->isNotEmpty()
            ? Item::whereIn('id', $existingIds)->with('gstTax:id,percentage')->get([
                'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id',
              ])
            : collect();

        $selectedCustId = old('customer_id', old('header.customer_id', $salesOrder?->customer_id));
        $customers = Customer::where('status', true)->orderBy('name')->limit(20)
            ->get(['id', 'name', 'mobile'])
            ->mapWithKeys(fn ($c) => [$c->id => $c->mobile ? "{$c->name} ({$c->mobile})" : $c->name]);
        if ($selectedCustId && ! isset($customers[$selectedCustId])) {
            $sel = Customer::find($selectedCustId);
            if ($sel) $customers->put($sel->id, $sel->mobile ? "{$sel->name} ({$sel->mobile})" : $sel->name);
        }

        return [
            'customers' => $customers,
            'branches'  => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items'     => $items,
        ];
    }

    private function computeLines(array $items, array $header): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');
        $isInterstate = ($header['sales_type'] ?? null) === 'Interstate';

        return collect($items)->map(function ($line) use ($itemsById, $isInterstate) {
            $qty = (float) $line['qty'];
            $sellPrice = (float) $line['sell_price'];
            $item = $itemsById[$line['item_id']];

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);

            $tax = $this->taxEngine->calculate($qty, $sellPrice, $item, $discPercent, $discAmount, 0.0, $isInterstate);

            return [
                'item_id' => $line['item_id'],
                'qty' => $qty,
                'sell_price' => $sellPrice,
                'mrp' => (float) ($line['mrp'] ?? 0),
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

        $advanceAmount = (float) ($data['header']['advance_amount'] ?? 0);

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total_cgst' => $collection->sum('cgst_amount'),
            'total_sgst' => $collection->sum('sgst_amount'),
            'total_igst' => $collection->sum('igst_amount'),
            'total' => round($collection->sum('net_amount') + $roundOff, 2),
            'round_off' => $roundOff,
            'advance_amount' => $advanceAmount,
        ];
    }

    private function validateData(Request $request): array
    {
        $headerRules = [
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'advance_amount' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', 'in:Open,Partially Fulfilled,Converted,Cancelled'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('sales_orders', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

        $header['order_date'] = $this->normalizeDate($header['order_date']);
        if (!empty($header['expected_delivery_date'])) {
            $header['expected_delivery_date'] = $this->normalizeDate($header['expected_delivery_date']);
        }
        $header['advance_amount'] = (float) ($header['advance_amount'] ?? 0);
        $header['status'] = $header['status'] ?? 'Open';

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.sell_price' => ['required', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ['header' => $header, 'items' => $validated['items']];
    }
}
