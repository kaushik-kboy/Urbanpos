<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BillSettlement;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Ledger;
use App\Models\PurchaseInvoice;
use App\Models\SalesBill;
use App\Models\Supplier;
use App\Services\Accounting\DocumentNumberingService;
use App\Services\Accounting\FinancialYearGuard;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BillSettlementController extends Controller
{
    public function __construct(
        private LedgerPostingService $ledgerPosting,
        private DocumentNumberingService $numbering,
        private AuditLogger $auditLogger,
        private FinancialYearGuard $financialYearGuard,
    ) {
    }

    public function index(Request $request)
    {
        $query = BillSettlement::with(['customer', 'supplier', 'branch', 'bankLedger', 'journalEntry'])
            ->latest('settlement_date');

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('settlement_number', 'like', "%{$term}%")
                    ->orWhere('reference_no', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$term}%"));
            });
        }

        if ($request->filled('settlement_type')) {
            $query->where('settlement_type', $request->input('settlement_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('settlement_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('settlement_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $settlements = $query->paginate(20)->withQueryString();
        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');

        return view('finance.settlements.index', compact('settlements', 'branches'));
    }

    public function create(Request $request)
    {
        $settlementType = $request->input('type', 'Customer') === 'Supplier' ? 'Supplier' : 'Customer';

        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $oldCustId = old('customer_id', $request->input('customer_id'));
        $customers = Customer::where('status', true)->orderBy('name')->limit(30)->pluck('name', 'id');
        if ($oldCustId && !$customers->has($oldCustId)) {
            $selC = Customer::find($oldCustId);
            if ($selC) {
                $customers->put($selC->id, $selC->name);
            }
        }
        $suppliers = Supplier::where('status', true)->orderBy('name')->pluck('name', 'id');

        $bankLedgers = Ledger::whereIn('ledger_group', ['Cash in Hand', 'Bank Account'])
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('finance.settlements.create', compact('settlementType', 'branches', 'customers', 'suppliers', 'bankLedgers'));
    }

    public function unpaidBills(Request $request)
    {
        $type = $request->input('type', 'Customer');
        $partyId = (int) $request->input('party_id');
        $branchId = $request->input('branch_id');

        if (!$partyId) {
            return response()->json(['bills' => []]);
        }

        $today = Carbon::today();
        $bills = [];

        if ($type === 'Customer') {
            $salesBillsQuery = SalesBill::with(['payments.tenderType', 'settlementItems.settlement'])
                ->where('customer_id', $partyId);

            if (!empty($branchId)) {
                $salesBillsQuery->where('branch_id', $branchId);
            }

            $salesBills = $salesBillsQuery->orderBy('bill_date', 'asc')->get();

            foreach ($salesBills as $sb) {
                // POS Immediate payments (Cash, Card, UPI, etc.)
                $posImmediatePaid = 0.0;
                $hasPayments = $sb->payments->isNotEmpty();
                if ($hasPayments) {
                    $posImmediatePaid = (float) $sb->payments
                        ->filter(fn ($p) => ($p->tenderType?->type ?? '') !== 'Credit')
                        ->sum('amount');
                } elseif (!empty($sb->payment_type) && strtolower($sb->payment_type) !== 'credit' && strtolower($sb->payment_type) !== 'none') {
                    $posImmediatePaid = (float) $sb->total;
                }

                // Active settlements
                $settledSum = (float) $sb->settlementItems
                    ->filter(fn ($si) => ($si->settlement?->status ?? 'Active') === 'Active')
                    ->sum(fn ($si) => (float) $si->settled_amount + (float) $si->discount_amount);

                $totalPaid = round($posImmediatePaid + $settledSum, 2);
                $balanceDue = round((float) $sb->total - $totalPaid, 2);

                if ($balanceDue > 0.01) {
                    $billDate = $sb->bill_date ? Carbon::parse($sb->bill_date) : $today;
                    $bills[] = [
                        'id' => $sb->id,
                        'bill_number' => $sb->bill_number,
                        'bill_date' => $billDate->format('Y-m-d'),
                        'bill_date_formatted' => $billDate->format('d-m-Y'),
                        'total_amount' => (float) $sb->total,
                        'paid_amount' => $totalPaid,
                        'balance_due' => $balanceDue,
                        'days_overdue' => max(0, (int) $billDate->diffInDays($today, false)),
                    ];
                }
            }
        } else {
            $purchaseInvoicesQuery = PurchaseInvoice::with(['settlementItems.settlement'])
                ->where('supplier_id', $partyId)
                ->where('status', '!=', 'Cancelled');

            if (!empty($branchId)) {
                $purchaseInvoicesQuery->where('branch_id', $branchId);
            }

            $invoices = $purchaseInvoicesQuery->orderBy('invoice_date', 'asc')->get();

            foreach ($invoices as $pi) {
                $settledSum = (float) $pi->settlementItems
                    ->filter(fn ($si) => ($si->settlement?->status ?? 'Active') === 'Active')
                    ->sum(fn ($si) => (float) $si->settled_amount + (float) $si->discount_amount);

                $balanceDue = round((float) $pi->total - $settledSum, 2);

                if ($balanceDue > 0.01) {
                    $invDate = $pi->invoice_date ? Carbon::parse($pi->invoice_date) : $today;
                    $bills[] = [
                        'id' => $pi->id,
                        'bill_number' => $pi->invoice_number,
                        'supplier_inv_no' => $pi->supplier_inv_no ?: '-',
                        'bill_date' => $invDate->format('Y-m-d'),
                        'bill_date_formatted' => $invDate->format('d-m-Y'),
                        'total_amount' => (float) $pi->total,
                        'paid_amount' => $settledSum,
                        'balance_due' => $balanceDue,
                        'days_overdue' => max(0, (int) $invDate->diffInDays($today, false)),
                    ];
                }
            }
        }

        return response()->json(['bills' => $bills]);
    }

    public function store(Request $request)
    {
        $header = $request->validate([
            'settlement_type' => ['required', 'in:Customer,Supplier'],
            'customer_id' => ['required_if:settlement_type,Customer', 'nullable', 'exists:customers,id'],
            'supplier_id' => ['required_if:settlement_type,Supplier', 'nullable', 'exists:suppliers,id'],
            'branch_id' => ['required', 'exists:branches,id'],
            'settlement_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_mode' => ['required', 'in:Cash,Bank Transfer,Cheque,UPI,Card'],
            'bank_ledger_id' => ['required', 'exists:ledgers,id'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $this->financialYearGuard->assertOpenForPosting($header['settlement_date']);

        $allocatedItems = $request->validate([
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.bill_id' => ['required', 'integer'],
            'allocations.*.bill_amount' => ['required', 'numeric', 'min:0.01'],
            'allocations.*.settled_amount' => ['required', 'numeric', 'min:0'],
            'allocations.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $filteredAllocations = collect($allocatedItems['allocations'])
            ->filter(fn ($row) => ((float) $row['settled_amount'] > 0 || (float) ($row['discount_amount'] ?? 0) > 0))
            ->values()
            ->all();

        if (empty($filteredAllocations)) {
            throw ValidationException::withMessages([
                'allocations' => 'Please allocate payment amount to at least one bill.',
            ]);
        }

        $sumAllocated = round(collect($filteredAllocations)->sum('settled_amount'), 2);
        $totalPayment = round((float) $header['total_amount'], 2);

        if (abs($sumAllocated - $totalPayment) > 0.05) {
            throw ValidationException::withMessages([
                'total_amount' => "Allocated bill amounts total (₹{$sumAllocated}) must equal the total payment amount (₹{$totalPayment}).",
            ]);
        }

        $settlement = DB::transaction(function () use ($header, $filteredAllocations) {
            $prefix = $header['settlement_type'] === 'Customer' ? 'SET-CST' : 'SET-SUP';
            $nextSeq = (BillSettlement::where('settlement_type', $header['settlement_type'])->max('id') ?? 0) + 1;
            $settlementNumber = $prefix . str_pad((string) $nextSeq, 6, '0', STR_PAD_LEFT);

            $settlement = BillSettlement::create(array_merge($header, [
                'settlement_number' => $settlementNumber,
                'status' => 'Active',
            ]));

            $billableType = $header['settlement_type'] === 'Customer' ? SalesBill::class : PurchaseInvoice::class;

            foreach ($filteredAllocations as $alloc) {
                $settlement->items()->create([
                    'billable_type' => $billableType,
                    'billable_id' => $alloc['bill_id'],
                    'bill_amount' => $alloc['bill_amount'],
                    'settled_amount' => $alloc['settled_amount'],
                    'discount_amount' => $alloc['discount_amount'] ?? 0,
                ]);
            }

            // Post journal entry to accounting ledger
            $journalEntry = $this->ledgerPosting->postBillSettlement($settlement);
            $settlement->update(['journal_entry_id' => $journalEntry->id]);

            return $settlement;
        });

        return redirect()->route('finance.settlements.show', $settlement)
            ->with('status', "Credit settlement {$settlement->settlement_number} posted successfully.");
    }

    public function show(BillSettlement $settlement)
    {
        $settlement->load(['customer', 'supplier', 'branch', 'bankLedger', 'journalEntry.lines.ledger', 'items.billable', 'cancelledBy']);

        return view('finance.settlements.show', compact('settlement'));
    }

    public function destroy(Request $request, BillSettlement $settlement)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        if ($settlement->status === 'Cancelled') {
            throw ValidationException::withMessages([
                'status' => "Settlement {$settlement->settlement_number} is already cancelled.",
            ]);
        }

        DB::transaction(function () use ($settlement, $data, $request) {
            $oldValues = $settlement->only(['status']);

            // Reverse the accounting journal entry
            $this->ledgerPosting->reverse(BillSettlement::class, $settlement->id);

            $settlement->update([
                'status' => 'Cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $data['reason'],
                'cancelled_by_id' => $request->user()?->id,
            ]);

            $this->auditLogger->log('cancel', $settlement, $oldValues, ['status' => 'Cancelled'], $data['reason']);
        });

        return redirect()->route('finance.settlements.index')
            ->with('status', "Settlement {$settlement->settlement_number} cancelled and journal entry reversed.");
    }
}
