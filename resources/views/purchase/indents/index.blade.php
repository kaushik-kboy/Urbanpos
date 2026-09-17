@extends('adminlte::page')

@section('title', 'Purchase Indents')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Purchase Indents (Requisitions)</h1>
        <a href="{{ route('purchase.purchase-indents.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus-circle mr-1"></i> Raise Indent
        </a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('purchase.purchase-indents.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Indent #, Dept, Remarks..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Priority</label>
                    <select name="priority" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach ($priorities as $pri)
                            <option value="{{ $pri }}" {{ request('priority') == $pri ? 'selected' : '' }}>{{ $pri }}</option>
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
                <div class="col-md-12 text-right">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Filter</button>
                    <a href="{{ route('purchase.purchase-indents.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Purchase Indents</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="purchase.indents" table-id="indentsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped mb-0" id="indentsTable">
                <thead class="thead-light">
                    <tr>
                        <th>Indent #</th>
                        <th>Date / Required</th>
                        <th>Branch & Dept</th>
                        <th>Priority</th>
                        <th>Requested Qty</th>
                        <th>Approved Qty</th>
                        <th>Est. Amount</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($indents as $indent)
                        <tr>
                            <td>
                                <a href="{{ route('purchase.purchase-indents.show', $indent) }}" class="font-weight-bold text-primary">
                                    {{ $indent->indent_number }}
                                </a>
                                <div class="text-muted small">By: {{ $indent->requestedBy->name ?? 'Staff' }}</div>
                            </td>
                            <td>
                                <div>{{ $indent->indent_date->format('d-m-Y') }}</div>
                                @if ($indent->required_by_date)
                                    <small class="text-muted"><i class="far fa-clock"></i> By {{ $indent->required_by_date->format('d-m-Y') }}</small>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $indent->branch?->name }}</strong>
                                <div class="text-muted small">{{ $indent->department }}</div>
                            </td>
                            <td>
                                @php
                                    $pClass = match($indent->priority) {
                                        'Urgent' => 'danger',
                                        'High' => 'warning',
                                        'Medium' => 'info',
                                        default => 'secondary'
                                    };
                                @endphp
                                <span class="badge badge-{{ $pClass }} px-2 py-1">{{ $indent->priority }}</span>
                            </td>
                            <td>{{ number_format($indent->total_requested_qty, 2) }}</td>
                            <td>
                                @if ($indent->status === 'Pending')
                                    <span class="text-muted italic">—</span>
                                @else
                                    <span class="font-weight-bold text-success">{{ number_format($indent->total_approved_qty, 2) }}</span>
                                @endif
                            </td>
                            <td>₹{{ number_format($indent->total_estimated_amount, 2) }}</td>
                            <td>
                                @php
                                    $sClass = match($indent->status) {
                                        'Pending' => 'warning',
                                        'Approved' => 'success',
                                        'Rejected' => 'danger',
                                        'Converted' => 'primary',
                                        'Cancelled' => 'secondary',
                                        default => 'light'
                                    };
                                @endphp
                                <span class="badge badge-{{ $sClass }} px-2 py-1">{{ $indent->status }}</span>
                                @if ($indent->status === 'Converted' && $indent->purchaseOrder)
                                    <div class="small">
                                        <a href="{{ route('purchase.purchase-orders.edit', $indent->purchaseOrder) }}" class="text-info font-weight-bold">
                                            {{ $indent->purchaseOrder->po_number }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="text-right">
                                <div class="btn-group">
                                    <a href="{{ route('purchase.purchase-indents.show', $indent) }}" class="btn btn-xs btn-outline-primary" title="View Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('purchase.purchase-indents.print', $indent) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Print Slip">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    @if ($indent->status === 'Approved')
                                        <a href="{{ route('purchase.purchase-orders.create', ['from_indent' => $indent->id]) }}" class="btn btn-xs btn-success" title="Convert to Purchase Order">
                                            <i class="fas fa-cart-plus mr-1"></i> Convert to PO
                                        </a>
                                    @endif
                                    @if (!in_array($indent->status, ['Converted', 'Cancelled']))
                                        <form action="{{ route('purchase.purchase-indents.destroy', $indent) }}" method="POST" class="d-inline indent-cancel-form">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="cancellation_reason" class="indent-cancel-reason">
                                            <button type="button" class="btn btn-xs btn-outline-danger indent-cancel-btn" title="Cancel Indent">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-clipboard-list fa-2x mb-2 d-block text-gray-400"></i>
                                No purchase indents found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($indents->hasPages())
            <div class="card-footer py-2">
                {{ $indents->links() }}
            </div>
        @endif
    </div>

    @push('js')
    <script>
        document.querySelectorAll('.indent-cancel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const reason = prompt('Reason for cancelling this Purchase Indent:');
                if (reason === null || reason.trim() === '') {
                    return;
                }
                const form = btn.closest('.indent-cancel-form');
                form.querySelector('.indent-cancel-reason').value = reason.trim();
                form.submit();
            });
        });
    </script>
    @endpush
@stop
