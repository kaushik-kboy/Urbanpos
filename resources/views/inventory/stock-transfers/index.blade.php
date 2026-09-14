@extends('adminlte::page')

@section('title', 'Stock Transfers')

@section('content_header')
    <h1>Stock Transfers (Dispatch)</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('inventory.stock-transfers.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> New Transfer
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
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
                            <td class="text-right">
                                <a href="{{ route('inventory.stock-transfers.show', $transfer) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-eye"></i></a>
                                @if ($transfer->status === 'Dispatched')
                                    <form action="{{ route('inventory.stock-transfers.cancel', $transfer) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this transfer? Source stock will be restored.')">
                                        @csrf
                                        <button class="btn btn-xs btn-outline-danger"><i class="fas fa-times"></i></button>
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
