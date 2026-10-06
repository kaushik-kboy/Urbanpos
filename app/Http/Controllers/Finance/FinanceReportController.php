<?php

namespace App\Http\Controllers\Finance;

use App\Exports\GenericReportExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class FinanceReportController extends Controller
{
    public function index()
    {
        return view('finance.reports.index');
    }

    protected function exportData(array $rows, array $headers, string $filename, string $format = 'excel')
    {
        if ($format === 'csv') {
            $csvFilename = preg_replace('/\.(xlsx|xls)$/i', '', $filename) . '.csv';
            return response()->stream(function () use ($rows, $headers) {
                $handle = fopen('php://output', 'w');
                fwrite($handle, "\xEF\xBB\xBF");
                fputcsv($handle, array_values($headers));
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$csvFilename}\"",
            ]);
        }

        $xlsxFilename = preg_replace('/\.(csv|xls)$/i', '', $filename) . '.xlsx';
        return Excel::download(
            new GenericReportExport($rows, array_values($headers)),
            $xlsxFilename
        );
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

        if ($ledger && in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [];
            $running = $openingBalance;
            $exportRows[] = [
                'Opening Balance',
                '-',
                '-',
                '-',
                '-',
                '-',
                number_format($openingBalance, 2),
            ];

            foreach ($lines as $line) {
                $deb = (float) $line->debit;
                $cred = (float) $line->credit;
                $running += ($deb - $cred);
                $exportRows[] = [
                    $line->journalEntry?->voucher_date ? \Carbon\Carbon::parse($line->journalEntry->voucher_date)->format('d-m-Y') : '',
                    $line->journalEntry?->voucher_number ?? '',
                    $line->journalEntry?->voucher_type ?? '',
                    $line->narration ?: ($line->journalEntry?->narration ?: ''),
                    $deb > 0 ? number_format($deb, 2) : '0.00',
                    $cred > 0 ? number_format($cred, 2) : '0.00',
                    number_format($running, 2),
                ];
            }

            return $this->exportData(
                $exportRows,
                ['Date', 'Voucher Number', 'Type', 'Narration', 'Debit', 'Credit', 'Balance'],
                'general-ledger-' . \Illuminate\Support\Str::slug($ledger->name) . '_' . $from . '_to_' . $to . '.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
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

        // Sargable range (same rows as whereDate()).
        $query = JournalEntry::with(['lines.ledger', 'branch'])
            ->where('voucher_date', '>=', $from.' 00:00:00')
            ->where('voucher_date', '<=', $to.' 23:59:59');

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

        // Totals cover the whole filtered period via one SQL aggregate; the page below only holds 100 vouchers
        // (was: every voucher + its lines + ledgers hydrated: ~0.7 ms and ~30 KB per voucher).
        $sums = DB::table('journal_entry_lines')
            ->whereIn('journal_entry_id', (clone $query)->reorder()->select('journal_entries.id')->toBase())
            ->selectRaw('COALESCE(SUM(debit), 0) as d, COALESCE(SUM(credit), 0) as c')->first();
        $totalDebit = (float) $sums->d;
        $totalCredit = (float) $sums->c;

        if (in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [];
            $query->chunk(500, function ($entriesChunk) use (&$exportRows) {
                foreach ($entriesChunk as $entry) {
                    $date = $entry->voucher_date ? \Carbon\Carbon::parse($entry->voucher_date)->format('d-m-Y') : '';
                    $branchName = $entry->branch?->name ?? 'All';
                    foreach ($entry->lines as $line) {
                        $exportRows[] = [
                            $date,
                            $entry->voucher_number,
                            $entry->voucher_type,
                            $branchName,
                            $line->ledger?->name ?? '',
                            $line->narration ?: ($entry->narration ?: ''),
                            (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '0.00',
                            (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '0.00',
                        ];
                    }
                }
            });

            return $this->exportData(
                $exportRows,
                ['Date', 'Voucher Number', 'Type', 'Branch', 'Ledger / Account', 'Narration', 'Debit', 'Credit'],
                'day-book_' . $from . '_to_' . $to . '.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
        }

        $entries = $query->orderBy('voucher_date')
            ->orderBy('id')
            ->paginate(100)->withQueryString();

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

        if (in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [];
            $totalD = 0;
            $totalC = 0;
            foreach ($ledgers as $l) {
                $totalD += $l->debit;
                $totalC += $l->credit;
                $exportRows[] = [
                    $l->name,
                    $l->ledger_group,
                    $l->debit > 0 ? number_format((float) $l->debit, 2) : '0.00',
                    $l->credit > 0 ? number_format((float) $l->credit, 2) : '0.00',
                ];
            }
            $exportRows[] = [
                'TOTAL',
                '',
                number_format($totalD, 2),
                number_format($totalC, 2),
            ];

            return $this->exportData(
                $exportRows,
                ['Ledger Name', 'Group', 'Debit', 'Credit'],
                'trial-balance_as_of_' . $asOf . '.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
        }

        return view('finance.reports.trial-balance', compact('ledgers', 'asOf'));
    }

    public function profitLoss(Request $request)
    {
        $from = $request->input('from', now()->startOfYear()->format('Y-m-d'));
        $to = $request->input('to', now()->format('Y-m-d'));
        $branchId = $request->input('branch_id');

        // Sales & Returns
        $salesQuery = \App\Models\SalesBill::whereDate('bill_date', '>=', $from)->whereDate('bill_date', '<=', $to)
            ->whereNotIn('status', ['Cancelled', 'Draft']);
        $returnsQuery = \App\Models\SalesReturn::whereDate('return_date', '>=', $from)->whereDate('return_date', '<=', $to);
        $purchaseQuery = \App\Models\PurchaseInvoice::whereDate('invoice_date', '>=', $from)->whereDate('invoice_date', '<=', $to)
            ->where('status', '!=', 'Cancelled');

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
        $expenseLedgers = Ledger::whereIn('ledger_group', ['Indirect Expense', 'Indirect Expenses', 'Direct Expenses', 'Administrative Expenses'])
            ->with(['lines' => fn ($q) => $q->whereHas('journalEntry', fn ($j) => $j->whereDate('voucher_date', '>=', $from)->whereDate('voucher_date', '<=', $to))])
            ->get()
            ->map(function ($l) {
                $amount = $l->lines->sum('debit') - $l->lines->sum('credit');
                return (object) ['name' => $l->name, 'group' => $l->ledger_group, 'amount' => $amount];
            })
            ->filter(fn ($r) => $r->amount > 0)
            ->values();

        $incomeLedgers = Ledger::whereIn('ledger_group', ['Indirect Income', 'Indirect Incomes', 'Direct Incomes'])
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

        if (in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [
                ['Gross Sales', 'Revenue', number_format($grossSales, 2)],
                ['Less: Sales Return', 'Revenue', number_format($salesReturn, 2)],
                ['Net Sales', 'Revenue', number_format($netSales, 2)],
                ['Gross Purchases', 'Direct Cost', number_format($grossPurchase, 2)],
                ['Gross Profit / (Loss)', 'Gross Profit', number_format($grossProfit, 2)],
            ];
            foreach ($incomeLedgers as $inc) {
                $exportRows[] = [$inc->name, $inc->group, number_format((float) $inc->amount, 2)];
            }
            $exportRows[] = ['Total Indirect Income', 'Income', number_format($totalIndirectIncomes, 2)];
            foreach ($expenseLedgers as $exp) {
                $exportRows[] = [$exp->name, $exp->group, number_format((float) $exp->amount, 2)];
            }
            $exportRows[] = ['Total Indirect Expenses', 'Expense', number_format($totalExpenses, 2)];
            $exportRows[] = ['NET PROFIT / (LOSS)', 'Bottom Line', number_format($netProfit, 2)];

            return $this->exportData(
                $exportRows,
                ['Particulars', 'Classification', 'Amount'],
                'profit-loss_' . $from . '_to_' . $to . '.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
        }

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

        if (in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [];
            $running = $openingBalance;
            $exportRows[] = ['Opening Balance', '-', '-', '-', '-', '-', '-', number_format($openingBalance, 2)];
            foreach ($lines as $line) {
                $deb = (float) $line->debit;
                $cred = (float) $line->credit;
                $running += ($deb - $cred);
                $exportRows[] = [
                    $line->journalEntry?->voucher_date ? \Carbon\Carbon::parse($line->journalEntry->voucher_date)->format('d-m-Y') : '',
                    $line->journalEntry?->voucher_number ?? '',
                    $line->journalEntry?->voucher_type ?? '',
                    $line->journalEntry?->branch?->name ?? '',
                    $line->ledger?->name ?? '',
                    $line->narration ?: ($line->journalEntry?->narration ?: ''),
                    $deb > 0 ? number_format($deb, 2) : '0.00',
                    $cred > 0 ? number_format($cred, 2) : '0.00',
                    number_format($running, 2),
                ];
            }
            $exportRows[] = ['Closing Balance', '-', '-', '-', '-', '-', '-', number_format($closingBalance, 2)];

            return $this->exportData(
                $exportRows,
                ['Date', 'Voucher Number', 'Type', 'Branch', 'Ledger', 'Narration', 'Debit', 'Credit', 'Balance'],
                'cash-bank-book_' . $from . '_to_' . $to . '.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
        }

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
        // Balance-due per document is computed in SQL and only documents that still owe money come back
        // (was: every bill ever posted hydrated with 3 eager-load levels: 31 s / 482 MB at 2.75 lakh bills).
        // The per-bill rules are unchanged; see agingBalances().
        $rows = $this->agingRows($partyType, $branchId, $asOfDate, $asOf);

        // Sort by highest total due first
        // Highest due first; equal totals fall back to name then id so the order never depends on DB row order.
        usort($rows, fn ($a, $b) => ($b['total_due'] <=> $a['total_due']) ?: strcmp((string) $a['party_name'], (string) $b['party_name']) ?: ($a['party_id'] <=> $b['party_id']));

        $totals = [
            'total' => collect($rows)->sum('total_due'),
            'b0_30' => collect($rows)->sum('bucket_0_30'),
            'b31_60' => collect($rows)->sum('bucket_31_60'),
            'b61_90' => collect($rows)->sum('bucket_61_90'),
            'b90_plus' => collect($rows)->sum('bucket_90_plus'),
        ];

        if (in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [];
            foreach ($rows as $r) {
                $exportRows[] = [
                    $r['party_name'],
                    $r['phone'],
                    $r['bill_count'],
                    number_format((float) $r['total_due'], 2),
                    number_format((float) $r['bucket_0_30'], 2),
                    number_format((float) $r['bucket_31_60'], 2),
                    number_format((float) $r['bucket_61_90'], 2),
                    number_format((float) $r['bucket_90_plus'], 2),
                ];
            }
            $exportRows[] = [
                'TOTAL',
                '',
                '',
                number_format((float) $totals['total'], 2),
                number_format((float) $totals['b0_30'], 2),
                number_format((float) $totals['b31_60'], 2),
                number_format((float) $totals['b61_90'], 2),
                number_format((float) $totals['b90_plus'], 2),
            ];

            return $this->exportData(
                $exportRows,
                ['Party Name', 'Phone', 'Bills Count', 'Total Due', '0-30 Days', '31-60 Days', '61-90 Days', '90+ Days'],
                'outstanding-aging-' . strtolower($partyType) . '_as_of_' . $asOfDate . '.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
        }

        return view('finance.reports.outstanding-aging', compact(
            'partyType', 'branchId', 'asOfDate', 'branches', 'rows', 'totals'
        ));
    }

    /**
     * @return array<int, array<string, mixed>> one row per customer/supplier that still owes money
     */
    private function agingRows(string $partyType, $branchId, string $asOfDate, \Carbon\Carbon $asOf): array
    {
        $isCustomer = $partyType === 'Customer';
        $balances = $this->agingBalances($isCustomer, $branchId, $asOfDate);

        $partyKey = $isCustomer ? 'customer_id' : 'supplier_id';
        $parties = collect($balances->pluck($partyKey)->filter()->unique()->values()->all())
            ->chunk(1000)
            ->flatMap(fn ($ids) => ($isCustomer ? Customer::class : \App\Models\Supplier::class)::whereIn('id', $ids->all())->get(['id', 'name', 'phone']))
            ->keyBy('id');

        $asOfTs = $asOf->getTimestamp();
        $rows = [];
        foreach ($balances->groupBy($partyKey) as $partyId => $docs) {
            $party = $parties->get($partyId);
            if (! $party) {
                continue;
            }

            // Money is summed in integer paise so the totals are exact and independent of row order
            // (float addition is not associative: the same bills could total 2362802.52 or 2362802.5200000005).
            $totalCents = $c0_30 = $c31_60 = $c61_90 = $c90_plus = 0;
            $billCount = 0;

            foreach ($docs as $doc) {
                $cents = (int) round(((float) $doc->balance) * 100);
                $totalCents += $cents;
                $billCount++;
                // whole elapsed days, floored at 0 (same result as Carbon diffInDays(), without parsing every row)
                $days = $doc->doc_date ? max(0, (int) floor(($asOfTs - strtotime($doc->doc_date)) / 86400)) : 0;

                if ($days <= 30) {
                    $c0_30 += $cents;
                } elseif ($days <= 60) {
                    $c31_60 += $cents;
                } elseif ($days <= 90) {
                    $c61_90 += $cents;
                } else {
                    $c90_plus += $cents;
                }
            }

            if ($totalCents / 100 > 0.01) {
                $rows[] = [
                    'party_id' => $party->id,
                    'party_name' => $party->name,
                    'phone' => $party->phone ?: '-',
                    'bill_count' => $billCount,
                    'total_due' => $totalCents / 100,
                    'bucket_0_30' => $c0_30 / 100,
                    'bucket_31_60' => $c31_60 / 100,
                    'bucket_61_90' => $c61_90 / 100,
                    'bucket_90_plus' => $c90_plus / 100,
                ];
            }
        }

        return $rows;
    }

    /**
     * Documents (sales bills / purchase invoices) with a balance due > 0.01 as of $asOfDate, one light row each:
     * (id, customer_id|supplier_id, doc_date, balance). Same rules as the original per-model PHP loop:
     *  - sales: paid-at-POS = sum of non-"Credit" tender payments; with no payment rows, a non-empty
     *    payment_type other than credit/none counts as fully paid; then minus active settlements (+discount)
     *    dated on/before as-of (DATE(), matching the model's 'date' cast).
     *  - purchases: only settlements reduce the balance.
     *  - Cancelled (and Draft for sales) documents are excluded; balance is rounded to 2 dp.
     */
    private function agingBalances(bool $isCustomer, $branchId, string $asOfDate): \Illuminate\Support\Collection
    {
        $table = $isCustomer ? 'sales_bills' : 'purchase_invoices';
        $dateCol = $isCustomer ? 'bill_date' : 'invoice_date';
        $partyCol = $isCustomer ? 'customer_id' : 'supplier_id';
        $morph = $isCustomer ? (new \App\Models\SalesBill)->getMorphClass() : (new \App\Models\PurchaseInvoice)->getMorphClass();
        $nextDay = \Carbon\Carbon::parse($asOfDate)->addDay()->format('Y-m-d');

        // Payments and settlements are aggregated ONCE per bill in derived tables and hash-joined, instead of
        // running correlated subqueries for each of the (up to millions of) bills.
        $settlements = DB::table('bill_settlement_items as si')
            ->join('bill_settlements as st', 'st.id', '=', 'si.bill_settlement_id')
            ->where('si.billable_type', $morph)
            ->whereRaw("COALESCE(st.status, 'Active') = 'Active'")
            ->whereRaw('DATE(st.settlement_date) <= ?', [$asOfDate])
            ->selectRaw('si.billable_id, SUM(si.settled_amount + si.discount_amount) as settled')
            ->groupBy('si.billable_id');

        $query = DB::table("{$table} as d")
            ->leftJoinSub($settlements, 'stl', 'stl.billable_id', '=', 'd.id')
            ->where("d.{$dateCol}", '<', $nextDay)
            ->when($branchId, fn ($q) => $q->where('d.branch_id', $branchId));

        $paid = '0';
        if ($isCustomer) {
            $payments = DB::table('sales_bill_payments as p')
                ->leftJoin('tender_types as tt', 'tt.id', '=', 'p.tender_type_id')
                ->selectRaw("p.sales_bill_id, COUNT(*) as n, SUM(CASE WHEN COALESCE(tt.type, '') <> 'Credit' THEN p.amount ELSE 0 END) as paid")
                ->groupBy('p.sales_bill_id');
            $query->leftJoinSub($payments, 'pay', 'pay.sales_bill_id', '=', 'd.id');
            $paid = "(CASE WHEN pay.n IS NOT NULL THEN pay.paid "
                ."WHEN d.payment_type IS NOT NULL AND CHAR_LENGTH(d.payment_type) > 0 AND d.payment_type <> '0' "
                ."AND LOWER(d.payment_type) COLLATE utf8mb4_bin NOT IN ('credit', 'none') THEN d.total ELSE 0 END)";
        }

        $query->selectRaw("d.id, d.{$partyCol}, d.{$dateCol} as doc_date, ROUND(d.total - {$paid} - COALESCE(stl.settled, 0), 2) as balance")
            ->havingRaw('balance > 0.01')
            ->orderBy('d.id');

        $isCustomer
            ? $query->whereNotIn('d.status', ['Cancelled', 'Draft'])
            : $query->where('d.status', '!=', 'Cancelled');

        return $query->get();
    }

    public function customerLoyalty(Request $request)
    {
        $branchId = $request->input('branch_id');
        $search = $request->input('search');

        $query = Customer::with(['category', 'branch', 'loyaltyPoints'])
            ->where('status', true);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $customers = $query->get()->map(function ($customer) {
            $earned = (float) $customer->loyaltyPoints->whereIn('type', ['Earned', 'Adjustment_Add'])->sum('points');
            $redeemed = (float) $customer->loyaltyPoints->whereIn('type', ['Redeemed', 'Adjustment_Deduct'])->sum('points');
            $reversals = (float) $customer->loyaltyPoints->where('type', 'Reversal')->sum('points');
            $balance = (float) max(0.0, round($earned - $redeemed + $reversals, 2));
            $lastTrans = $customer->loyaltyPoints->sortByDesc('created_at')->first();

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone ?: $customer->mobile,
                'category' => $customer->category?->name ?? 'Default',
                'enable_loyalty' => (bool) ($customer->category?->enable_loyalty ?? false),
                'earned' => $earned,
                'redeemed' => $redeemed,
                'balance' => $balance,
                'last_activity' => $lastTrans?->created_at?->format('d M Y, h:i A') ?? 'Never',
            ];
        });

        $customers = $customers->sortByDesc('balance')->values();

        $totals = [
            'total_customers' => $customers->count(),
            'total_balance_points' => $customers->sum('balance'),
            'total_earned_points' => $customers->sum('earned'),
            'total_redeemed_points' => $customers->sum('redeemed'),
        ];

        $branches = Branch::orderBy('name')->pluck('name', 'id');

        if (in_array($request->query('export'), ['excel', 'xlsx', 'csv'])) {
            $exportRows = [];
            foreach ($customers as $c) {
                $exportRows[] = [
                    $c['name'],
                    $c['phone'] ?: '-',
                    $c['category'],
                    $c['enable_loyalty'] ? 'Yes' : 'No',
                    number_format((float) $c['earned'], 2),
                    number_format((float) $c['redeemed'], 2),
                    number_format((float) $c['balance'], 2),
                    $c['last_activity'],
                ];
            }

            return $this->exportData(
                $exportRows,
                ['Customer Name', 'Phone', 'Category', 'Loyalty Active', 'Points Earned', 'Points Redeemed', 'Current Balance', 'Last Activity'],
                'customer-loyalty-report.xlsx',
                $request->query('export') === 'csv' ? 'csv' : 'excel'
            );
        }

        return view('finance.reports.customer-loyalty', compact('customers', 'totals', 'branches', 'branchId', 'search'));
    }
}
