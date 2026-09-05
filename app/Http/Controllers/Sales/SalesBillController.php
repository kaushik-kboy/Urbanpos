<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\SalesBill;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesBillController extends Controller
{
    public function __construct(private LedgerPostingService $ledgerPosting)
    {
    }

    public function index()
    {
        $salesBills = SalesBill::with(['customer', 'branch'])->latest('bill_date')->paginate(20);

        return view('sales.sales-bills.index', compact('salesBills'));
    }

    public function create()
    {
        return view('sales.sales-bills.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $salesBill = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);
            $this->assertStockAvailable($lines, $data['header']['branch_id']);
            $totals = $this->computeTotals($lines, $data);

            $salesBill = SalesBill::create(array_merge($data['header'], $totals, [
                'bill_number' => $this->nextNumber(),
            ]));

            $salesBill->items()->createMany($lines);
            $this->applyStock($lines, $salesBill->branch_id, -1);
            $this->ledgerPosting->postSalesBill($salesBill);

            return $salesBill;
        });

        return redirect()->route('sales.sales-bills.index')->with('status', "Sales Bill {$salesBill->bill_number} created successfully.");
    }

    public function edit(SalesBill $salesBill)
    {
        $salesBill->load('items');

        return view('sales.sales-bills.edit', array_merge(['salesBill' => $salesBill], $this->formOptions()));
    }

    public function update(Request $request, SalesBill $salesBill)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $salesBill) {
            $oldLines = $salesBill->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($oldLines, $salesBill->branch_id, +1);

            $lines = $this->computeLines($data['items']);
            $this->assertStockAvailable($lines, $data['header']['branch_id']);
            $totals = $this->computeTotals($lines, $data);

            $salesBill->update(array_merge($data['header'], $totals));
            $salesBill->items()->delete();
            $salesBill->items()->createMany($lines);

            $this->applyStock($lines, $salesBill->branch_id, -1);
            $this->ledgerPosting->postSalesBill($salesBill);
        });

        return redirect()->route('sales.sales-bills.index')->with('status', "Sales Bill {$salesBill->bill_number} updated successfully.");
    }

    public function destroy(SalesBill $salesBill)
    {
        DB::transaction(function () use ($salesBill) {
            $lines = $salesBill->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($lines, $salesBill->branch_id, +1);
            $this->ledgerPosting->reverse(SalesBill::class, $salesBill->id);
            $salesBill->delete();
        });

        return redirect()->route('sales.sales-bills.index')->with('status', 'Sales Bill deleted and stock restored.');
    }

    /**
     * $direction -1 depletes stock (create), +1 restores it (edit-reverse/delete).
     */
    private function applyStock(array $lines, int $branchId, int $direction): void
    {
        foreach ($lines as $line) {
            ItemStock::adjust($line['item_id'], $branchId, (float) $line['qty'] * $direction);
        }
    }

    private function assertStockAvailable(array $lines, int $branchId): void
    {
        foreach ($lines as $line) {
            $item = Item::find($line['item_id']);
            if ($item->allow_negative_stock) {
                continue;
            }

            $available = (float) (ItemStock::where('item_id', $line['item_id'])->where('branch_id', $branchId)->value('quantity') ?? 0);
            if ($line['qty'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for \"{$item->name}\": available {$available}, requested {$line['qty']}.",
                ]);
            }
        }
    }

    private function nextNumber(): string
    {
        $next = (SalesBill::max('id') ?? 0) + 1;

        return 'SB'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'customers' => Customer::orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
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
            'total_qty' => $collection->sum('qty'),
            'total' => round($collection->sum('net_amount') + $roundOff + $totalExtraCess + $gstCalamityCess, 2),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'bill_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'invoice_type' => ['required', 'in:Retail Invoice,Tax Invoice,Exempted'],
            'delivery_type' => ['required', 'string', 'max:255'],
            'delivery_time' => ['nullable', 'date_format:H:i'],
            'sales_type' => ['required', 'in:Local,Interstate'],
            'payment_type' => ['nullable', 'string', 'max:255'],
            'round_off' => ['nullable', 'numeric'],
            'total_extra_cess' => ['nullable', 'numeric', 'min:0'],
            'gst_calamity_cess' => ['nullable', 'numeric', 'min:0'],
            'total_weight' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ]);

        $header['bill_date'] = $this->normalizeDate($header['bill_date']);

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
