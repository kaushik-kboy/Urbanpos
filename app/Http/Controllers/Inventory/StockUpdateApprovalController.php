<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\StockUpdate;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockUpdateApprovalController extends Controller
{
    public function __construct(
        private StockLedgerService $stockLedger,
        private StockUpdateController $stockUpdates,
        private AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request)
    {
        $pendingCount = StockUpdate::where('status', 'Pending')->count();
        $status = $request->input('status', $pendingCount > 0 ? 'Pending' : 'All');
        $branchId = $request->input('branch_id');

        $query = StockUpdate::with(['branch', 'items.item'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($status && $status !== 'All', fn ($q) => $q->where('status', $status))
            ->latest('entry_date');

        $stockUpdates = $query->paginate(15);
        $branches = Branch::orderBy('name')->pluck('name', 'id');

        return view('inventory.stock-update-approval.index', compact(
            'stockUpdates',
            'branches',
            'branchId',
            'status',
            'pendingCount'
        ));
    }

    public function show(StockUpdate $stockUpdate)
    {
        $stockUpdate->load(['branch', 'items.item']);

        return view('inventory.stock-update-approval.show', compact('stockUpdate'));
    }

    public function approve(Request $request, StockUpdate $stockUpdate)
    {
        if ($stockUpdate->isPosted()) {
            return back()->with('status', "Stock Update #{$stockUpdate->update_number} is already approved.");
        }

        DB::transaction(function () use ($stockUpdate) {
            $lines = $stockUpdate->items->map->only(['item_id', 'delta_qty', 'exp_date'])->all();
            $this->stockUpdates->postLines($stockUpdate, collect($lines)->map(fn ($l) => [
                'item_id' => $l['item_id'],
                'delta_qty' => $l['delta_qty'],
                'exp_date' => $l['exp_date']?->toDateString(),
            ])->all());

            $stockUpdate->update(['status' => 'Approved']);
            $this->auditLogger->log('approve', $stockUpdate, ['status' => 'Pending'], ['status' => 'Approved']);
        });

        return redirect()->route('inventory.stock-update-approval.index')
            ->with('status', "Stock Update #{$stockUpdate->update_number} approved and stock adjusted successfully.");
    }

    public function reject(Request $request, StockUpdate $stockUpdate)
    {
        $oldStatus = $stockUpdate->status;

        DB::transaction(function () use ($stockUpdate, $oldStatus) {
            // If it was approved earlier, reverse the posted movement (never delete history).
            if ($stockUpdate->isPosted()) {
                $this->stockLedger->reverseByReference(StockUpdate::class, $stockUpdate->id);
            }
            $stockUpdate->update(['status' => 'Rejected']);
            $this->auditLogger->log('reject', $stockUpdate, ['status' => $oldStatus], ['status' => 'Rejected']);
        });

        return redirect()->route('inventory.stock-update-approval.index')
            ->with('status', "Stock Update #{$stockUpdate->update_number} rejected.");
    }
}
