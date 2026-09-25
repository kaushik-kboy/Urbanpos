<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PurchaseIndent;
use App\Models\PurchaseIndentItem;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseIndentController extends Controller
{
    public function __construct(private AuditLogger $auditLogger)
    {
    }

    public function index(Request $request)
    {
        $query = PurchaseIndent::with(['branch', 'requestedBy', 'reviewedBy', 'purchaseOrder']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('indent_number', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('indent_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('indent_date', '<=', $request->date_to);
        }

        $indents = $query->latest('indent_date')->latest('id')->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->get();
        $priorities = ['Low', 'Medium', 'High', 'Urgent'];
        $statuses = ['Pending', 'Approved', 'Rejected', 'Converted', 'Cancelled'];

        return view('purchase.indents.index', compact('indents', 'branches', 'priorities', 'statuses'));
    }

    public function create()
    {
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $items = Item::where('status', true)->orderBy('name')->get(['id', 'item_code', 'name', 'cost_price', 'sell_price']);
        $departments = ['Store / Retail', 'Warehouse', 'Pharmacy', 'Grocery', 'Pet Care', 'Bakery', 'Stationery', 'General'];
        $priorities = ['Low', 'Medium', 'High', 'Urgent'];

        return view('purchase.indents.create', compact('branches', 'items', 'departments', 'priorities'));
    }

    public function store(Request $request)
    {
        $rules = [
            'indent_date' => ['required', 'date'],
            'required_by_date' => ['nullable', 'date', 'after_or_equal:indent_date'],
            'branch_id' => ['required', 'exists:branches,id'],
            'department' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'in:Low,Medium,High,Urgent'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.requested_qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];

        $messages = [];
        app(\App\Services\DynamicValidationService::class)->applyTo('purchase_indents', $rules, $messages);
        $data = $request->validate($rules, $messages);

        $indent = DB::transaction(function () use ($data, $request) {
            $totRequestedQty = 0;
            $totEstimatedAmount = 0;
            $lines = [];

            // Pre-fetch items for cost fallback
            $itemIds = collect($data['items'])->pluck('item_id')->unique();
            $itemModels = Item::whereIn('id', $itemIds)->get()->keyBy('id');

            // Pre-fetch branch stock
            $branchStocks = ItemStock::where('branch_id', $data['branch_id'])
                ->whereIn('item_id', $itemIds)
                ->pluck('quantity', 'item_id');

            foreach ($data['items'] as $line) {
                $itemId = (int) $line['item_id'];
                $itemModel = $itemModels->get($itemId);
                $qty = (float) $line['requested_qty'];
                $cost = !empty($line['estimated_cost']) && (float) $line['estimated_cost'] > 0
                    ? (float) $line['estimated_cost']
                    : (float) ($itemModel?->cost_price ?? 0);
                $stock = (float) ($branchStocks[$itemId] ?? 0);

                $totRequestedQty += $qty;
                $totEstimatedAmount += round($qty * $cost, 2);

                $lines[] = [
                    'item_id' => $itemId,
                    'current_stock' => $stock,
                    'requested_qty' => $qty,
                    'approved_qty' => null,
                    'estimated_cost' => $cost,
                    'remarks' => $line['remarks'] ?? null,
                ];
            }

            $indentNumber = $this->nextNumber();

            $indent = PurchaseIndent::create([
                'indent_number' => $indentNumber,
                'indent_date' => $data['indent_date'],
                'required_by_date' => $data['required_by_date'] ?? null,
                'branch_id' => $data['branch_id'],
                'requested_by_id' => $request->user()?->id,
                'department' => $data['department'],
                'priority' => $data['priority'],
                'status' => 'Pending',
                'total_requested_qty' => $totRequestedQty,
                'total_approved_qty' => 0,
                'total_estimated_amount' => $totEstimatedAmount,
                'remarks' => $data['remarks'] ?? null,
                'posting_key' => PurchaseIndent::class.'_'.uniqid(),
            ]);

            $indent->items()->createMany($lines);

            $this->auditLogger->log('create', $indent, [], $indent->toArray(), "Purchase Indent {$indent->indent_number} raised");

            return $indent;
        });

        return redirect()->route('purchase.purchase-indents.show', $indent)
            ->with('status', "Purchase Indent {$indent->indent_number} raised successfully.");
    }

    public function show(PurchaseIndent $purchaseIndent)
    {
        $purchaseIndent->load(['branch', 'requestedBy', 'reviewedBy', 'cancelledBy', 'purchaseOrder', 'items.item']);

        return view('purchase.indents.show', compact('purchaseIndent'));
    }

    public function approve(Request $request, PurchaseIndent $purchaseIndent)
    {
        if ($purchaseIndent->status !== 'Pending') {
            throw ValidationException::withMessages([
                'indent' => "Purchase Indent {$purchaseIndent->indent_number} cannot be approved because it is {$purchaseIndent->status}.",
            ]);
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:purchase_indent_items,id'],
            'items.*.approved_qty' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $oldValues = $purchaseIndent->only(['status', 'total_approved_qty', 'total_estimated_amount']);

        DB::transaction(function () use ($validated, $purchaseIndent, $request) {
            $totApprovedQty = 0;
            $totEstimatedAmount = 0;

            foreach ($validated['items'] as $itemData) {
                $indentItem = PurchaseIndentItem::where('id', $itemData['id'])
                    ->where('purchase_indent_id', $purchaseIndent->id)
                    ->firstOrFail();

                $approvedQty = (float) $itemData['approved_qty'];
                $indentItem->update(['approved_qty' => $approvedQty]);

                $totApprovedQty += $approvedQty;
                $totEstimatedAmount += round($approvedQty * (float) $indentItem->estimated_cost, 2);
            }

            $updateData = [
                'status' => 'Approved',
                'total_approved_qty' => $totApprovedQty,
                'total_estimated_amount' => $totEstimatedAmount,
                'reviewed_by_id' => $request->user()?->id,
                'reviewed_at' => now(),
            ];

            if ($request->filled('remarks')) {
                $updateData['remarks'] = $purchaseIndent->remarks
                    ? $purchaseIndent->remarks.' | Approval Note: '.$request->remarks
                    : $request->remarks;
            }

            $purchaseIndent->update($updateData);
        });

        $this->auditLogger->log('approve', $purchaseIndent, $oldValues, ['status' => 'Approved'], 'Purchase Indent approved');

        return redirect()->route('purchase.purchase-indents.show', $purchaseIndent)
            ->with('status', "Purchase Indent {$purchaseIndent->indent_number} approved successfully.");
    }

    public function reject(Request $request, PurchaseIndent $purchaseIndent)
    {
        if ($purchaseIndent->status !== 'Pending') {
            throw ValidationException::withMessages([
                'indent' => "Purchase Indent {$purchaseIndent->indent_number} cannot be rejected because it is {$purchaseIndent->status}.",
            ]);
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255'],
        ]);

        $oldValues = $purchaseIndent->only(['status']);

        $purchaseIndent->update([
            'status' => 'Rejected',
            'rejection_reason' => $data['rejection_reason'],
            'reviewed_by_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        $this->auditLogger->log('reject', $purchaseIndent, $oldValues, ['status' => 'Rejected'], $data['rejection_reason']);

        return redirect()->route('purchase.purchase-indents.show', $purchaseIndent)
            ->with('status', "Purchase Indent {$purchaseIndent->indent_number} rejected.");
    }

    public function destroy(Request $request, PurchaseIndent $purchaseIndent)
    {
        if ($purchaseIndent->status === 'Converted') {
            throw ValidationException::withMessages([
                'indent' => "Cannot cancel Purchase Indent {$purchaseIndent->indent_number} — it has already been converted to Purchase Order.",
            ]);
        }

        if ($purchaseIndent->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'indent' => "Purchase Indent {$purchaseIndent->indent_number} is already cancelled.",
            ]);
        }

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ]);

        $oldValues = $purchaseIndent->only(['status']);

        $purchaseIndent->update([
            'status' => 'Cancelled',
            'cancellation_reason' => $data['cancellation_reason'],
            'cancelled_by_id' => $request->user()?->id,
            'cancelled_at' => now(),
        ]);

        $this->auditLogger->log('cancel', $purchaseIndent, $oldValues, ['status' => 'Cancelled'], $data['cancellation_reason']);

        return redirect()->route('purchase.purchase-indents.index')
            ->with('status', "Purchase Indent {$purchaseIndent->indent_number} cancelled.");
    }

    public function print(PurchaseIndent $purchaseIndent)
    {
        $purchaseIndent->load(['branch', 'requestedBy', 'reviewedBy', 'items.item']);

        return view('purchase.indents.print', compact('purchaseIndent'));
    }

    public function itemStock(Request $request)
    {
        $request->validate([
            'item_id' => ['required', 'exists:items,id'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        $stock = ItemStock::where('item_id', $request->item_id)
            ->where('branch_id', $request->branch_id)
            ->value('quantity') ?? 0;

        $item = Item::find($request->item_id);

        return response()->json([
            'item_id' => (int) $request->item_id,
            'current_stock' => (float) $stock,
            'cost_price' => (float) ($item?->cost_price ?? 0),
        ]);
    }

    private function nextNumber(): string
    {
        $next = (int) (PurchaseIndent::max('id') ?? 0);
        $lastIndent = PurchaseIndent::where('indent_number', 'like', 'IND%')->orderByDesc('id')->value('indent_number');
        if ($lastIndent && preg_match('/^IND(\d+)$/', $lastIndent, $matches)) {
            $next = max($next, (int) $matches[1]);
        }
        do {
            $next++;
            $indNum = 'IND'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
        } while (PurchaseIndent::where('indent_number', $indNum)->exists());

        return $indNum;
    }
}
