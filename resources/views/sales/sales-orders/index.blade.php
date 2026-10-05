@extends('adminlte::page')

@section('title', 'Sales Orders')

@section('content_header')
    <h1>Sales Orders</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.sales-orders.index') }}" class="row align-items-end">
                @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                @if(request('direction'))
                    <input type="hidden" name="direction" value="{{ request('direction') }}">
                @endif
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Order No / Customer..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="all" @selected(request('branch_id') === 'all')>All Branches</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected(request('branch_id', session('active_branch_id', auth()->user()?->branch_id)) == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm select2">
                        <option value="">All Customers</option>
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}" @selected(request('customer_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-1 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm btn-block" title="Apply Filter"><i class="fas fa-filter"></i></button>
                    <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-outline-secondary btn-sm btn-block mt-1" title="Reset"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Sales Orders List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('sales.sales-orders.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> New Sales Order
                </a>
                <x-table-column-customizer table-key="sales.sales-orders" table-id="salesOrdersTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0" id="salesOrdersTable">
                <thead>
                    <tr>
                        <x-sortable-th column="order_number" label="Order No" />
                        <x-sortable-th column="order_date" label="Order Date" />
                        <th>Delivery Date</th>
                        <x-sortable-th column="customer" label="Customer" />
                        <th>Branch</th>
                        <x-sortable-th column="status" label="Status" />
                        <th class="text-right">Advance</th>
                        <x-sortable-th column="final_total" label="Total" align="right" />
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="font-weight-bold">
                                <a href="{{ route('sales.sales-orders.show', $order) }}">{{ $order->order_number }}</a>
                            </td>
                            <td>{{ $order->order_date?->format('d-m-Y') }}</td>
                            <td>{{ $order->expected_delivery_date ? $order->expected_delivery_date->format('d-m-Y') : '-' }}</td>
                            <td>{{ $order->customer?->name }}</td>
                            <td>{{ $order->branch?->name }}</td>
                            <td>
                                @php
                                    $badgeClass = match($order->status) {
                                        'Converted' => 'badge-success',
                                        'Partially Fulfilled' => 'badge-info',
                                        'Open' => 'badge-primary',
                                        'Cancelled' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-1">{{ $order->status }}</span>
                            </td>
                            <td class="text-right text-muted">₹{{ number_format($order->advance_amount, 2) }}</td>
                            <td class="font-weight-bold text-right text-success">₹{{ number_format($order->total, 2) }}</td>
                            <td class="text-right text-nowrap">
                                @if($order->status !== 'Converted' && $order->status !== 'Cancelled')
                                    <a href="{{ route('sales.delivery-notes.create', ['from_order' => $order->id]) }}" class="btn btn-xs btn-outline-info mr-1" title="Create Delivery Challan">
                                        <i class="fas fa-truck mr-1"></i> Dispatch
                                    </a>
                                    <a href="{{ route('sales.sales-bills.create', ['from_order' => $order->id]) }}" class="btn btn-xs btn-success mr-1 shadow-sm" title="1-Click Convert to Sales Bill">
                                        <i class="fas fa-cash-register mr-1"></i> Bill
                                    </a>
                                    <a href="{{ route('sales.sales-orders.edit', $order) }}" class="btn btn-xs btn-outline-secondary mr-1" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form action="{{ route('sales.sales-orders.destroy', $order) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this sales order?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-outline-danger" title="Cancel Order"><i class="fas fa-ban"></i></button>
                                    </form>
                                @else
                                    <a href="{{ route('sales.sales-orders.show', $order) }}" class="btn btn-xs btn-outline-info" title="View Details">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No sales orders found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer clearfix">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
@stop
