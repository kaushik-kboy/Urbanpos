@extends('adminlte::page')

@section('title', 'Sales Orders')

@section('content_header')
    <h1 class="m-0 text-dark"><i class="fas fa-shopping-basket mr-2 text-primary"></i>Sales Orders</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card card-outline card-secondary mb-3">
        <div class="card-header py-2">
            <h3 class="card-title text-muted text-sm"><i class="fas fa-filter mr-1"></i> Filter Orders</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body py-2">
            <form method="GET" action="{{ route('sales.sales-orders.index') }}" class="row align-items-end">
                <div class="col-md-3 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Search Order / Customer</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search number or customer..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Date From</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Date To</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm select2">
                        <option value="">All Customers</option>
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}" @selected(request('customer_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        @foreach ($statuses as $st)
                            <option value="{{ $st }}" @selected(request('status') === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2 text-right">
                    <button type="submit" class="btn btn-primary btn-sm px-3"><i class="fas fa-search mr-1"></i> Filter</button>
                    <a href="{{ route('sales.sales-orders.index') }}" class="btn btn-outline-secondary btn-sm ml-1 px-3">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1"></i> Sales Orders List</h3>
            <a href="{{ route('sales.sales-orders.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus mr-1"></i> New Sales Order
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Order No</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-right">Advance</th>
                        <th class="text-right">Total</th>
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
