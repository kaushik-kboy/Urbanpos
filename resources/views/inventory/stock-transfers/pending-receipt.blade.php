@extends('adminlte::page')

@section('title', 'Transfer In — Pending Receipt')

@section('content_header')
    <h1>Transfer In (Pending Receipt)</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <form method="GET" class="form-inline">
                <label class="mr-2 mb-0">Destination Branch:</label>
                <select name="branch_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">-- All Branches --</option>
                    @foreach ($branches as $bId => $bName)
                        <option value="{{ $bId }}" @selected($branchId == $bId)>{{ $bName }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Transfer No</th>
                        <th>Date</th>
                        <th>Source Branch</th>
                        <th>Destination Branch</th>
                        <th>Total Qty</th>
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
                            <td class="text-right">
                                <a href="{{ route('inventory.stock-transfers.receive-form', $transfer) }}" class="btn btn-xs btn-primary">
                                    <i class="fas fa-check"></i> Receive
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No transfers awaiting receipt.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $stockTransfers->links() }}</div>
    </div>
@stop
