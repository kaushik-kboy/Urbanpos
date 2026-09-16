@extends('adminlte::page')

@section('title', 'Purchase Orders')

@section('content_header')
    <h1>Purchase Order</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('purchase.purchase-orders.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="PO No / Supplier..." value="{{ request('search') }}">
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
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>
                                {{ $supplier->name }}
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
                    <a href="{{ route('purchase.purchase-orders.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('purchase.purchase-orders.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Purchase Order
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>PO No</th>
                        <th>PO Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td>{{ $po->po_number }}</td>
                            <td>{{ $po->po_date->format('d-m-Y') }}</td>
                            <td>{{ $po->supplier?->name }}</td>
                            <td>{{ $po->branch?->name }}</td>
                            <td>{{ number_format($po->total, 2) }}</td>
                            <td><span class="badge badge-{{ $po->status === 'Open' ? 'success' : ($po->status === 'Cancelled' ? 'danger' : 'secondary') }}">{{ $po->status }}</span></td>
                            <td class="text-right">
                                @if ($po->status !== 'Cancelled')
                                    <a href="{{ route('purchase.purchase-receipt-notes.create', ['from_po' => $po->id]) }}" class="btn btn-xs btn-outline-info mr-1" title="Create Goods Receipt Note"><i class="fas fa-receipt"></i> GRN</a>
                                    <a href="{{ route('purchase.purchase-orders.edit', $po) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                    <form action="{{ route('purchase.purchase-orders.destroy', $po) }}" method="POST" class="d-inline po-cancel-form">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="reason" class="po-cancel-reason">
                                        <button type="button" class="btn btn-xs btn-outline-danger po-cancel-btn"><i class="fas fa-ban"></i> Cancel</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Cancelled</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $purchaseOrders->links() }}</div>
    </div>

    @push('js')
    <script>
        document.querySelectorAll('.po-cancel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const reason = prompt('Reason for cancelling this Purchase Order:');
                if (reason === null || reason.trim() === '') {
                    return;
                }
                const form = btn.closest('.po-cancel-form');
                form.querySelector('.po-cancel-reason').value = reason.trim();
                form.submit();
            });
        });
    </script>
    @endpush
@stop
