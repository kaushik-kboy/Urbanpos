@extends('adminlte::page')

@section('title', 'Sales Bills')

@section('content_header')
    <h1>Sales Bill</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('sales.sales-bills.index') }}" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Bill No / Customer" value="{{ request('search') }}">
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
                    <label class="small font-weight-bold mb-1">Invoice Type</label>
                    <select name="invoice_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($invoiceTypes as $type)
                            <option value="{{ $type }}" @selected(request('invoice_type') == $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 mt-1">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="fas fa-filter mr-1"></i> Apply Filter
                    </button>
                    <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-outline-secondary btn-sm ml-1 px-3">
                        <i class="fas fa-undo mr-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1"></i> Sales Bills List</h3>
            <a href="{{ route('sales.sales-bills.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Sales Bill
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Bill Date</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Invoice Type</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesBills as $bill)
                        <tr>
                            <td class="font-weight-bold">
                                <a href="{{ route('sales.sales-bills.show', $bill) }}">{{ $bill->bill_number }}</a>
                            </td>
                            <td>{{ $bill->bill_date->format('d-m-Y') }}</td>
                            <td>{{ $bill->customer?->name }}</td>
                            <td>{{ $bill->branch?->name }}</td>
                            <td>{{ $bill->invoice_type }}</td>
                            <td class="font-weight-bold text-success">₹{{ number_format($bill->total, 2) }}</td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('sales.sales-bills.receipt', $bill) }}" target="_blank" class="btn btn-xs btn-outline-success" title="Thermal Receipt (80mm)">
                                    <i class="fas fa-receipt"></i>
                                </a>
                                <a href="{{ route('sales.sales-bills.show', $bill) }}" class="btn btn-xs btn-outline-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('sales.sales-bills.edit', $bill) }}" class="btn btn-xs btn-outline-secondary" title="Edit">
                                    <i class="fas fa-pen"></i>
                                </a>
                                <form action="{{ route('sales.sales-bills.destroy', $bill) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this sales bill? Stock will be restored.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger" title="Cancel & Restore Stock"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $salesBills->links() }}</div>
    </div>
@stop
