@extends('adminlte::page')

@section('title', 'Sales Quotations')

@section('content_header')
    <h1 class="m-0 text-dark"><i class="fas fa-file-signature mr-2 text-primary"></i>Sales Quotations</h1>
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
            <h3 class="card-title text-muted text-sm"><i class="fas fa-filter mr-1"></i> Filter Quotations</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button>
            </div>
        </div>
        <div class="card-body py-2">
            <form method="GET" action="{{ route('sales.sales-quotations.index') }}" class="row align-items-end">
                <div class="col-md-3 form-group mb-2">
                    <label class="text-xs text-muted mb-1">Search Quotation / Customer</label>
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
                    <a href="{{ route('sales.sales-quotations.index') }}" class="btn btn-outline-secondary btn-sm ml-1 px-3">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1"></i> Quotations List</h3>
            <a href="{{ route('sales.sales-quotations.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus mr-1"></i> New Quotation
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Quotation No</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-right">Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($quotations as $quotation)
                        <tr>
                            <td class="font-weight-bold">
                                <a href="{{ route('sales.sales-quotations.show', $quotation) }}">{{ $quotation->quotation_number }}</a>
                            </td>
                            <td>{{ $quotation->quotation_date?->format('d-m-Y') }}</td>
                            <td>{{ $quotation->valid_until ? $quotation->valid_until->format('d-m-Y') : '-' }}</td>
                            <td>{{ $quotation->customer?->name }}</td>
                            <td>{{ $quotation->branch?->name }}</td>
                            <td>
                                @php
                                    $badgeClass = match($quotation->status) {
                                        'Converted' => 'badge-success',
                                        'Accepted' => 'badge-primary',
                                        'Sent' => 'badge-info',
                                        'Cancelled' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-1">{{ $quotation->status }}</span>
                            </td>
                            <td class="font-weight-bold text-right text-success">₹{{ number_format($quotation->total, 2) }}</td>
                            <td class="text-right text-nowrap">
                                @if($quotation->status !== 'Converted' && $quotation->status !== 'Cancelled')
                                    <a href="{{ route('sales.sales-bills.create', ['from_quotation' => $quotation->id]) }}" class="btn btn-xs btn-success mr-1 shadow-sm" title="1-Click Convert to Sales Bill">
                                        <i class="fas fa-cash-register mr-1"></i> Convert to Bill
                                    </a>
                                    <a href="{{ route('sales.sales-orders.create', ['from_quotation' => $quotation->id]) }}" class="btn btn-xs btn-outline-primary mr-1" title="Convert to Sales Order">
                                        <i class="fas fa-shopping-basket"></i>
                                    </a>
                                    <a href="{{ route('sales.sales-quotations.edit', $quotation) }}" class="btn btn-xs btn-outline-secondary mr-1" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form action="{{ route('sales.sales-quotations.destroy', $quotation) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this quotation?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-outline-danger" title="Cancel Quotation"><i class="fas fa-ban"></i></button>
                                    </form>
                                @else
                                    <a href="{{ route('sales.sales-quotations.show', $quotation) }}" class="btn btn-xs btn-outline-info" title="View Details">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No sales quotations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($quotations->hasPages())
            <div class="card-footer clearfix">
                {{ $quotations->links() }}
            </div>
        @endif
    </div>
@stop
