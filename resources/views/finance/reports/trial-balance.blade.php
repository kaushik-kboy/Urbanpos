@extends('adminlte::page')

@section('title', 'Trial Balance')

@section('content_header')
    <h1>Trial Balance</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <label class="mr-2">As on</label>
                <div class="input-group input-group-sm mr-3">
                    <input type="text" name="as_of" value="{{ $asOf }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                    <div class="input-group-append">
                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0 font-weight-bold text-muted">Trial Balance Accounts</h5>
                <x-table-column-customizer table-key="finance.reports.trial-balance" table-id="trialBalanceTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>

            <table class="table table-sm table-striped" id="trialBalanceTable">
                <thead>
                    <tr>
                        <th>Ledger</th>
                        <th>Group</th>
                        <th class="text-right">Debit</th>
                        <th class="text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledgers as $row)
                        <tr>
                            <td>{{ $row->name }}</td>
                            <td>{{ $row->ledger_group }}</td>
                            <td class="text-right">{{ $row->debit > 0 ? number_format($row->debit, 2) : '' }}</td>
                            <td class="text-right">{{ $row->credit > 0 ? number_format($row->credit, 2) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No ledger balances yet.</td></tr>
                    @endforelse
                </tbody>
                @if ($ledgers->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="2">Total</td>
                            <td class="text-right">{{ number_format($ledgers->sum('debit'), 2) }}</td>
                            <td class="text-right">{{ number_format($ledgers->sum('credit'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
