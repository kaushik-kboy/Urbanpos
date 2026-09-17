@extends('adminlte::page')

@section('title', 'Purchase Order Summary')

@section('content_header')
    <h1>Purchase Order Summary</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.purchase-order-summary') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="PO No, Supplier...">
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
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $id => $name)
                            <option value="{{ $id }}" {{ request('supplier_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
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
                    <a href="{{ route('reports.purchase-order-summary') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-file-invoice mr-1"></i> Orders List</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="reports.purchase-order-summary" table-id="purchase-order-summary-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table id="purchase-order-summary-table" class="table table-sm table-striped table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th class="text-right">Total Qty</th>
                        <th class="text-right">Freight</th>
                        <th class="text-right">Total GST</th>
                        <th class="text-right">Total Amount</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td><strong>{{ $po->po_number }}</strong></td>
                            <td>{{ $po->po_date->format('d-m-Y') }}</td>
                            <td>{{ $po->supplier?->name }}</td>
                            <td>{{ $po->branch?->name }}</td>
                            <td class="text-right">{{ number_format($po->total_qty, 2) }}</td>
                            <td class="text-right">{{ number_format($po->freight, 2) }}</td>
                            <td class="text-right">{{ number_format($po->total_gst, 2) }}</td>
                            <td class="text-right font-weight-bold text-primary">{{ number_format($po->total, 2) }}</td>
                            <td class="text-center">
                                <span class="badge badge-{{ $po->status === 'Closed' ? 'success' : ($po->status === 'Cancelled' ? 'danger' : 'primary') }}">
                                    {{ $po->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-4">No purchase orders found in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Total: {{ $purchaseOrders->total() }} purchase orders</span>
            {{ $purchaseOrders->links() }}
        </div>
    </div>
@stop
