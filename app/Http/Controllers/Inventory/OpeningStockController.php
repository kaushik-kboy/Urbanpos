<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\OpeningStock;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpeningStockController extends Controller
{
    public function index()
    {
        $openingStocks = OpeningStock::with('branch')->latest('entry_date')->paginate(20);

        return view('inventory.opening-stocks.index', compact('openingStocks'));
    }

    public function create()
    {
        return view('inventory.opening-stocks.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $openingStock = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);

            $openingStock = OpeningStock::create(array_merge($data['header'], [
                'entry_number' => $this->nextNumber(),
                'total_qty' => collect($lines)->sum('qty'),
                'total' => collect($lines)->sum('net_amount'),
            ]));

            $openingStock->items()->createMany($lines);
            $this->applyStock($lines, $openingStock->branch_id, +1);

            return $openingStock;
        });

        return redirect()->route('inventory.opening-stocks.index')->with('status', "Opening Stock {$openingStock->entry_number} created successfully.");
    }

    public function edit(OpeningStock $openingStock)
    {
        $openingStock->load('items');

        return view('inventory.opening-stocks.edit', array_merge(['openingStock' => $openingStock], $this->formOptions()));
    }

    public function update(Request $request, OpeningStock $openingStock)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $openingStock) {
            $oldLines = $openingStock->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($oldLines, $openingStock->branch_id, -1);

            $lines = $this->computeLines($data['items']);

            $openingStock->update(array_merge($data['header'], [
                'total_qty' => collect($lines)->sum('qty'),
                'total' => collect($lines)->sum('net_amount'),
            ]));
            $openingStock->items()->delete();
            $openingStock->items()->createMany($lines);

            $this->applyStock($lines, $openingStock->branch_id, +1);
        });

        return redirect()->route('inventory.opening-stocks.index')->with('status', "Opening Stock {$openingStock->entry_number} updated successfully.");
    }

    public function destroy(OpeningStock $openingStock)
    {
        DB::transaction(function () use ($openingStock) {
            $lines = $openingStock->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($lines, $openingStock->branch_id, -1);
            $openingStock->delete();
        });

        return redirect()->route('inventory.opening-stocks.index')->with('status', 'Opening Stock deleted and stock reversed.');
    }

    private function applyStock(array $lines, int $branchId, int $direction): void
    {
        foreach ($lines as $line) {
            ItemStock::adjust($line['item_id'], $branchId, (float) $line['qty'] * $direction);

            if ($direction > 0 && ! empty($line['cost_price'])) {
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
        $next = (OpeningStock::max('id') ?? 0) + 1;

        return 'OPS'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
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
                'supplier_id' => $line['supplier_id'] ?: null,
                'exp_date' => $this->normalizeDate($line['exp_date'] ?: null),
                'qty' => $qty,
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

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'entry_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ]);

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
            'items.*.gst_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ['header' => $header, 'items' => $validated['items']];
    }
}
