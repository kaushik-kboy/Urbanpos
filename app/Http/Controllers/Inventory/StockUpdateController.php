<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockUpdate;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockUpdateController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = \App\Models\StockUpdateItem::with(['item', 'stockUpdate.branch'])
            ->join('stock_updates', 'stock_update_items.stock_update_id', '=', 'stock_updates.id')
            ->leftJoin('item_stocks', function ($join) {
                $join->on('item_stocks.item_id', '=', 'stock_update_items.item_id')
                     ->on('item_stocks.branch_id', '=', 'stock_updates.branch_id');
            });

        // Filter: Search (Item Code, Name, Barcode, Alias, Update No, Remarks)
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('item', function ($iq) use ($search) {
                    $iq->where('item_code', 'like', "%{$search}%")
                       ->orWhere('name', 'like', "%{$search}%")
                       ->orWhere('alias', 'like', "%{$search}%")
                       ->orWhere('ean_upc_code', 'like', "%{$search}%");
                })->orWhere('stock_updates.update_number', 'like', "%{$search}%")
                  ->orWhere('stock_updates.remarks', 'like', "%{$search}%");
            });
        }

        // Filter: Branch / Location
        if ($branchId = $request->input('branch_id')) {
            $query->where('stock_updates.branch_id', $branchId);
        }

        // Filter: Date Range
        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('stock_updates.entry_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('stock_updates.entry_date', '<=', $dateTo);
        }

        // Filter: Difference Type
        if ($diffType = $request->input('diff_type')) {
            if ($diffType === 'shortage') {
                $query->where('stock_update_items.delta_qty', '<', 0);
            } elseif ($diffType === 'excess') {
                $query->where('stock_update_items.delta_qty', '>', 0);
            } elseif ($diffType === 'exact') {
                $query->where('stock_update_items.delta_qty', '=', 0);
            }
        }

        // Filter: Status
        if ($status = $request->input('status')) {
            $query->where('stock_updates.status', $status);
        }

        // Export CSV if requested
        if ($request->input('export') === 'csv') {
            return $this->exportCsv($query->select('stock_update_items.*', 'item_stocks.quantity as live_current_stock')->get());
        }

        // Summary KPI totals before pagination
        $totalCount = (clone $query)->count();
        $totalPhysicalQty = (clone $query)->sum('stock_update_items.physical_qty');
        $totalSystemQty = (clone $query)->sum('stock_update_items.system_qty_at_entry');
        $totalDeltaQty = (clone $query)->sum('stock_update_items.delta_qty');

        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [25, 50, 100, 200, 500])) {
            $perPage = 50;
        }

        $stockUpdateItems = $query->select(
                'stock_update_items.*',
                'item_stocks.quantity as live_current_stock'
            )
            ->orderByDesc('stock_updates.entry_date')
            ->orderByDesc('stock_update_items.id')
            ->paginate($perPage)
            ->withQueryString();

        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('inventory.stock-updates.index', compact(
            'stockUpdateItems',
            'branches',
            'totalCount',
            'totalPhysicalQty',
            'totalSystemQty',
            'totalDeltaQty'
        ));
    }

    protected function exportCsv($items)
    {
        $filename = 'stock_updates_' . date('Y_m_d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'S.No',
                'Code',
                'Description',
                'Exp Dt',
                'Qty (Counted)',
                'System Stock (At Entry)',
                'Diff Qty',
                'Live Current Stock',
                'Sell Price',
                'MRP',
                'Update No',
                'Date',
                'Location',
                'Status',
            ]);

            foreach ($items as $idx => $line) {
                $code = $line->item?->item_code ?: ($line->item?->ean_upc_code ?: '-');
                $name = $line->item?->name ?? '-';
                $exp = $line->exp_date ? $line->exp_date->format('d-m-Y') : '';
                fputcsv($handle, [
                    $idx + 1,
                    $code,
                    $name,
                    $exp,
                    $line->physical_qty,
                    $line->system_qty_at_entry,
                    $line->delta_qty,
                    $line->live_current_stock ?? 0,
                    $line->sell_price,
                    $line->mrp,
                    $line->stockUpdate?->update_number ?? '-',
                    $line->stockUpdate?->entry_date ? $line->stockUpdate->entry_date->format('d-m-Y') : '',
                    $line->stockUpdate?->branch?->name ?? '-',
                    $line->stockUpdate?->status ?? 'Approved',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        return view('inventory.stock-updates.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['entry_date']);

        $stockUpdate = DB::transaction(function () use ($data) {
            $stockUpdate = StockUpdate::create(array_merge($data['header'], [
                'update_number' => $this->nextNumber(),
                'status' => 'Pending',
            ]));

            $lines = $this->buildLines($data['items'], $stockUpdate->branch_id);
            $stockUpdate->items()->createMany($lines);

            // Physical count is only evidence until approved by supervisor/manager.
            // Starts in 'Pending' status and does NOT post to stock ledger until approved.
            if ($stockUpdate->isPosted()) {
                $this->postLines($stockUpdate, $lines);
            }

            return $stockUpdate;
        });

        return redirect()->route('inventory.stock-updates.index')->with('status', "Stock Update {$stockUpdate->update_number} created successfully and submitted for supervisor approval.");
    }

    public function edit(StockUpdate $stockUpdate)
    {
        $stockUpdate->load('items');

        return view('inventory.stock-updates.edit', array_merge(['stockUpdate' => $stockUpdate], $this->formOptions()));
    }

    public function update(Request $request, StockUpdate $stockUpdate)
    {
        $stockUpdate->assertEditable();

        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['entry_date']);

        DB::transaction(function () use ($data, $stockUpdate) {
            $stockUpdate->update($data['header']);
            $stockUpdate->items()->delete();

            $lines = $this->buildLines($data['items'], $stockUpdate->branch_id);
            $stockUpdate->items()->createMany($lines);
        });

        return redirect()->route('inventory.stock-updates.index')->with('status', "Stock Update {$stockUpdate->update_number} updated successfully.");
    }

    public function destroy(StockUpdate $stockUpdate)
    {
        DB::transaction(function () use ($stockUpdate) {
            if ($stockUpdate->isPosted()) {
                $this->stockLedger->reverseByReference(StockUpdate::class, $stockUpdate->id);
            }
            $stockUpdate->delete();
        });

        return redirect()->route('inventory.stock-updates.index')->with('status', 'Stock Update deleted and stock reversed.');
    }

    /**
     * Reads the current system quantity for each line and computes physical - system as
     * the delta — pure record-keeping, no stock/ledger effect. Posting is a separate step
     * (postLines(), called only once the record is Approved).
     */
    private function buildLines(array $items, int $branchId): array
    {
        return collect($items)->map(function ($line) use ($branchId) {
            $itemId = $line['item_id'];
            $physicalQty = (float) $line['physical_qty'];
            $systemQty = (float) (ItemStock::where('item_id', $itemId)->where('branch_id', $branchId)->value('quantity') ?? 0);
            $delta = round($physicalQty - $systemQty, 3);

            return [
                'item_id' => $itemId,
                'exp_date' => $this->normalizeDate($line['exp_date'] ?? null),
                'physical_qty' => $physicalQty,
                'system_qty_at_entry' => $systemQty,
                'delta_qty' => $delta,
                'sell_price' => (float) ($line['sell_price'] ?? 0),
                'mrp' => (float) ($line['mrp'] ?? 0),
            ];
        })->all();
    }

    /**
     * Posts each non-zero delta to the stock ledger, tagged EXCESS or SHORTAGE and
     * referencing this StockUpdate — called on create when already Approved, and from
     * StockUpdateApprovalController::approve() when a Pending count is later approved.
     */
    public function postLines(StockUpdate $stockUpdate, array $lines): void
    {
        foreach ($lines as $line) {
            $delta = (float) $line['delta_qty'];
            if ($delta === 0.0) {
                continue;
            }

            $this->stockLedger->post(
                itemId: $line['item_id'],
                branchId: $stockUpdate->branch_id,
                movementType: $delta > 0 ? 'EXCESS' : 'SHORTAGE',
                qtyDelta: $delta,
                unitCost: null,
                referenceType: StockUpdate::class,
                referenceId: $stockUpdate->id,
                documentDate: $stockUpdate->entry_date->toDateString(),
                reasonCode: 'PHYSICAL_COUNT',
                expDate: $line['exp_date'] ?? null,
            );
        }
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
        $headerRules = [
            'branch_id' => ['required', 'exists:branches,id'],
            'entry_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ];

        $headerMessages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('stock_updates', $headerRules, $headerMessages);
        $header = $request->validate($headerRules, $headerMessages);

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
