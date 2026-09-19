<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesQuotation;
use App\Services\Audit\AuditLogger;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQuotationController extends Controller
{
    public function __construct(
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request)
    {
        $query = SalesQuotation::with(['customer', 'branch'])->latest('quotation_date');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('quotation_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', function ($cq) use ($term) {
                        $cq->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('quotation_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('quotation_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $quotations = $query->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $customers = Customer::where('status', true)->orderBy('name')->pluck('name', 'id');
        $statuses = ['Draft', 'Sent', 'Accepted', 'Converted', 'Cancelled'];

        return view('sales.sales-quotations.index', compact('quotations', 'branches', 'customers', 'statuses'));
    }

    public function create()
    {
        return view('sales.sales-quotations.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $quotation = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $quotation = SalesQuotation::create(array_merge($data['header'], $totals, [
                'quotation_number' => $this->nextNumber(),
            ]));

            $quotation->items()->createMany($lines);

            return $quotation;
        });

        return redirect()->route('sales.sales-quotations.show', $quotation)
            ->with('status', "Quotation {$quotation->quotation_number} created successfully.");
    }

    public function show(SalesQuotation $salesQuotation)
    {
        $salesQuotation->load(['customer', 'branch', 'items.item.gstTax', 'salesBill']);

        return view('sales.sales-quotations.show', compact('salesQuotation'));
    }

    public function edit(SalesQuotation $salesQuotation)
    {
        if (in_array($salesQuotation->status, ['Converted', 'Cancelled'])) {
            throw ValidationException::withMessages([
                'status' => "Cannot edit quotation {$salesQuotation->quotation_number} because its status is {$salesQuotation->status}.",
            ]);
        }

        $salesQuotation->load('items');

        return view('sales.sales-quotations.edit', array_merge(['salesQuotation' => $salesQuotation], $this->formOptions()));
    }

    public function update(Request $request, SalesQuotation $salesQuotation)
    {
        if (in_array($salesQuotation->status, ['Converted', 'Cancelled'])) {
            throw ValidationException::withMessages([
                'status' => "Cannot edit quotation {$salesQuotation->quotation_number} because its status is {$salesQuotation->status}.",
            ]);
        }

        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $salesQuotation) {
            $lines = $this->computeLines($data['items'], $data['header']);
            $totals = $this->computeTotals($lines, $data);

            $salesQuotation->update(array_merge($data['header'], $totals));
            $salesQuotation->items()->delete();
            $salesQuotation->items()->createMany($lines);
        });

        return redirect()->route('sales.sales-quotations.show', $salesQuotation)
            ->with('status', "Quotation {$salesQuotation->quotation_number} updated successfully.");
    }

    public function destroy(Request $request, SalesQuotation $salesQuotation)
    {
        if ($salesQuotation->status === 'Converted') {
            throw ValidationException::withMessages([
                'status' => "Cannot cancel quotation {$salesQuotation->quotation_number} as it has already been converted to a sales bill.",
            ]);
        }

        $oldValues = $salesQuotation->only(['status']);
        $salesQuotation->update(['status' => 'Cancelled']);

        $this->auditLogger->log('cancel', $salesQuotation, $oldValues, ['status' => 'Cancelled'], $request->input('reason', 'Quotation cancelled'));

        return redirect()->route('sales.sales-quotations.index')
            ->with('status', "Quotation {$salesQuotation->quotation_number} cancelled.");
    }

    private function nextNumber(): string
    {
        $next = (SalesQuotation::max('id') ?? 0) + 1;

        return 'SQ'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        $items = Item::where('status', true)->with('gstTax:id,percentage')->orderBy('name')->get([
            'id', 'name', 'item_code', 'ean_upc_code', 'cost_price', 'sell_price', 'mrp', 'gst_tax_id'
        ]);

        return [
            'customers' => Customer::options(),
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'items' => $items,
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
            'quotation_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'round_off' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', 'in:Draft,Sent,Accepted,Converted,Cancelled'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('sales_quotations', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

        $header['quotation_date'] = $this->normalizeDate($header['quotation_date']);
        if (!empty($header['valid_until'])) {
            $header['valid_until'] = $this->normalizeDate($header['valid_until']);
        }
        $header['status'] = $header['status'] ?? 'Draft';

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
