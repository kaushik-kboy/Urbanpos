@extends('adminlte::page')

@section('title', 'Sales Returns')

@section('content_header')
    <h1>Sales Returns</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.sales-returns.index') }}" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Return / Bill / Customer" value="{{ request('search') }}">
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
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected(request('branch_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm">
                        <option value="">All Customers</option>
                        @foreach ($customers as $id => $name)
                            <option value="{{ $id }}" @selected(request('customer_id') == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Return Mode</label>
                    <select name="return_mode" class="form-control form-control-sm">
                        <option value="">All Modes</option>
                        @foreach ($returnModes as $mode)
                            <option value="{{ $mode }}" @selected(request('return_mode') == $mode)>{{ $mode }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 mt-1">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-filter mr-1"></i> Apply Filter
                    </button>
                    <a href="{{ route('sales.sales-returns.index') }}" class="btn btn-outline-secondary btn-sm ml-1 px-3">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Sales Returns List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('sales.sales-returns.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Sales Return
                </a>
                <x-table-column-customizer table-key="sales.sales-returns" table-id="salesReturnsTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0" id="salesReturnsTable">
                <thead>
                    <tr>
                        <th>Return No</th>
                        <th>Return Date</th>
                        <th>Customer</th>
                        <th>Bill No</th>
                        <th>Return Mode</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesReturns as $return)
                        <tr>
                            <td>{{ $return->return_number }}</td>
                            <td>{{ $return->return_date->format('d-m-Y') }}</td>
                            <td>{{ $return->customer?->name }}</td>
                            <td>{{ $return->salesBill?->bill_number }}</td>
                            <td>{{ $return->return_mode }}</td>
                            <td>{{ number_format($return->total, 2) }}</td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('sales.sales-returns.show', $return) }}" class="btn btn-xs btn-outline-info mr-1" title="View"><i class="fas fa-eye"></i> View</a>
                                <a href="{{ route('sales.sales-returns.print', $return) }}" target="_blank" class="btn btn-xs btn-outline-primary" title="Print"><i class="fas fa-print"></i> Print</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales returns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $salesReturns->links() }}</div>
    </div>
@stop
