@extends('adminlte::page')

@section('title', 'Stock Transfer Report')

@section('content_header')
    <h1>Stock Transfer Report</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.stock-transfer-summary') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Transfer Number...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Source Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Source Locations</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Destination</label>
                    <select name="to_branch_id" class="form-control form-control-sm">
                        <option value="">All Destination Locations</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $toBranchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.stock-transfer-summary') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Transfer Number</th>
                        <th>Transfer Date</th>
                        <th>From Branch</th>
                        <th>To Branch</th>
                        <th class="text-right">Total Qty</th>
                        <th>Dispatched At</th>
                        <th>Received At</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transfers as $transfer)
                        <tr>
                            <td><strong>{{ $transfer->transfer_number }}</strong></td>
                            <td>{{ $transfer->transfer_date->format('d-m-Y') }}</td>
                            <td>{{ $transfer->fromBranch?->name }}</td>
                            <td>{{ $transfer->toBranch?->name }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($transfer->total_qty, 2) }}</td>
                            <td>{{ $transfer->dispatched_at ? $transfer->dispatched_at->format('d-m-Y H:i') : '-' }}</td>
                            <td>{{ $transfer->received_at ? $transfer->received_at->format('d-m-Y H:i') : '-' }}</td>
                            <td class="text-center">
                                <span class="badge badge-{{ $transfer->status === 'Received' ? 'success' : ($transfer->status === 'Cancelled' ? 'danger' : 'info') }}">
                                    {{ $transfer->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No stock transfers in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Total: {{ $transfers->total() }} transfers</span>
            {{ $transfers->links() }}
        </div>
    </div>
@stop
