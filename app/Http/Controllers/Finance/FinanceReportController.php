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

        $entries = JournalEntry::with(['lines.ledger', 'branch'])
            ->whereDate('voucher_date', '>=', $from)
            ->whereDate('voucher_date', '<=', $to)
            ->orderBy('voucher_date')
            ->orderBy('id')
            ->get();

        return view('finance.reports.day-book', compact('entries', 'from', 'to'));
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
}
