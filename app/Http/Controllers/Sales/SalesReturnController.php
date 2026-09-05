<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Models\SalesReturn;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController extends Controller
{
    public function __construct(private LedgerPostingService $ledgerPosting)
    {
    }

    public function index()
    {
        $salesReturns = SalesReturn::with(['customer', 'branch'])->latest('return_date')->paginate(20);

        return view('sales.sales-returns.index', compact('salesReturns'));
    }

    public function create()
    {
        return view('sales.sales-returns.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $salesReturn = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);
            $totals = $this->computeTotals($lines, $data);

            $salesReturn = SalesReturn::create(array_merge($data['header'], $totals, [
                'return_number' => $this->nextNumber(),
            ]));

            $salesReturn->items()->createMany($lines);
            $this->applyStock($lines, $salesReturn->branch_id, +1);
            $this->ledgerPosting->postSalesReturn($salesReturn);

            return $salesReturn;
        });

        return redirect()->route('sales.sales-returns.index')->with('status', "Sales Return {$salesReturn->return_number} created successfully.");
    }

    public function edit(SalesReturn $salesReturn)
    {
        $salesReturn->load('items');

        return view('sales.sales-returns.edit', array_merge(['salesReturn' => $salesReturn], $this->formOptions()));
    }

    public function update(Request $request, SalesReturn $salesReturn)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $salesReturn) {
            $oldLines = $salesReturn->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($oldLines, $salesReturn->branch_id, -1);

            $lines = $this->computeLines($data['items']);
            $totals = $this->computeTotals($lines, $data);

            $salesReturn->update(array_merge($data['header'], $totals));
            $salesReturn->items()->delete();
            $salesReturn->items()->createMany($lines);

            $this->applyStock($lines, $salesReturn->branch_id, +1);
            $this->ledgerPosting->postSalesReturn($salesReturn);
        });

        return redirect()->route('sales.sales-returns.index')->with('status', "Sales Return {$salesReturn->return_number} updated successfully.");
    }

    public function destroy(SalesReturn $salesReturn)
    {
        DB::transaction(function () use ($salesReturn) {
            $lines = $salesReturn->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($lines, $salesReturn->branch_id, -1);
            $this->ledgerPosting->reverse(SalesReturn::class, $salesReturn->id);
            $salesReturn->delete();
        });

        return redirect()->route('sales.sales-returns.index')->with('status', 'Sales Return deleted and stock reversed.');
    }

    /**
     * $direction +1 restores returned stock (create), -1 reverses it (edit-reverse/delete).
     */
    private function applyStock(array $lines, int $branchId, int $direction): void
    {
        foreach ($lines as $line) {
            ItemStock::adjust($line['item_id'], $branchId, (float) $line['qty'] * $direction);
        }
    }

    private function nextNumber(): string
    {
        $next = (SalesReturn::max('id') ?? 0) + 1;

        return 'SRN'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
            'salesBills' => SalesBill::orderBy('bill_number')->pluck('bill_number', 'id'),
        ];
    }

    private function computeLines(array $items): array
    {
        return collect($items)->map(function ($line) {
            $qty = (float) $line['qty'];
            $sellPrice = (float) $line['sell_price'];
            $base = $qty * $sellPrice;

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
                'sell_price' => $sellPrice,
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
        $roundOff = (float) ($data['header']['round_off'] ?? 0);
        $totalExtraCess = (float) ($data['header']['total_extra_cess'] ?? 0);
        $gstCalamityCess = (float) ($data['header']['gst_calamity_cess'] ?? 0);

        return [
            'item_disc_amount' => $collection->sum('disc_amount'),
            'disc_amount' => $collection->sum('disc_amount'),
            'total_gst' => $collection->sum('gst_tax_amount'),
            'total' => round($collection->sum('net_amount') + $roundOff + $totalExtraCess + $gstCalamityCess, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'return_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'sales_bill_id' => ['nullable', 'exists:sales_bills,id'],
            'return_mode' => ['required', 'in:RRN,Credit Note,Cash,Wallet,Card'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'round_off' => ['nullable', 'numeric'],
            'total_extra_cess' => ['nullable', 'numeric', 'min:0'],
            'gst_calamity_cess' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $header['return_date'] = $this->normalizeDate($header['return_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
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
