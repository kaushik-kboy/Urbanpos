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

        $fromBranch = Branch::findOrFail($fromBranchId);
        $toBranch = Branch::findOrFail($toBranchId);
        $isInterstate = $fromBranch->state !== $toBranch->state;

        $stockTransfer = DB::transaction(function () use ($data, $fromBranchId, $toBranchId, $isInterstate) {
            // assertStockAvailable is inside the transaction so lockForUpdate() is effective:
            // concurrent transfers for the same items are serialized at the database row level.
            $this->assertStockAvailable($data['items'], $fromBranchId);
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

    public function print(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items.item', 'fromBranch', 'toBranch']);

        return view('inventory.stock-transfers.print', compact('stockTransfer'));
    }

    public function pendingReceipt(Request $request)
    {
        $branchId = $request->input('branch_id');
        $status = $request->input('status', 'Dispatched');

        $query = StockTransfer::with(['fromBranch', 'toBranch'])
            ->when($branchId, fn ($q) => $q->where('to_branch_id', $branchId));

        if ($status && $status !== 'All') {
            $query->where('status', $status);
        }

        $stockTransfers = $query->latest('transfer_date')->latest('id')->paginate(20)->withQueryString();
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        $baseCountQuery = StockTransfer::query()
            ->when($branchId, fn ($q) => $q->where('to_branch_id', $branchId));

        $pendingCount = (clone $baseCountQuery)->where('status', 'Dispatched')->count();
        $receivedCount = (clone $baseCountQuery)->where('status', 'Received')->count();
        $allCount = (clone $baseCountQuery)->count();

        return view('inventory.stock-transfers.pending-receipt', compact(
            'stockTransfers',
            'branches',
            'branchId',
            'status',
            'pendingCount',
            'receivedCount',
            'allCount'
        ));
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
            'remarks' => ['nullable', 'string', 'max:1000'],
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

            $receiveRemarks = trim($validated['remarks'] ?? '');
            $newRemarks = $stockTransfer->remarks;
            if (!empty($receiveRemarks)) {
                $newRemarks = !empty($newRemarks) ? ($newRemarks . " | Inward: " . $receiveRemarks) : "Inward: " . $receiveRemarks;
            }

            $stockTransfer->update([
                'status' => 'Received',
                'received_at' => now(),
                'remarks' => $newRemarks,
            ]);
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
        $totalQtyByItem = [];
        foreach ($items as $line) {
            $itemId = (int) $line['item_id'];
            $totalQtyByItem[$itemId] = ($totalQtyByItem[$itemId] ?? 0.0) + (float) $line['qty'];
        }

        foreach ($totalQtyByItem as $itemId => $totalRequested) {
            $item = Item::find($itemId);
            if (! $item) {
                continue;
            }

            $available = (float) (ItemStock::where('item_id', $itemId)
                ->where('branch_id', $fromBranchId)
                ->lockForUpdate()
                ->value('quantity') ?? 0);
            if (round($totalRequested, 4) > round($available, 4)) {
                throw ValidationException::withMessages([
                    'items' => "Insufficient stock for \"{$item->name}\" at source branch: available {$available}, requested {$totalRequested}.",
                ]);
            }
        }
    }

    public function itemList(Request $request)
    {
        $branchId = (int) ($request->input('branch_id') ?: $request->input('from_branch_id') ?: session('active_branch_id', auth()->user()?->branch_id ?: (\App\Models\Branch::value('id') ?? 1)));
        $search   = trim((string) $request->input('search', ''));
        $expiry   = trim((string) $request->input('expiry', ''));
        $code     = trim((string) $request->input('code', ''));

        // Require at least 1 character to avoid loading huge dataset
        $hasFilter = $search !== '' || $code !== '' || $expiry !== '';
        if (! $hasFilter) {
            return response()->json(['items' => [], 'hint' => 'Type to search items…']);
        }

        $limit  = 100;
        $where  = [
            'i.status = 1',
            'COALESCE(st.quantity, 0) > 0',
        ];
        $params = [$branchId, $branchId];

        $orderSql    = 'i.name ASC';
        $orderParams = [];

        if ($search !== '') {
            $sWild   = "%{$search}%";
            $sExact  = $search;
            $sPrefix = "{$search}%";

            $where[] = '(i.name LIKE ? OR i.item_code = ? OR i.item_code LIKE ? OR i.ean_upc_code = ? OR i.ean_upc_code LIKE ?)';
            $params  = array_merge($params, [$sWild, $sExact, $sPrefix, $sExact, $sPrefix]);

            $orderSql = "
                CASE
                    WHEN i.item_code = ? THEN 1
                    WHEN i.ean_upc_code = ? THEN 2
                    WHEN i.item_code LIKE ? THEN 3
                    WHEN i.ean_upc_code LIKE ? THEN 4
                    WHEN i.name LIKE ? THEN 5
                    ELSE 6
                END ASC,
                i.name ASC
            ";
            $orderParams = [$sExact, $sExact, $sPrefix, $sPrefix, $sPrefix];
        }

        if ($code !== '') {
            $cExact  = $code;
            $cPrefix = "{$code}%";
            $where[] = '(i.item_code = ? OR i.item_code LIKE ? OR i.ean_upc_code = ? OR i.ean_upc_code LIKE ?)';
            $params  = array_merge($params, [$cExact, $cPrefix, $cExact, $cPrefix]);

            if ($search === '') {
                $orderSql = "
                    CASE
                        WHEN i.item_code = ? THEN 1
                        WHEN i.ean_upc_code = ? THEN 2
                        WHEN i.item_code LIKE ? THEN 3
                        WHEN i.ean_upc_code LIKE ? THEN 4
                        ELSE 5
                    END ASC,
                    i.name ASC
                ";
                $orderParams = [$cExact, $cExact, $cPrefix, $cPrefix];
            }
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "
            SELECT
                i.id,
                i.name,
                COALESCE(i.item_code, '')  AS item_code,
                COALESCE(i.ean_upc_code, '') AS ean_upc_code,
                COALESCE(st.quantity, 0)   AS qty,
                ei.exp_date
            FROM items i
            LEFT JOIN item_stocks st
                   ON st.item_id = i.id AND st.branch_id = ?
            LEFT JOIN (
                SELECT pi2.item_id,
                       MIN(pi2.exp_date) AS exp_date
                FROM purchase_invoice_items pi2
                INNER JOIN purchase_invoices pih
                        ON pih.id = pi2.purchase_invoice_id AND pih.branch_id = ?
                WHERE pi2.exp_date IS NOT NULL
                  AND CAST(pi2.exp_date AS CHAR) NOT IN ('', '0000-00-00')
                GROUP BY pi2.item_id
            ) ei ON ei.item_id = i.id
            {$whereClause}
            ORDER BY {$orderSql}
            LIMIT {$limit}
        ";

        $finalParams = array_merge($params, $orderParams);
        $rows = DB::select($sql, $finalParams);

        // Fallback: for rows without branch-specific expiry, try cross-branch
        $noExpIds = collect($rows)->filter(fn ($r) => empty($r->exp_date))->pluck('id')->all();
        $expiryFallback = [];
        if (! empty($noExpIds)) {
            $ph = implode(',', array_fill(0, count($noExpIds), '?'));
            $fbSql = "
                SELECT pi2.item_id,
                       MIN(pi2.exp_date) AS exp_date
                FROM purchase_invoice_items pi2
                WHERE pi2.item_id IN ({$ph})
                  AND pi2.exp_date IS NOT NULL
                  AND CAST(pi2.exp_date AS CHAR) NOT IN ('', '0000-00-00')
                GROUP BY pi2.item_id
            ";
            foreach (DB::select($fbSql, $noExpIds) as $fb) {
                $expiryFallback[$fb->item_id] = $fb->exp_date;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $expRaw = $row->exp_date ?? ($expiryFallback[$row->id] ?? null);
            $exp = null;
            if (! empty($expRaw) && $expRaw !== '0000-00-00') {
                try {
                    $exp = \Carbon\Carbon::parse($expRaw)->format('Y-m-d');
                } catch (\Throwable) {
                    $exp = (string) $expRaw;
                }
            }

            if ($expiry !== '' && (! $exp || ! str_contains($exp, $expiry))) {
                continue;
            }

            $displayCode = $row->ean_upc_code ?: ($row->item_code ?: '');
            $result[] = [
                'id'       => $row->id,
                'name'     => $row->name,
                'code'     => $displayCode,
                'barcode'  => $row->ean_upc_code,
                'item_code'=> $row->item_code,
                'exp_date' => $exp,
                'qty'      => (float) $row->qty,
                'available_qty' => (float) $row->qty,
            ];
        }

        return response()->json(['items' => $result]);
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $branchId = (int) $request->input('branch_id');

        $items = Item::where('status', true)
            ->whereHas('stocks', function ($query) use ($branchId) {
                $query->where('branch_id', $branchId)->where('quantity', '>', 0);
            })
            ->with([
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
                'exp_date' => $this->resolveItemExpiry($item, $stock),
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

        $item = Item::where('status', true)
            ->with(['stocks' => fn ($query) => $query->where('branch_id', $branchId)])
            ->where(function ($q) use ($code) {
                $q->where('ean_upc_code', $code)
                  ->orWhere('item_code', $code)
                  ->orWhere('alias', $code);
            })
            ->first();

        if (! $item) {
            return response()->json(['found' => false]);
        }

        $stock = $item->stocks->first();
        $avail = (float) ($stock?->quantity ?? 0);
        if ($avail <= 0) {
            return response()->json([
                'found' => false,
                'error' => "Product '{$item->name}' has 0 available stock in this branch. Cannot transfer.",
            ]);
        }

        $expDate = $this->resolveItemExpiry($item, $stock);
        if ($expDate && $expDate < now()->toDateString()) {
            return response()->json([
                'found' => false,
                'error' => "Product '{$item->name}' has expired on {$expDate}. Transfer of expired items is not permitted.",
            ]);
        }

        $displayCode = $item->ean_upc_code ?: ($item->item_code ? "Item: {$item->item_code}" : "");
        $codeStr = $displayCode ? " [{$displayCode}]" : "";

        return response()->json([
            'found' => true,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'text' => "{$item->name}{$codeStr}",
                'code' => $item->ean_upc_code ?: ($item->item_code ?: ''),
                'available_qty' => $avail,
                'exp_date' => $expDate,
            ],
        ]);
    }

    private function resolveItemExpiry(Item $item, ?ItemStock $stock): ?string
    {
        if (!empty($stock?->exp_date) && (string)$stock->exp_date !== '0000-00-00') {
            try {
                return \Carbon\Carbon::parse($stock->exp_date)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        $fbExp = DB::table('purchase_invoice_items')
            ->where('item_id', $item->id)
            ->whereNotNull('exp_date')
            ->whereNotIn('exp_date', ['', '0000-00-00'])
            ->orderBy('id', 'desc')
            ->value('exp_date');

        if ($fbExp) {
            try {
                return \Carbon\Carbon::parse($fbExp)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        return null;
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
        // 1. Pre-normalize transfer_date (handles DD/MM/YYYY and DDMMYYYY)
        if ($request->filled('transfer_date')) {
            $request->merge(['transfer_date' => $this->normalizeDate($request->input('transfer_date'))]);
        }

        // 2. Pre-filter items to drop empty rows and pre-normalize exp_date before validation
        if ($request->has('items') && is_array($request->input('items'))) {
            $filteredItems = array_values(array_filter($request->input('items'), function ($line) {
                return !empty($line['item_id']) && (!isset($line['qty']) || (float)$line['qty'] > 0);
            }));
            foreach ($filteredItems as $k => $item) {
                if (!empty($item['exp_date'])) {
                    $filteredItems[$k]['exp_date'] = $this->normalizeDate($item['exp_date']);
                }
            }
            $request->merge(['items' => $filteredItems]);
        }

        $today = date('Y-m-d');
        $headerRules = [
            'transfer_date' => ['required', 'date', "before_or_equal:{$today}"],
            'from_branch_id' => ['required', 'exists:branches,id', 'different:to_branch_id'],
            'to_branch_id' => ['required', 'exists:branches,id'],
            'remarks' => ['nullable', 'string'],
            'posting_key' => ['nullable', 'string', 'max:100'],
        ];

        $headerMessages = [
            'transfer_date.before_or_equal' => 'Future date is not allowed for Transfer Date.',
        ];

        $dynamicService = app(\App\Services\DynamicValidationService::class);
        $dynamicService->applyTo('stock_transfers', $headerRules, $headerMessages);

        $header = $request->validate($headerRules, $headerMessages);

        $header['transfer_date'] = $this->normalizeDate($header['transfer_date']);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.exp_date' => ['nullable', 'date'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $todayStr = now()->toDateString();
        $expiredItems = [];

        $items = collect($validated['items'])->map(function ($line) use ($todayStr, &$expiredItems) {
            $normalizedExp = $this->normalizeDate($line['exp_date'] ?? null);
            $line['exp_date'] = $normalizedExp;

            if (!empty($normalizedExp) && $normalizedExp < $todayStr) {
                $item = Item::find($line['item_id']);
                $itemName = $item ? $item->name : "Item #{$line['item_id']}";
                $expiredItems[] = "{$itemName} (Expired on {$normalizedExp})";
            }

            return $line;
        })->all();

        if (!empty($expiredItems)) {
            throw ValidationException::withMessages([
                'items' => 'Cannot transfer out expired products: ' . implode(', ', $expiredItems) . '. Transfer of expired items is not permitted.',
            ]);
        }

        return ['header' => $header, 'items' => $items];
    }
}
