@extends('adminlte::page')

@section('title', 'Day Book')

@section('content_header')
    <h1>Day Book</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <label class="mr-2">From</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm mr-3">
                <label class="mr-2">To</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm mr-3">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </form>

            @forelse ($entries as $entry)
                <div class="mb-3 border rounded p-2">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $entry->voucher_date->format('d-m-Y') }} — {{ $entry->voucher_number }} ({{ $entry->voucher_type }})</strong>
                        <span>{{ $entry->branch?->name }}</span>
                    </div>
                    <table class="table table-sm mb-1 mt-2">
                        <thead>
                            <tr>
                                <th>Particulars</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entry->lines as $line)
                                <tr>
                                    <td>{{ $line->ledger?->name }}</td>
                                    <td class="text-right">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                                    <td class="text-right">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($entry->narration)
                        <div class="text-muted small">{{ $entry->narration }}</div>
                    @endif
                </div>
            @empty
                <p class="text-center text-muted py-3">No transactions in this period.</p>
            @endforelse
        </div>
    </div>
@stop
