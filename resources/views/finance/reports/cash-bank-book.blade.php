@extends('adminlte::page')

@section('title', 'Cash & Bank Book')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-money-check-alt mr-2 text-primary"></i>Cash & Bank Book</h1>
        <div>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <a href="{{ route('finance.reports.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Reports Index
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('finance.reports.cash-bank-book') }}" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Account Category</label>
                    <select name="account_type" class="form-control form-control-sm">
                        <option value="All" @selected(request('account_type', 'All') === 'All')>All (Cash & Bank)</option>
                        <option value="Cash" @selected(request('account_type') === 'Cash')>Cash in Hand</option>
                        <option value="Bank" @selected(request('account_type') === 'Bank')>Bank Accounts</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Specific Ledger</label>
                    <select name="ledger_id" class="form-control form-control-sm">
                        <option value="">All Filtered Ledgers</option>
                        @foreach ($availableLedgers as $id => $name)
                            <option value="{{ $id }}" @selected(request('ledger_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $from }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $to }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected(request('branch_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box bg-light shadow-sm border mb-0">
                <span class="info-box-icon bg-secondary"><i class="fas fa-wallet"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Opening Balance</span>
                    <span class="info-box-number text-dark h5 mb-0">
                        ₹{{ number_format($openingBalance, 2) }}
                        <small class="text-muted">({{ $openingBalance >= 0 ? 'Dr' : 'Cr' }})</small>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box bg-light shadow-sm border mb-0">
                <span class="info-box-icon bg-success"><i class="fas fa-arrow-down"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Receipts (Dr)</span>
                    <span class="info-box-number text-success h5 mb-0">₹{{ number_format($totalDebit, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box bg-light shadow-sm border mb-0">
                <span class="info-box-icon bg-danger"><i class="fas fa-arrow-up"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Payments (Cr)</span>
                    <span class="info-box-number text-danger h5 mb-0">₹{{ number_format($totalCredit, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box bg-light shadow-sm border mb-0">
                <span class="info-box-icon bg-primary"><i class="fas fa-landmark"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Closing Balance</span>
                    <span class="info-box-number {{ $closingBalance >= 0 ? 'text-primary' : 'text-danger' }} h5 mb-0">
                        ₹{{ number_format(abs($closingBalance), 2) }}
                        <small class="text-muted">({{ $closingBalance >= 0 ? 'Dr' : 'Cr' }})</small>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Transactions Table --}}
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header py-2">
            <h3 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-book mr-1 text-primary"></i> Transactions Log ({{ count($lines) }} Entries)
            </h3>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 105px;">Date</th>
                        <th>Voucher No</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Account</th>
                        <th>Narration</th>
                        <th class="text-right" style="width: 130px;">Debit / Receipt (₹)</th>
                        <th class="text-right" style="width: 130px;">Credit / Payment (₹)</th>
                        <th class="text-right" style="width: 140px;">Balance (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-secondary font-weight-bold">
                        <td>{{ \Carbon\Carbon::parse($from)->format('d-m-Y') }}</td>
                        <td colspan="5" class="text-muted font-italic">Opening Balance as of {{ \Carbon\Carbon::parse($from)->format('d-m-Y') }}</td>
                        <td class="text-right">—</td>
                        <td class="text-right">—</td>
                        <td class="text-right">
                            ₹{{ number_format(abs($openingBalance), 2) }} {{ $openingBalance >= 0 ? 'Dr' : 'Cr' }}
                        </td>
                    </tr>
                    @php
                        $running = $openingBalance;
                    @endphp
                    @forelse ($lines as $line)
                        @php
                            $debit = (float) $line->debit;
                            $credit = (float) $line->credit;
                            $running += ($debit - $credit);
                        @endphp
                        <tr>
                            <td>{{ optional($line->journalEntry?->voucher_date)->format('d-m-Y') }}</td>
                            <td class="font-weight-bold">{{ $line->journalEntry?->voucher_number }}</td>
                            <td>
                                <span class="badge badge-light border">{{ $line->journalEntry?->voucher_type }}</span>
                            </td>
                            <td>{{ $line->journalEntry?->branch?->name ?? '—' }}</td>
                            <td>{{ $line->ledger?->name }}</td>
                            <td class="text-muted small">{{ $line->journalEntry?->narration ?? '—' }}</td>
                            <td class="text-right text-success font-weight-bold">
                                {{ $debit > 0 ? '₹' . number_format($debit, 2) : '—' }}
                            </td>
                            <td class="text-right text-danger font-weight-bold">
                                {{ $credit > 0 ? '₹' . number_format($credit, 2) : '—' }}
                            </td>
                            <td class="text-right font-weight-bold">
                                ₹{{ number_format(abs($running), 2) }} {{ $running >= 0 ? 'Dr' : 'Cr' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                No cash or bank transactions recorded for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="6" class="text-right">Period Totals:</td>
                        <td class="text-right text-success h6 mb-0">₹{{ number_format($totalDebit, 2) }}</td>
                        <td class="text-right text-danger h6 mb-0">₹{{ number_format($totalCredit, 2) }}</td>
                        <td class="text-right text-primary h6 mb-0">
                            ₹{{ number_format(abs($closingBalance), 2) }} {{ $closingBalance >= 0 ? 'Dr' : 'Cr' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@stop
