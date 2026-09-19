<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DamageStock;
use App\Models\Item;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DamageStockController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = DamageStock::with(['branch', 'items.item'])->latest('entry_date');

        // Location / Branch filter
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        // Wastage Type filter
        if ($request->filled('wastage_type')) {
            $query->where('wastage_type', $request->input('wastage_type'));
        }

        // Search query (Damage Number or Remarks)
        if ($request->filled('q')) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('damage_number', 'like', "%{$q}%")
                    ->orWhere('remarks', 'like', "%{$q}%")
                    ->orWhere('message', 'like', "%{$q}%");
            });
        }

        // Date range filters
        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->input('date_to'));
        }

        // Compute summary totals based on current filters
        $totalsQuery = clone $query;
        $totalEntries = $totalsQuery->count();
        $totalQty = (float) $totalsQuery->sum('total_qty');
        $totalCost = (float) $totalsQuery->sum('total_cost');

        $damageStocks = $query->paginate(15)->withQueryString();

        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('inventory.damage-stocks.index', compact(
            'damageStocks',
            'branches',
            'totalEntries',
            'totalQty',
            'totalCost'
        ));
    }

    public function show(Request $request, DamageStock $damageStock)
    {
        $damageStock->load(['branch', 'items.item.gstTax']);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'id' => $damageStock->id,
                'damage_number' => $damageStock->damage_number,
                'branch_name' => $damageStock->branch?->name ?? 'N/A',
                'entry_date' => $damageStock->entry_date ? $damageStock->entry_date->format('d-m-Y') : '-',
                'wastage_type' => $damageStock->wastage_type,
                'total_qty' => number_format($damageStock->total_qty, 3),
                'total_cost' => number_format($damageStock->total_cost, 2),
                'remarks' => $damageStock->remarks ?: '-',
                'message' => $damageStock->message ?: '-',
                'edit_url' => route('inventory.damage-stocks.edit', $damageStock),
                'items' => $damageStock->items->map(function ($line, $idx) {
                    $item = $line->item;
                    $code = $item?->item_code ?: ($item?->ean_upc_code ?: '-');
                    return [
                        'sno' => $idx + 1,
                        'code' => $code,
                        'name' => $item?->name ?? 'Unknown Item',
                        'exp_date' => $line->exp_date ? $line->exp_date->format('d-m-Y') : '-',
                        'qty' => number_format($line->qty, 3),
                        'cost_price' => number_format($line->cost_price, 2),
                        'sell_price' => number_format($line->sell_price, 2),
                        'mrp' => number_format($line->mrp, 2),
                        'gst_percent' => number_format($line->gst_percent, 0),
                        'gst_tax_amount' => number_format($line->gst_tax_amount, 2),
                        'net_amount' => number_format($line->net_amount, 2),
                    ];
                }),
            ]);
        }

        return view('inventory.damage-stocks.show', compact('damageStock'));
    }

    public function create()
    {
        return view('inventory.damage-stocks.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['entry_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = DamageStock::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('inventory.damage-stocks.index')->with('status', "Damage Stock {$existing->damage_number} created successfully.");
            }
        }

        $damageStock = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);

            $damageStock = DamageStock::create(array_merge($data['header'], [
                'damage_number' => $this->nextNumber(),
                'total_qty' => collect($lines)->sum('qty'),
                'total_cost' => collect($lines)->sum('net_amount'),
            ]));

            $createdItems = $damageStock->items()->createMany($lines);
            $this->postStock($createdItems, $damageStock);

            return $damageStock;
        });

        return redirect()->route('inventory.damage-stocks.index')->with('status', "Damage Stock {$damageStock->damage_number} created successfully.");
    }

    public function edit(DamageStock $damageStock)
    {
        $damageStock->load(['items.item.gstTax']);

        return view('inventory.damage-stocks.edit', array_merge(['damageStock' => $damageStock], $this->formOptions()));
    }

    public function update(Request $request, DamageStock $damageStock)
    {
        $damageStock->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['entry_date']);

        DB::transaction(function () use ($data, $damageStock) {
            $this->stockLedger->reverseByReference(DamageStock::class, $damageStock->id);

            $lines = $this->computeLines($data['items']);

            $damageStock->update(array_merge($data['header'], [
                'total_qty' => collect($lines)->sum('qty'),
                'total_cost' => collect($lines)->sum('net_amount'),
            ]));
            $damageStock->items()->delete();
            $createdItems = $damageStock->items()->createMany($lines);

            $this->postStock($createdItems, $damageStock);
        });

        return redirect()->route('inventory.damage-stocks.index')->with('status', "Damage Stock {$damageStock->damage_number} updated successfully.");
    }

    public function destroy(DamageStock $damageStock)
    {
        // No assertEditable() guard here: destroy() already IS the cancel/reversal action
        // (reverses the ledger, then removes the row) — per spec, cancelling a posted
        // document is the expected correction path, not a "silent edit" to block.
        DB::transaction(function () use ($damageStock) {
            $this->stockLedger->reverseByReference(DamageStock::class, $damageStock->id);
            $damageStock->delete();
        });

        return redirect()->route('inventory.damage-stocks.index')->with('status', 'Damage Stock deleted and stock restored.');
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $branchId = (int) ($request->input('branch_id') ?: 2);

        $items = Item::with([
                'gstTax',
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

        $item = Item::with([
                'gstTax',
                'brand',
                'stocks' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->where('ean_upc_code', $code)
            ->orWhere('item_code', $code)
            ->first();

        if (! $item) {
            $item = Item::with([
                    'gstTax',
                    'brand',
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
            ],
        ]);
    }

    /**
     * Releases stock at the item's current moving-average cost (via unitCost: null),
     * never the form's manually-entered cost_price — inventory loss must reflect what the
     * stock actually cost the business, not a display value.
     */
    private function postStock($createdItems, DamageStock $damageStock): void
    {
        foreach ($createdItems as $itemLine) {
            $this->stockLedger->post(
                itemId: $itemLine->item_id,
                branchId: $damageStock->branch_id,
                movementType: 'DAMAGE',
                qtyDelta: -1 * (float) $itemLine->qty,
                unitCost: null,
                referenceType: DamageStock::class,
                referenceId: $damageStock->id,
                documentDate: $damageStock->entry_date->toDateString(),
                reasonCode: $damageStock->wastage_type,
                expDate: $itemLine->exp_date?->toDateString(),
            );
        }
    }

    private function nextNumber(): string
    {
        $next = (DamageStock::max('id') ?? 0) + 1;

        return 'DMG'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function computeLines(array $items): array
    {
        $itemsById = Item::with('gstTax')->whereIn('id', collect($items)->pluck('item_id')->unique())->get()->keyBy('id');

        return collect($items)->map(function ($line) use ($itemsById) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $item = $itemsById[$line['item_id']];

            $tax = $this->taxEngine->calculate($qty, $costPrice, $item);

            return [
                'item_id' => $line['item_id'],
                'exp_date' => !empty($line['exp_date']) ? $this->normalizeDate($line['exp_date']) : null,
                'qty' => $qty,
                'cost_price' => $costPrice,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
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
            'wastage_type' => ['required', 'in:Wastage,Damage,Theft'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('damage_stocks', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

        $header['entry_date'] = $this->normalizeDate($header['entry_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ['header' => $header, 'items' => $validated['items']];
    }
}
