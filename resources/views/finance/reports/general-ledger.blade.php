@extends('adminlte::page')

@section('title', 'General Ledger')

@section('content_header')
    <h1>General Ledger</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <label class="mr-2">Ledger</label>
                <select name="ledger_id" class="form-control form-control-sm mr-3">
                    <option value="">Select a ledger</option>
                    @foreach ($ledgers as $id => $name)
                        <option value="{{ $id }}" @selected((string) $ledgerId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                <label class="mr-2">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm mr-3">
                <label class="mr-2">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm mr-3">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </form>

            @if ($ledger)
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher No</th>
                            <th>Type</th>
                            <th>Narration</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="font-weight-bold">
                            <td colspan="6">Opening Balance</td>
                            <td class="text-right">{{ number_format(abs($openingBalance), 2) }} {{ $openingBalance >= 0 ? 'Dr' : 'Cr' }}</td>
                        </tr>
                        @php $running = $openingBalance; @endphp
                        @forelse ($lines as $line)
                            @php $running += $line->debit - $line->credit; @endphp
                            <tr>
                                <td>{{ $line->journalEntry->voucher_date->format('d-m-Y') }}</td>
                                <td>{{ $line->journalEntry->voucher_number }}</td>
                                <td>{{ $line->journalEntry->voucher_type }}</td>
                                <td>{{ $line->journalEntry->narration }}</td>
                                <td class="text-right">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                                <td class="text-right">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                                <td class="text-right">{{ number_format(abs($running), 2) }} {{ $running >= 0 ? 'Dr' : 'Cr' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-3">No transactions in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <p class="text-muted">Select a ledger to view its statement.</p>
            @endif
        </div>
    </div>
@stop
