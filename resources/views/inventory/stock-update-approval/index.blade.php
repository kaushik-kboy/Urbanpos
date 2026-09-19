@extends('adminlte::page')

@section('title', 'Stock Update Approval')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-clipboard-check text-primary mr-2"></i>Stock Update Approval</h1>
        @if ($pendingCount > 0)
            <span class="badge badge-warning font-weight-bold px-3 py-2" style="font-size: 0.95rem;">
                <i class="fas fa-clock mr-1"></i> {{ $pendingCount }} Pending Approval
            </span>
        @endif
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <form method="GET" class="form-inline">
                <label class="mr-2 font-weight-bold">Location:</label>
                <select name="branch_id" class="form-control form-control-sm mr-3">
                    <option value="">All Locations</option>
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <label class="mr-2 font-weight-bold">Status:</label>
                <select name="status" class="form-control form-control-sm mr-3">
                    <option value="All" @selected($status === 'All')>All Statuses</option>
                    <option value="Pending" @selected($status === 'Pending')>Pending</option>
                    <option value="Approved" @selected($status === 'Approved')>Approved</option>
                    <option value="Rejected" @selected($status === 'Rejected')>Rejected</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
            </form>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="inventory.stock-update-approval" table-id="stockUpdateApprovalTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0" id="stockUpdateApprovalTable">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 140px;">Update No</th>
                            <th>Entry Date</th>
                            <th>Location / Branch</th>
                            <th>Items Count</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th class="text-right" style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockUpdates as $entry)
                            <tr>
                                <td class="font-weight-bold">{{ $entry->update_number }}</td>
                                <td>{{ $entry->entry_date ? $entry->entry_date->format('d-m-Y') : '-' }}</td>
                                <td>{{ $entry->branch?->name }}</td>
                                <td>
                                    <span class="badge badge-info px-2 py-1">
                                        {{ $entry->items->count() }} items
                                    </span>
                                </td>
                                <td>
                                    @if ($entry->status === 'Pending')
                                        <span class="badge badge-warning px-2 py-1"><i class="fas fa-clock mr-1"></i>Pending</span>
                                    @elseif ($entry->status === 'Approved')
                                        <span class="badge badge-success px-2 py-1"><i class="fas fa-check-circle mr-1"></i>Approved</span>
                                    @else
                                        <span class="badge badge-danger px-2 py-1"><i class="fas fa-times-circle mr-1"></i>Rejected</span>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $entry->remarks ?: '-' }}</small></td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-xs btn-outline-info mr-1" data-toggle="collapse" data-target="#lines-{{ $entry->id }}">
                                        <i class="fas fa-eye mr-1"></i> Review Lines
                                    </button>
                                    @if ($entry->status === 'Pending')
                                        <form action="{{ route('inventory.stock-update-approval.approve', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Approve this stock update and adjust physical stock?')">
                                            @csrf
                                            <button class="btn btn-xs btn-success"><i class="fas fa-check mr-1"></i> Approve</button>
                                        </form>
                                        <form action="{{ route('inventory.stock-update-approval.reject', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Reject this stock update?')">
                                            @csrf
                                            <button class="btn btn-xs btn-danger"><i class="fas fa-times mr-1"></i> Reject</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            <tr class="collapse bg-light" id="lines-{{ $entry->id }}">
                                <td colspan="7" class="p-3">
                                    <div class="card card-outline card-secondary mb-0 shadow-sm">
                                        <div class="card-header py-2">
                                            <h6 class="m-0 font-weight-bold">Stock Adjustment Details for {{ $entry->update_number }}</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="bg-white">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Item Name</th>
                                                        <th>Barcode</th>
                                                        <th class="text-right">System Qty</th>
                                                        <th class="text-right">Physical Count</th>
                                                        <th class="text-right">Difference (Delta)</th>
                                                        <th class="text-right">Sell Price</th>
                                                        <th class="text-right">MRP</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($entry->items as $idx => $line)
                                                        <tr>
                                                            <td>{{ $idx + 1 }}</td>
                                                            <td>{{ $line->item?->name }}</td>
                                                            <td><code>{{ $line->item?->item_code ?: ($line->item?->ean_upc_code ?: '-') }}</code></td>
                                                            <td class="text-right">{{ number_format($line->system_qty_at_entry, 2) }}</td>
                                                            <td class="text-right font-weight-bold">{{ number_format($line->physical_qty, 2) }}</td>
                                                            <td class="text-right font-weight-bold {{ $line->delta_qty >= 0 ? 'text-success' : 'text-danger' }}">
                                                                {{ $line->delta_qty > 0 ? '+' : '' }}{{ number_format($line->delta_qty, 2) }}
                                                            </td>
                                                            <td class="text-right">₹ {{ number_format($line->sell_price, 2) }}</td>
                                                            <td class="text-right">₹ {{ number_format($line->mrp, 2) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-clipboard-check fa-2x mb-2 text-muted d-block"></i>
                                    No stock update records found for the selected filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">
            {{ $stockUpdates->links() }}
        </div>
    </div>
@stop
