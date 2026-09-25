<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\OpeningStock;
use App\Models\Supplier;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningStockController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = OpeningStock::with('branch');

        if ($request->filled('search')) {
            $query->where('entry_number', 'like', "%{$request->search}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $openingStocks = $query->latest('entry_date')->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->get();

        return view('inventory.opening-stocks.index', compact('openingStocks', 'branches'));
    }

    public function create()
    {
        return view('inventory.opening-stocks.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['entry_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = OpeningStock::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('inventory.opening-stocks.index')->with('status', "Opening Stock {$existing->entry_number} created successfully.");
            }
        }

        $openingStock = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);

            $openingStock = OpeningStock::create(array_merge($data['header'], [
                'entry_number' => $this->nextNumber(),
                'total_qty' => collect($lines)->sum('qty'),
                'total' => collect($lines)->sum('net_amount'),
            ]));

            $openingStock->items()->createMany($lines);
            $this->postStockAndItemMaster($lines, $openingStock);

            return $openingStock;
        });

        return redirect()->route('inventory.opening-stocks.index')->with('status', "Opening Stock {$openingStock->entry_number} created successfully.");
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $branchId = (int) ($request->input('branch_id') ?: 2);

        // Search ONLY by item description (name) and alias
        $items = Item::with([
                'gstTax',
                'supplier',
                'brand',
                'stocks' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('alias', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json($items->map(function ($item) {
            $stock = $item->stocks->first();
            $costPrice = ($stock && $stock->cost_price > 0) ? (float) $stock->cost_price : (float) ($item->cost_price ?? 0);
            $sellPrice = ($stock && $stock->sell_price > 0) ? (float) $stock->sell_price : (float) ($item->sell_price ?? 0);
            $mrp = ($stock && $stock->mrp > 0) ? (float) $stock->mrp : (float) ($item->mrp ?? 0);

            $displayCode = $item->ean_upc_code ?: ($item->item_code ? "Item: {$item->item_code}" : "");
            $codeStr = $displayCode ? " [{$displayCode}]" : "";
            return [
                'id' => $item->id,
                'text' => "{$item->name}{$codeStr}",
                'name' => $item->name,
                'code' => $item->ean_upc_code ?: ($item->item_code ?: ''),
                'item_code' => $item->item_code ?: '',
                'barcode' => $item->ean_upc_code ?: '',
                'brand' => $item->brand?->name ?? '-',
                'cost_price' => $costPrice,
                'sell_price' => $sellPrice,
                'mrp' => $mrp,
                'gst_percent' => (float) ($item->gstTax?->percentage ?? 0),
                'supplier_id' => $item->supplier_id ?: null,
            ];
        }));
    }

    public function getItemByCode(Request $request)
    {
        $code = trim($request->input('code', ''));
        if ($code === '') {
            return response()->json(['found' => false]);
        }

        $branchId = (int) ($request->input('branch_id') ?: 2);

        // 1. Exact match on barcode (ean_upc_code) or TruePOS item_code
        $item = Item::with([
                'gstTax',
                'supplier',
                'stocks' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->where('ean_upc_code', $code)
            ->orWhere('item_code', $code)
            ->first();

        // 2. Exact match on item alias
        if (! $item) {
            $item = Item::with([
                    'gstTax',
                    'supplier',
                    'stocks' => fn ($query) => $query->where('branch_id', $branchId),
                ])
                ->where('alias', $code)
                ->first();
        }

        if (! $item) {
            return response()->json(['found' => false]);
        }

        $stock = $item->stocks->first();
        $costPrice = ($stock && $stock->cost_price > 0) ? (float) $stock->cost_price : (float) ($item->cost_price ?? 0);
        $sellPrice = ($stock && $stock->sell_price > 0) ? (float) $stock->sell_price : (float) ($item->sell_price ?? 0);
        $mrp = ($stock && $stock->mrp > 0) ? (float) $stock->mrp : (float) ($item->mrp ?? 0);

        $displayCode = $item->ean_upc_code ?: ($item->item_code ? "Item: {$item->item_code}" : "");
        $codeStr = $displayCode ? " [{$displayCode}]" : "";

        return response()->json([
            'found' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'text' => "{$item->name}{$codeStr}",
                'code' => $item->ean_upc_code ?: ($item->item_code ?: ''),
                'cost_price' => $costPrice,
                'sell_price' => $sellPrice,
                'mrp' => $mrp,
                'gst_percent' => (float) ($item->gstTax?->percentage ?? 0),
                'supplier_id' => $item->supplier_id ?: null,
            ],
        ]);
    }

    public function edit(OpeningStock $openingStock)
    {
        $openingStock->load(['items.item.gstTax', 'items.supplier']);

        return view('inventory.opening-stocks.edit', array_merge(['openingStock' => $openingStock], $this->formOptions()));
    }

    public function update(Request $request, OpeningStock $openingStock)
    {
        $openingStock->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['entry_date']);

        DB::transaction(function () use ($data, $openingStock) {
            $this->stockLedger->reverseByReference(OpeningStock::class, $openingStock->id);

            $lines = $this->computeLines($data['items']);

            $openingStock->update(array_merge($data['header'], [
                'total_qty' => collect($lines)->sum('qty'),
                'total' => collect($lines)->sum('net_amount'),
            ]));
            $openingStock->items()->delete();
            $openingStock->items()->createMany($lines);

            $this->postStockAndItemMaster($lines, $openingStock);
        });

        return redirect()->route('inventory.opening-stocks.index')->with('status', "Opening Stock {$openingStock->entry_number} updated successfully.");
    }

    public function destroy(OpeningStock $openingStock)
    {
        // No assertEditable() guard here: destroy() already IS the cancel/reversal action.
        DB::transaction(function () use ($openingStock) {
            $this->stockLedger->reverseByReference(OpeningStock::class, $openingStock->id);
            $openingStock->delete();
        });

        return redirect()->route('inventory.opening-stocks.index')->with('status', 'Opening Stock deleted and stock reversed.');
    }

    /**
     * Opening stock establishes the item's very first weighted-average cost — posted as
     * an OPENING movement at the entered cost_price, plus the same item-master price
     * snapshot the purchase flow uses.
     */
    private function postStockAndItemMaster(array $lines, OpeningStock $openingStock): void
    {
        foreach ($lines as $line) {
            if ((float) $line['qty'] > 0) {
                $this->stockLedger->post(
                    itemId: $line['item_id'],
                    branchId: $openingStock->branch_id,
                    movementType: 'OPENING',
                    qtyDelta: (float) $line['qty'],
                    unitCost: (float) $line['cost_price'],
                    referenceType: OpeningStock::class,
                    referenceId: $openingStock->id,
                    documentDate: $openingStock->entry_date->toDateString(),
                    expDate: $line['exp_date'],
                );
            }

            if (! empty($line['cost_price'])) {
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
        $next = (int) (OpeningStock::max('id') ?? 0);
        $lastOps = OpeningStock::where('entry_number', 'like', 'OPS%')->orderByDesc('id')->value('entry_number');
        if ($lastOps && preg_match('/^OPS(\d+)$/', $lastOps, $matches)) {
            $next = max($next, (int) $matches[1]);
        }
        do {
            $next++;
            $ops = 'OPS'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        } while (OpeningStock::where('entry_number', $ops)->exists());

        return $ops;
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function computeLines(array $items): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');

        return collect($items)->map(function ($line) use ($itemsById) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $item = $itemsById[$line['item_id']];
            $base = $qty * $costPrice;

            $discPercent = (float) ($line['disc_percent'] ?? 0);
            $discAmount = (float) ($line['disc_amount'] ?? 0);

            $schemeDiscPercent = (float) ($line['scheme_disc_percent'] ?? 0);
            $schemeAmount = (float) ($line['scheme_amount'] ?? 0);
            if ($schemeAmount <= 0 && $schemeDiscPercent > 0) {
                $schemeAmount = round($base * $schemeDiscPercent / 100, 2);
            }
            $schemeOthers = (float) ($line['scheme_others'] ?? 0);

            $tax = $this->taxEngine->calculate($qty, $costPrice, $item, $discPercent, $discAmount, $schemeAmount + $schemeOthers);

            return [
                'item_id' => $line['item_id'],
                'supplier_id' => ! empty($line['supplier_id']) ? $line['supplier_id'] : null,
                'exp_date' => ! empty($line['exp_date']) ? $this->normalizeDate($line['exp_date']) : null,
                'qty' => $qty,
                'cost_price' => $costPrice,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
                'disc_percent' => $discPercent,
                'disc_amount' => $tax['disc_amount'],
                'scheme_disc_percent' => $schemeDiscPercent,
                'scheme_amount' => $schemeAmount,
                'scheme_others' => $schemeOthers,
                'gst_percent' => $tax['gst_percent'],
                'gst_tax_amount' => $tax['gst_tax_amount'],
                'net_amount' => $tax['net_amount'],
            ];
        })->all();
    }

    private function validateData(Request $request): array
    {
        $headerRules = [
            'branch_id' => ['required', 'exists:branches,id'],
            'entry_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('opening_stocks', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

        $header['entry_date'] = $this->normalizeDate($header['entry_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.disc_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.scheme_disc_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.scheme_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.scheme_others' => ['nullable', 'numeric'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.net_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ['header' => $header, 'items' => $validated['items']];
    }
}
