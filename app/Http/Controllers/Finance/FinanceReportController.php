<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\Ledger;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    public function index()
    {
        return view('finance.reports.index');
    }

    public function generalLedger(Request $request)
    {
        $ledgerId = $request->input('ledger_id');
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));

        $ledgers = Ledger::orderBy('name')->pluck('name', 'id');
        $ledger = $ledgerId ? Ledger::find($ledgerId) : null;

        $lines = collect();
        $openingBalance = 0;

        if ($ledger) {
            $openingBalance = $ledger->opening_balance_type === 'Debit' ? (float) $ledger->opening_balance : -(float) $ledger->opening_balance;
            $openingBalance += (float) $ledger->lines()
                ->whereHas('journalEntry', fn ($q) => $q->whereDate('voucher_date', '<', $from))
                ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
                ->value('net');

            $lines = $ledger->lines()
                ->with('journalEntry')
                ->whereHas('journalEntry', fn ($q) => $q->whereDate('voucher_date', '>=', $from)->whereDate('voucher_date', '<=', $to))
                ->get()
                ->sortBy(fn ($line) => $line->journalEntry->voucher_date)
                ->values();
        }

        return view('finance.reports.general-ledger', compact('ledgers', 'ledger', 'ledgerId', 'from', 'to', 'lines', 'openingBalance'));
    }

    public function dayBook(Request $request)
    {
        $from = $request->input('from', now()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $request->input('branch_id');
        $voucherType = $request->input('voucher_type');
        $search = $request->input('search');

        $query = JournalEntry::with(['lines.ledger', 'branch'])
            ->whereDate('voucher_date', '>=', $from)
            ->whereDate('voucher_date', '<=', $to);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($voucherType) {
            $query->where('voucher_type', $voucherType);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('narration', 'like', "%{$search}%")
                    ->orWhereHas('lines.ledger', fn ($lq) => $lq->where('name', 'like', "%{$search}%"));
            });
        }

        $entries = $query->orderBy('voucher_date')
            ->orderBy('id')
            ->get();

        $totalDebit = $entries->sum(fn ($e) => $e->lines->sum('debit'));
        $totalCredit = $entries->sum(fn ($e) => $e->lines->sum('credit'));

        $branches = \App\Models\Branch::orderBy('name')->pluck('name', 'id');
        $voucherTypes = JournalEntry::select('voucher_type')->distinct()->whereNotNull('voucher_type')->pluck('voucher_type');

        return view('finance.reports.day-book', compact('entries', 'from', 'to', 'branches', 'branchId', 'voucherTypes', 'voucherType', 'search', 'totalDebit', 'totalCredit'));
    }

    public function trialBalance(Request $request)
    {
        $asOf = $request->input('as_of', now()->format('Y-m-d'));

        $ledgers = Ledger::orderBy('ledger_group')->orderBy('name')->get()->map(function (Ledger $ledger) use ($asOf) {
            $balance = $ledger->balance($asOf);

            return (object) [
                'name' => $ledger->name,
                'ledger_group' => $ledger->ledger_group,
                'debit' => $balance > 0 ? $balance : 0,
                'credit' => $balance < 0 ? abs($balance) : 0,
            ];
        })->filter(fn ($row) => $row->debit != 0 || $row->credit != 0)->values();

        return view('finance.reports.trial-balance', compact('ledgers', 'asOf'));
    }

    public function profitLoss(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $request->input('branch_id');

        // Sales & Returns
        $salesQuery = \App\Models\SalesBill::whereDate('bill_date', '>=', $from)->whereDate('bill_date', '<=', $to);
        $returnsQuery = \App\Models\SalesReturn::whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to);
        $purchaseQuery = \App\Models\PurchaseInvoice::whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to);

        if ($branchId) {
            $salesQuery->where('branch_id', $branchId);
            $returnsQuery->where('branch_id', $branchId);
            $purchaseQuery->where('branch_id', $branchId);
        }

        $grossSales = (float) $salesQuery->sum('total');
        $salesReturn = (float) $returnsQuery->sum('total');
        $netSales = max(0, $grossSales - $salesReturn);

        $grossPurchase = (float) $purchaseQuery->sum('total');

        // Other Ledgers for Indirect Incomes & Expenses
        $expenseLedgers = Ledger::whereIn('ledger_group', ['Indirect Expenses', 'Direct Expenses', 'Administrative Expenses'])
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry', fn ($j) => $j->whereDate('voucher_date', '>=', $from)->whereDate('voucher_date', '<=', $to))])
            ->get()
            ->map(function ($l) {
                $amount = $l->lines->sum('debit') - $l->lines->sum('credit');
                return (object) ['name' => $l->name, 'group' => $l->ledger_group, 'amount' => $amount];
            })
            ->filter(fn ($r) => $r->amount > 0)
            ->values();

        $incomeLedgers = Ledger::whereIn('ledger_group', ['Indirect Incomes', 'Direct Incomes'])
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry', fn ($j) => $j->whereDate('voucher_date', '>=', $from)->whereDate('voucher_date', '<=', $to))])
            ->get()
            ->map(function ($l) {
                $amount = $l->lines->sum('credit') - $l->lines->sum('debit');
                return (object) ['name' => $l->name, 'group' => $l->ledger_group, 'amount' => $amount];
            })
            ->filter(fn ($r) => $r->amount > 0)
            ->values();

        $totalExpenses = (float) $expenseLedgers->sum('amount');
        $totalIndirectIncomes = (float) $incomeLedgers->sum('amount');

        // Trading Gross Profit: Net Sales - Gross Purchases
        $grossProfit = $netSales - $grossPurchase;
        $netProfit = $grossProfit + $totalIndirectIncomes - $totalExpenses;

        $branches = \App\Models\Branch::orderBy('name')->pluck('name', 'id');

        return view('finance.reports.profit-loss', compact(
            'from', 'to', 'branchId', 'branches',
            'grossSales', 'salesReturn', 'netSales',
            'grossPurchase', 'grossProfit',
            'expenseLedgers', 'totalExpenses',
            'incomeLedgers', 'totalIndirectIncomes',
            'netProfit'
        ));
    }

    public function cashBankBook(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $accountType = $request->input('account_type', 'All');
        $ledgerId = $request->input('ledger_id');
        $branchId = $request->input('branch_id');

        $groups = match ($accountType) {
            'Cash' => ['Cash in Hand'],
            'Bank' => ['Bank Account', 'Bank Accounts', 'Bank OCC Account'],
            default => ['Cash in Hand', 'Bank Account', 'Bank Accounts', 'Bank OCC Account'],
        };

        $ledgersQuery = Ledger::where(function ($q) use ($groups) {
            $q->whereIn('ledger_group', $groups)
              ->orWhere('name', 'like', '%Cash%')
              ->orWhere('name', 'like', '%Bank%');
        });

        $availableLedgers = (clone $ledgersQuery)->orderBy('name')->pluck('name', 'id');
        $targetLedgerIds = $ledgerId ? [$ledgerId] : (clone $ledgersQuery)->pluck('id')->toArray();

        $openingBalance = 0;
        foreach ($targetLedgerIds as $id) {
            $l = Ledger::find($id);
            if ($l) {
                $base = $l->opening_balance_type === 'Debit' ? (float) $l->opening_balance : -(float) $l->opening_balance;
                $priorMovements = (float) $l->lines()
                    ->whereHas('journalEntry', function ($q) use ($from, $branchId) {
                        $q->whereDate('voucher_date', '<', $from);
                        if ($branchId) $q->where('branch_id', $branchId);
                    })
                    ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
                    ->value('net');
                $openingBalance += ($base + $priorMovements);
            }
        }

        $lines = \App\Models\JournalEntryLine::with(['journalEntry.branch', 'ledger'])
            ->whereIn('ledger_id', $targetLedgerIds)
            ->whereHas('journalEntry', function ($q) use ($from, $to, $branchId) {
                $q->whereDate('voucher_date', '>=', $from)
                  ->whereDate('voucher_date', '<=', $to);
                if ($branchId) $q->where('branch_id', $branchId);
            })
            ->get()
            ->sortBy(fn ($l) => $l->journalEntry->voucher_date . '_' . str_pad((string)$l->journalEntry->id, 8, '0', STR_PAD_LEFT))
            ->values();

        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $closingBalance = $openingBalance + $totalDebit - $totalCredit;

        $branches = \App\Models\Branch::orderBy('name')->pluck('name', 'id');

        return view('finance.reports.cash-bank-book', compact(
            'from', 'to', 'accountType', 'ledgerId', 'branchId', 'branches',
            'availableLedgers', 'lines', 'openingBalance', 'totalDebit', 'totalCredit', 'closingBalance'
        ));
    }

    public function outstandingAging(Request $request)
    {
        $partyType = $request->input('party_type', 'Customer') === 'Supplier' ? 'Supplier' : 'Customer';
        $branchId = $request->input('branch_id');
        $asOfDate = $request->input('as_of_date', now()->format('Y-m-d'));
        $asOf = \Carbon\Carbon::parse($asOfDate);

        $branches = \App\Models\Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $rows = [];

        if ($partyType === 'Customer') {
            $salesBills = \App\Models\SalesBill::with(['customer', 'payments.tenderType', 'settlementItems.settlement'])
                ->whereDate('bill_date', '<=', $asOfDate);

            if ($branchId) {
                $salesBills->where('branch_id', $branchId);
            }

            $bills = $salesBills->get();

            $partyGroups = $bills->groupBy('customer_id');

            foreach ($partyGroups as $customerId => $partyBills) {
                $customer = $partyBills->first()->customer;
                if (!$customer) continue;

                $partyTotalDue = 0.0;
                $b0_30 = 0.0;
                $b31_60 = 0.0;
                $b61_90 = 0.0;
                $b90_plus = 0.0;
                $billCount = 0;

                foreach ($partyBills as $sb) {
                    $posImmediatePaid = 0.0;
                    if ($sb->payments->isNotEmpty()) {
                        $posImmediatePaid = (float) $sb->payments
                            ->filter(fn ($p) => ($p->tenderType?->type ?? '') !== 'Credit')
                            ->sum('amount');
                    } elseif (!empty($sb->payment_type) && strtolower($sb->payment_type) !== 'credit' && strtolower($sb->payment_type) !== 'none') {
                        $posImmediatePaid = (float) $sb->total;
                    }

                    $settledSum = (float) $sb->settlementItems
                        ->filter(fn ($si) => ($si->settlement?->status ?? 'Active') === 'Active' && $si->settlement?->settlement_date?->lte($asOf))
                        ->sum(fn ($si) => (float) $si->settled_amount + (float) $si->discount_amount);

                    $balanceDue = round((float) $sb->total - $posImmediatePaid - $settledSum, 2);

                    if ($balanceDue > 0.01) {
                        $partyTotalDue += $balanceDue;
                        $billCount++;
                        $days = $sb->bill_date ? max(0, (int) $sb->bill_date->diffInDays($asOf, false)) : 0;

                        if ($days <= 30) {
                            $b0_30 += $balanceDue;
                        } elseif ($days <= 60) {
                            $b31_60 += $balanceDue;
                        } elseif ($days <= 90) {
                            $b61_90 += $balanceDue;
                        } else {
                            $b90_plus += $balanceDue;
                        }
                    }
                }

                if ($partyTotalDue > 0.01) {
                    $rows[] = [
                        'party_id' => $customer->id,
                        'party_name' => $customer->name,
                        'phone' => $customer->phone ?: '-',
                        'bill_count' => $billCount,
                        'total_due' => $partyTotalDue,
                        'bucket_0_30' => $b0_30,
                        'bucket_31_60' => $b31_60,
                        'bucket_61_90' => $b61_90,
                        'bucket_90_plus' => $b90_plus,
                    ];
                }
            }
        } else {
            $purchaseInvoices = \App\Models\PurchaseInvoice::with(['supplier', 'settlementItems.settlement'])
                ->where('status', '!=', 'Cancelled')
                ->whereDate('invoice_date', '<=', $asOfDate);

            if ($branchId) {
                $purchaseInvoices->where('branch_id', $branchId);
            }

            $invoices = $purchaseInvoices->get();
            $partyGroups = $invoices->groupBy('supplier_id');

            foreach ($partyGroups as $supplierId => $partyInvoices) {
                $supplier = $partyInvoices->first()->supplier;
                if (!$supplier) continue;

                $partyTotalDue = 0.0;
                $b0_30 = 0.0;
                $b31_60 = 0.0;
                $b61_90 = 0.0;
                $b90_plus = 0.0;
                $billCount = 0;

                foreach ($partyInvoices as $pi) {
                    $settledSum = (float) $pi->settlementItems
                        ->filter(fn ($si) => ($si->settlement?->status ?? 'Active') === 'Active' && $si->settlement?->settlement_date?->lte($asOf))
                        ->sum(fn ($si) => (float) $si->settled_amount + (float) $si->discount_amount);

                    $balanceDue = round((float) $pi->total - $settledSum, 2);

                    if ($balanceDue > 0.01) {
                        $partyTotalDue += $balanceDue;
                        $billCount++;
                        $days = $pi->invoice_date ? max(0, (int) $pi->invoice_date->diffInDays($asOf, false)) : 0;

                        if ($days <= 30) {
                            $b0_30 += $balanceDue;
                        } elseif ($days <= 60) {
                            $b31_60 += $balanceDue;
                        } elseif ($days <= 90) {
                            $b61_90 += $balanceDue;
                        } else {
                            $b90_plus += $balanceDue;
                        }
                    }
                }

                if ($partyTotalDue > 0.01) {
                    $rows[] = [
                        'party_id' => $supplier->id,
                        'party_name' => $supplier->name,
                        'phone' => $supplier->phone ?: '-',
                        'bill_count' => $billCount,
                        'total_due' => $partyTotalDue,
                        'bucket_0_30' => $b0_30,
                        'bucket_31_60' => $b31_60,
                        'bucket_61_90' => $b61_90,
                        'bucket_90_plus' => $b90_plus,
                    ];
                }
            }
        }

        // Sort by highest total due first
        usort($rows, fn ($a, $b) => $b['total_due'] <=> $a['total_due']);

        $totals = [
            'total' => collect($rows)->sum('total_due'),
            'b0_30' => collect($rows)->sum('bucket_0_30'),
            'b31_60' => collect($rows)->sum('bucket_31_60'),
            'b61_90' => collect($rows)->sum('bucket_61_90'),
            'b90_plus' => collect($rows)->sum('bucket_90_plus'),
        ];

        return view('finance.reports.outstanding-aging', compact(
            'partyType', 'branchId', 'asOfDate', 'branches', 'rows', 'totals'
        ));
    }
}
