<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\StockTransfer;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use App\Services\Tax\TaxEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private TaxEngine $taxEngine,
        private AuditLogger $auditLogger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = StockTransfer::with(['fromBranch', 'toBranch']);

        if ($request->filled('search')) {
            $query->where('transfer_number', 'like', "%{$request->search}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transfer_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transfer_date', '<=', $request->date_to);
        }

        if ($request->filled('from_branch_id')) {
            $query->where('from_branch_id', $request->from_branch_id);
        }

        if ($request->filled('to_branch_id')) {
            $query->where('to_branch_id', $request->to_branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $stockTransfers = $query->latest('transfer_date')->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->get();
        $statuses = StockTransfer::select('status')->distinct()->whereNotNull('status')->pluck('status');

        return view('inventory.stock-transfers.index', compact('stockTransfers', 'branches', 'statuses'));
    }

    public function create()
    {
        return view('inventory.stock-transfers.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $this->financialYearGuard->assertOpenForPosting($data['header']['transfer_date']);

        if ($data['header']['posting_key'] ?? null) {
            $existing = StockTransfer::where('posting_key', $data['header']['posting_key'])->first();
            if ($existing) {
                return redirect()->route('inventory.stock-transfers.index')->with('status', "Stock Transfer {$existing->transfer_number} dispatched successfully.");
            }
        }

        $fromBranchId = (int) $data['header']['from_branch_id'];
        $toBranchId = (int) $data['header']['to_branch_id'];

        $this->assertStockAvailable($data['items'], $fromBranchId);

        $fromBranch = Branch::findOrFail($fromBranchId);
        $toBranch = Branch::findOrFail($toBranchId);
        $isInterstate = $fromBranch->state !== $toBranch->state;

        $stockTransfer = DB::transaction(function () use ($data, $fromBranchId, $toBranchId, $isInterstate) {
            $stockTransfer = StockTransfer::create([
                'transfer_number' => $this->nextNumber(),
                'transfer_date' => $data['header']['transfer_date'],
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'remarks' => $data['header']['remarks'] ?? null,
                'posting_key' => $data['header']['posting_key'] ?? null,
                'dispatched_at' => now(),
                'total_qty' => collect($data['items'])->sum('qty'),
            ]);

            $itemsById = Item::with('gstTax')->whereIn('id', collect($data['items'])->pluck('item_id')->unique())->get()->keyBy('id');
            $totalValue = 0.0;

            foreach ($data['items'] as $line) {
                $qty = (float) $line['qty'];
                $item = $itemsById[$line['item_id']];

                $ledgerRow = $this->stockLedger->post(
                    itemId: $item->id,
                    branchId: $fromBranchId,
                    movementType: 'TRANSFER_OUT',
                    qtyDelta: -$qty,
                    unitCost: null,
                    referenceType: StockTransfer::class,
                    referenceId: $stockTransfer->id,
                    documentDate: $data['header']['transfer_date'],
                    expDate: $line['exp_date'] ?? null,
                );

                $unitCost = (float) $ledgerRow->unit_cost;
                $totalValue += $qty * $unitCost;

                $tax = $isInterstate
                    ? $this->taxEngine->calculate($qty, $unitCost, $item, isInterstate: true)
                    : null;

                $stockTransfer->items()->create([
                    'item_id' => $item->id,
                    'exp_date' => $line['exp_date'] ?? null,
                    'qty' => $qty,
                    'unit_cost' => $unitCost,
                    'gst_percent' => $tax['gst_percent'] ?? 0,
                    'taxable_value' => $tax['taxable_value'] ?? 0,
                    'gst_tax_amount' => $tax['gst_tax_amount'] ?? 0,
                    'cgst_amount' => $tax['cgst_amount'] ?? 0,
                    'sgst_amount' => $tax['sgst_amount'] ?? 0,
                    'igst_amount' => $tax['igst_amount'] ?? 0,
                ]);
            }

            $stockTransfer->update(['total_value' => round($totalValue, 2)]);

            return $stockTransfer;
        });

        return redirect()->route('inventory.stock-transfers.index')->with('status', "Stock Transfer {$stockTransfer->transfer_number} dispatched successfully.");
    }

    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items.item', 'fromBranch', 'toBranch']);

        return view('inventory.stock-transfers.show', compact('stockTransfer'));
    }

    public function pendingReceipt(Request $request)
    {
        $branchId = $request->input('branch_id');

        $stockTransfers = StockTransfer::with(['fromBranch', 'toBranch'])
            ->where('status', 'Dispatched')
            ->when($branchId, fn ($q) => $q->where('to_branch_id', $branchId))
            ->latest('transfer_date')
            ->paginate(20)
            ->withQueryString();

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('inventory.stock-transfers.pending-receipt', compact('stockTransfers', 'branches', 'branchId'));
    }

    public function receiveForm(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'Dispatched') {
            return redirect()->route('inventory.stock-transfers.pending-receipt')->with('status', 'This transfer is not awaiting receipt.');
        }

        $stockTransfer->load(['items.item', 'fromBranch', 'toBranch']);

        return view('inventory.stock-transfers.receive', compact('stockTransfer'));
    }

    public function receive(Request $request, StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'Dispatched') {
            throw ValidationException::withMessages([
                'status' => "Stock Transfer #{$stockTransfer->transfer_number} is not awaiting receipt.",
            ]);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:stock_transfer_items,id'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated, $stockTransfer) {
            $lines = $stockTransfer->items->keyBy('id');

            foreach ($validated['items'] as $input) {
                $line = $lines[$input['id']];
                $receivedQty = min((float) $input['received_qty'], (float) $line->qty);

                if ($receivedQty > 0) {
                    // Received at the DISPATCH-time cost snapshot, never the destination's
                    // own (possibly zero/stale) average — same value-conservation rule as
                    // Phase 2's repack/kit work.
                    $this->stockLedger->post(
                        itemId: $line->item_id,
                        branchId: $stockTransfer->to_branch_id,
                        movementType: 'TRANSFER_IN',
                        qtyDelta: $receivedQty,
                        unitCost: (float) $line->unit_cost,
                        referenceType: StockTransfer::class,
                        referenceId: $stockTransfer->id,
                        documentDate: now()->toDateString(),
                        expDate: $line->exp_date?->toDateString(),
                    );
                }

                $line->update(['received_qty' => $receivedQty]);
            }

            $stockTransfer->update(['status' => 'Received', 'received_at' => now()]);
        });

        return redirect()->route('inventory.stock-transfers.pending-receipt')->with('status', "Stock Transfer #{$stockTransfer->transfer_number} received successfully.");
    }

    public function cancel(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== 'Dispatched') {
            throw ValidationException::withMessages([
                'status' => "Stock Transfer #{$stockTransfer->transfer_number} can only be cancelled while awaiting receipt.",
            ]);
        }

        DB::transaction(function () use ($stockTransfer) {
            $this->stockLedger->reverseByReference(StockTransfer::class, $stockTransfer->id);
            $this->auditLogger->log('cancel', $stockTransfer, ['status' => 'Dispatched'], ['status' => 'Cancelled']);
            $stockTransfer->update(['status' => 'Cancelled']);
        });

        return redirect()->route('inventory.stock-transfers.index')->with('status', "Stock Transfer #{$stockTransfer->transfer_number} cancelled and source stock restored.");
    }

    private function assertStockAvailable(array $items, int $fromBranchId): void
    {
        foreach ($items as $line) {
            $item = Item::find($line['item_id']);
            if (! $item || $item->allow_negative_stock) {
                continue;
            }

            $available = (float) (ItemStock::where('item_id', $line['item_id'])->where('branch_id', $fromBranchId)->value('quantity') ?? 0);
            if ((float) $line['qty'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for \"{$item->name}\" at source branch: available {$available}, requested {$line['qty']}.",
                ]);
            }
        }
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $branchId = (int) $request->input('branch_id');

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
            $displayCode = $item->ean_upc_code ?: ($item->item_code ? "Item: {$item->item_code}" : "");
            $codeStr = $displayCode ? " [{$displayCode}]" : "";

            return [
                'id' => $item->id,
                'text' => "{$item->name}{$codeStr}",
                'name' => $item->name,
                'code' => $item->ean_upc_code ?: ($item->item_code ?: ''),
                'brand' => $item->brand?->name ?? '-',
                'available_qty' => (float) ($stock?->quantity ?? 0),
            ];
        }));
    }

    public function getItemByCode(Request $request)
    {
        $code = trim($request->input('code', ''));
        if ($code === '') {
            return response()->json(['found' => false]);
        }

        $branchId = (int) $request->input('branch_id');

        $item = Item::with(['stocks' => fn ($query) => $query->where('branch_id', $branchId)])
            ->where('ean_upc_code', $code)
            ->orWhere('item_code', $code)
            ->orWhere('alias', $code)
            ->first();

        if (! $item) {
            return response()->json(['found' => false]);
        }

        $stock = $item->stocks->first();
        $displayCode = $item->ean_upc_code ?: ($item->item_code ? "Item: {$item->item_code}" : "");
        $codeStr = $displayCode ? " [{$displayCode}]" : "";

        return response()->json([
            'found' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'text' => "{$item->name}{$codeStr}",
                'code' => $item->ean_upc_code ?: ($item->item_code ?: ''),
                'available_qty' => (float) ($stock?->quantity ?? 0),
            ],
        ]);
    }

    private function nextNumber(): string
    {
        $next = (StockTransfer::max('id') ?? 0) + 1;

        return 'STF'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::where('status', true)->orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validateData(Request $request): array
    {
        $header = $request->validate([
            'transfer_date' => ['required', 'date'],
            'from_branch_id' => ['required', 'exists:branches,id', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'exists:branches,id'],
            'remarks' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ]);

        $header['transfer_date'] = $this->normalizeDate($header['transfer_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $items = collect($validated['items'])->map(function ($line) {
            $line['exp_date'] = $this->normalizeDate($line['exp_date'] ?? null);

            return $line;
        })->all();

        return ['header' => $header, 'items' => $items];
    }
}
