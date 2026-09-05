<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockUpdateController extends Controller
{
    public function index()
    {
        $stockUpdates = StockUpdate::with('branch')->latest('entry_date')->paginate(20);

        return view('inventory.stock-updates.index', compact('stockUpdates'));
    }

    public function create()
    {
        return view('inventory.stock-updates.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $stockUpdate = DB::transaction(function () use ($data) {
            $stockUpdate = StockUpdate::create(array_merge($data['header'], [
                'update_number' => $this->nextNumber(),
            ]));

            $lines = $this->applyLines($data['items'], $stockUpdate->branch_id);
            $stockUpdate->items()->createMany($lines);

            return $stockUpdate;
        });

        return redirect()->route('inventory.stock-updates.index')->with('status', "Stock Update {$stockUpdate->update_number} created successfully.");
    }

    public function edit(StockUpdate $stockUpdate)
    {
        $stockUpdate->load('items');

        return view('inventory.stock-updates.edit', array_merge(['stockUpdate' => $stockUpdate], $this->formOptions()));
    }

    public function update(Request $request, StockUpdate $stockUpdate)
    {
        $data = $this->validateData($request);

        DB::transaction(function () use ($data, $stockUpdate) {
            // Undo the delta this stock-update previously applied.
            foreach ($stockUpdate->items as $oldLine) {
                ItemStock::adjust($oldLine->item_id, $stockUpdate->branch_id, -$oldLine->delta_qty);
            }

            $stockUpdate->update($data['header']);
            $stockUpdate->items()->delete();

            $lines = $this->applyLines($data['items'], $stockUpdate->branch_id);
            $stockUpdate->items()->createMany($lines);
        });

        return redirect()->route('inventory.stock-updates.index')->with('status', "Stock Update {$stockUpdate->update_number} updated successfully.");
    }

    public function destroy(StockUpdate $stockUpdate)
    {
        DB::transaction(function () use ($stockUpdate) {
            foreach ($stockUpdate->items as $line) {
                ItemStock::adjust($line->item_id, $stockUpdate->branch_id, -$line->delta_qty);
            }
            $stockUpdate->delete();
        });

        return redirect()->route('inventory.stock-updates.index')->with('status', 'Stock Update deleted and stock reversed.');
    }

    /**
     * Reads the current system quantity for each line, computes physical - system as the
     * delta, applies it to item_stocks, and returns the rows ready for createMany().
     */
    private function applyLines(array $items, int $branchId): array
    {
        return collect($items)->map(function ($line) use ($branchId) {
            $itemId = $line['item_id'];
            $physicalQty = (float) $line['physical_qty'];
            $systemQty = (float) (ItemStock::where('item_id', $itemId)->where('branch_id', $branchId)->value('quantity') ?? 0);
            $delta = round($physicalQty - $systemQty, 3);

            ItemStock::adjust($itemId, $branchId, $delta);

            return [
                'item_id' => $itemId,
                'exp_date' => $this->normalizeDate($line['exp_date'] ?: null),
                'physical_qty' => $physicalQty,
                'system_qty_at_entry' => $systemQty,
                'delta_qty' => $delta,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
            ];
        })->all();
    }

    private function nextNumber(): string
    {
        $next = (StockUpdate::max('id') ?? 0) + 1;

        return 'STKU'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'items' => Item::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'entry_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $header['entry_date'] = $this->normalizeDate($header['entry_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.physical_qty' => ['required', 'numeric', 'min:0'],
            'items.*.sell_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.mrp' => ['nullable', 'numeric', 'min:0'],
        ]);

        return ['header' => $header, 'items' => $validated['items']];
    }
}
