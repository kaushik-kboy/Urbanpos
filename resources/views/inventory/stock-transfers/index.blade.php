@extends('adminlte::page')

@section('title', 'Stock Transfers')

@section('content_header')
    <h1>Stock Transfers (Dispatch)</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventory.stock-transfers.index') }}" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Transfer No..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_from" class="form-control form-control-sm datepicker" value="{{ request('date_from') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_to" class="form-control form-control-sm datepicker" value="{{ request('date_to') }}" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Branch</label>
                    <select name="from_branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('from_branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Branch</label>
                    <select name="to_branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('to_branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" {{ request('status') == $st ? 'selected' : '' }}>
                                {{ $st }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply Filter</button>
                    <a href="{{ route('inventory.stock-transfers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Stock Transfers</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('inventory.stock-transfers.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> New Transfer
                </a>
                <x-table-column-customizer table-key="inventory.stock-transfers" table-id="stockTransfersTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0" id="stockTransfersTable">
                <thead>
                    <tr>
                        <th>Transfer No</th>
                        <th>Date</th>
                        <th>From Branch</th>
                        <th>To Branch</th>
                        <th>Total Qty</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stockTransfers as $transfer)
                        <tr>
                            <td>{{ $transfer->transfer_number }}</td>
                            <td>{{ $transfer->transfer_date->format('d-m-Y') }}</td>
                            <td>{{ $transfer->fromBranch?->name }}</td>
                            <td>{{ $transfer->toBranch?->name }}</td>
                            <td>{{ $transfer->total_qty }}</td>
                            <td>{{ number_format($transfer->total_value, 2) }}</td>
                            <td>
                                @php
                                    $badge = match ($transfer->status) {
                                        'Dispatched' => 'warning',
                                        'Received' => 'success',
                                        'Cancelled' => 'secondary',
                                        default => 'light',
                                    };
                                @endphp
                                <span class="badge badge-{{ $badge }}">{{ $transfer->status }}</span>
                            </td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('inventory.stock-transfers.show', $transfer) }}" class="btn btn-xs btn-outline-info mr-1" title="View"><i class="fas fa-eye"></i> View</a>
                                <a href="{{ route('inventory.stock-transfers.print', $transfer) }}" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="Print"><i class="fas fa-print"></i> Print</a>
                                @if ($transfer->status === 'Dispatched')
                                    <form action="{{ route('inventory.stock-transfers.cancel', $transfer) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this transfer? Source stock will be restored.')">
                                        @csrf
                                        <button class="btn btn-xs btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No stock transfers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $stockTransfers->links() }}</div>
    </div>
@stop
