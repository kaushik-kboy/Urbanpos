<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DamageStock;
use App\Models\Item;
use App\Models\ItemStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DamageStockController extends Controller
{
    public function index()
    {
        $damageStocks = DamageStock::with('branch')->latest('entry_date')->paginate(20);

        return view('inventory.damage-stocks.index', compact('damageStocks'));
    }

    public function create()
    {
        return view('inventory.damage-stocks.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $damageStock = DB::transaction(function () use ($data) {
            $lines = $this->computeLines($data['items']);

            $damageStock = DamageStock::create(array_merge($data['header'], [
                'damage_number' => $this->nextNumber(),
                'total_qty' => collect($lines)->sum('qty'),
                'total_cost' => collect($lines)->sum('net_amount'),
            ]));

            $damageStock->items()->createMany($lines);
            $this->applyStock($lines, $damageStock->branch_id, -1);

            return $damageStock;
        });

        return redirect()->route('inventory.damage-stocks.index')->with('status', "Damage Stock {$damageStock->damage_number} created successfully.");
    }

    public function edit(DamageStock $damageStock)
    {
        $damageStock->load('items');

        return view('inventory.damage-stocks.edit', array_merge(['damageStock' => $damageStock], $this->formOptions()));
    }

    public function update(Request $request, DamageStock $damageStock)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $damageStock) {
            $oldLines = $damageStock->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($oldLines, $damageStock->branch_id, +1);

            $lines = $this->computeLines($data['items']);

            $damageStock->update(array_merge($data['header'], [
                'total_qty' => collect($lines)->sum('qty'),
                'total_cost' => collect($lines)->sum('net_amount'),
            ]));
            $damageStock->items()->delete();
            $damageStock->items()->createMany($lines);

            $this->applyStock($lines, $damageStock->branch_id, -1);
        });

        return redirect()->route('inventory.damage-stocks.index')->with('status', "Damage Stock {$damageStock->damage_number} updated successfully.");
    }

    public function destroy(DamageStock $damageStock)
    {
        DB::transaction(function () use ($damageStock) {
            $lines = $damageStock->items->map->only(['item_id', 'qty'])->all();
            $this->applyStock($lines, $damageStock->branch_id, +1);
            $damageStock->delete();
        });

        return redirect()->route('inventory.damage-stocks.index')->with('status', 'Damage Stock deleted and stock restored.');
    }

    /**
     * $direction -1 writes stock off (create), +1 restores it (edit-reverse/delete).
     */
    private function applyStock(array $lines, int $branchId, int $direction): void
    {
        foreach ($lines as $line) {
            ItemStock::adjust($line['item_id'], $branchId, (float) $line['qty'] * $direction);
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
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function computeLines(array $items): array
    {
        return collect($items)->map(function ($line) {
            $qty = (float) $line['qty'];
            $costPrice = (float) $line['cost_price'];
            $base = $qty * $costPrice;

            $gstPercent = (float) ($line['gst_percent'] ?? 0);
            $gstTaxAmount = round($base * $gstPercent / 100, 2);
            $netAmount = round($base + $gstTaxAmount, 2);

            return [
                'item_id' => $line['item_id'],
                'exp_date' => $this->normalizeDate($line['exp_date'] ?: null),
                'qty' => $qty,
                'cost_price' => $costPrice,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
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
            'wastage_type' => ['required', 'in:Wastage,Damage,Theft'],
            'remarks' => ['nullable', 'string'],
            'message' => ['nullable', 'string'],
        ]);

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
