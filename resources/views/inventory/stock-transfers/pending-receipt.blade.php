@extends('adminlte::page')

@section('title', 'Transfer In — Pending Receipt & History')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0"><i class="fas fa-dolly mr-2 text-primary"></i>Transfer In (Pending Receipt & History)</h1>
        <div>
            <a href="{{ route('inventory.stock-transfers.index') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-exchange-alt mr-1"></i> View Transfer Out (Dispatches)
            </a>
            <a href="{{ route('inventory.stock-transfers.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus mr-1"></i> New Transfer Out
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-light py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                {{-- Status Filter Tabs --}}
                <div class="btn-group btn-group-sm mb-2 mb-md-0" role="group">
                    <a href="{{ route('inventory.stock-transfers.pending-receipt', ['status' => 'Dispatched', 'branch_id' => $branchId]) }}"
                       class="btn {{ $status === 'Dispatched' ? 'btn-primary font-weight-bold' : 'btn-outline-primary' }}">
                        <i class="fas fa-truck-loading mr-1"></i> Awaiting Receipt
                        <span class="badge badge-light ml-1">{{ $pendingCount }}</span>
                    </a>
                    <a href="{{ route('inventory.stock-transfers.pending-receipt', ['status' => 'Received', 'branch_id' => $branchId]) }}"
                       class="btn {{ $status === 'Received' ? 'btn-success font-weight-bold' : 'btn-outline-success' }}">
                        <i class="fas fa-history mr-1"></i> Received History
                        <span class="badge badge-light ml-1">{{ $receivedCount }}</span>
                    </a>
                    <a href="{{ route('inventory.stock-transfers.pending-receipt', ['status' => 'All', 'branch_id' => $branchId]) }}"
                       class="btn {{ $status === 'All' ? 'btn-secondary font-weight-bold' : 'btn-outline-secondary' }}">
                        <i class="fas fa-list mr-1"></i> All Transfers Inward
                        <span class="badge badge-light ml-1">{{ $allCount }}</span>
                    </a>
                </div>

                {{-- Branch Filter --}}
                <form method="GET" action="{{ route('inventory.stock-transfers.pending-receipt') }}" class="form-inline mb-0">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <label class="mr-2 mb-0 small font-weight-bold text-muted">Destination Branch:</label>
                    <select name="branch_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                        <option value="">-- All Branches --</option>
                        @foreach ($branches as $bId => $bName)
                            <option value="{{ $bId }}" @selected($branchId == $bId)>{{ $bName }}</option>
                        @endforeach
                    </select>
                    @if ($branchId)
                        <a href="{{ route('inventory.stock-transfers.pending-receipt', ['status' => $status]) }}" class="btn btn-outline-secondary btn-sm" title="Clear Branch Filter">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 130px;">Transfer No</th>
                            <th style="width: 110px;">Date</th>
                            <th>Source Branch (Transfer Out)</th>
                            <th>Destination Branch (Transfer In)</th>
                            <th style="width: 100px;" class="text-right">Total Qty</th>
                            <th style="width: 120px;" class="text-center">Status</th>
                            <th style="width: 170px;">Timeline</th>
                            <th style="width: 150px;" class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stockTransfers as $transfer)
                            <tr>
                                <td class="font-weight-bold align-middle">
                                    <a href="{{ route('inventory.stock-transfers.show', $transfer) }}" class="text-primary">
                                        {{ $transfer->transfer_number }}
                                    </a>
                                </td>
                                <td class="align-middle">{{ $transfer->transfer_date->format('d-m-Y') }}</td>
                                <td class="align-middle">
                                    <span class="badge badge-light border">
                                        <i class="fas fa-store-alt mr-1 text-muted"></i>{{ $transfer->fromBranch?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="align-middle">
                                    <span class="badge badge-light border">
                                        <i class="fas fa-map-marker-alt mr-1 text-muted"></i>{{ $transfer->toBranch?->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="text-right font-weight-bold align-middle">{{ number_format($transfer->total_qty, 3) }}</td>
                                <td class="text-center align-middle">
                                    @if ($transfer->status === 'Dispatched')
                                        <span class="badge badge-warning px-2 py-1">
                                            <i class="fas fa-clock mr-1"></i> Awaiting Receipt
                                        </span>
                                    @elseif ($transfer->status === 'Received')
                                        <span class="badge badge-success px-2 py-1">
                                            <i class="fas fa-check-circle mr-1"></i> Received
                                        </span>
                                    @elseif ($transfer->status === 'Cancelled')
                                        <span class="badge badge-secondary px-2 py-1">
                                            <i class="fas fa-times-circle mr-1"></i> Cancelled
                                        </span>
                                    @else
                                        <span class="badge badge-info px-2 py-1">{{ $transfer->status }}</span>
                                    @endif
                                </td>
                                <td class="small text-muted align-middle">
                                    @if ($transfer->received_at)
                                        <div><i class="fas fa-check text-success mr-1"></i> Received: {{ $transfer->received_at->format('d-m-Y H:i') }}</div>
                                    @endif
                                    @if ($transfer->dispatched_at)
                                        <div><i class="fas fa-truck text-muted mr-1"></i> Dispatched: {{ $transfer->dispatched_at->format('d-m-Y H:i') }}</div>
                                    @endif
                                </td>
                                <td class="text-right align-middle text-nowrap">
                                    @if ($transfer->status === 'Dispatched')
                                        <a href="{{ route('inventory.stock-transfers.receive-form', $transfer) }}" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check mr-1"></i> Receive
                                        </a>
                                    @endif
                                    <a href="{{ route('inventory.stock-transfers.print', $transfer) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print Delivery / Transfer Note">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="{{ route('inventory.stock-transfers.show', $transfer) }}" class="btn btn-sm btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-dolly fa-3x mb-3 text-secondary d-block"></i>
                                    <h6 class="font-weight-bold">No transfers found</h6>
                                    @if ($status === 'Dispatched')
                                        <p class="mb-2 text-muted">There are no incoming stock transfers currently awaiting receipt.</p>
                                        <a href="{{ route('inventory.stock-transfers.pending-receipt', ['status' => 'Received', 'branch_id' => $branchId]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-history mr-1"></i> View Received Transfer History
                                        </a>
                                    @else
                                        <p class="mb-0 text-muted">No stock transfer records match your current filter.</p>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($stockTransfers->hasPages())
            <div class="card-footer py-2">
                {{ $stockTransfers->links() }}
            </div>
        @endif
    </div>
@stop
