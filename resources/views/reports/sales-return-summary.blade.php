@extends('adminlte::page')

@section('title', 'Sales Return Summary')

@section('content_header')
    <h1>Sales Return Summary</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.sales-return-summary') }}" class="row align-items-end mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Return No, Bill No, Customer...">
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
                    <label class="small font-weight-bold mb-1">Location</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Locations</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Customer</label>
                    <select name="customer_id" class="form-control form-control-sm">
                        <option value="">All Customers</option>
                        @foreach ($customers as $cust)
                            <option value="{{ $cust->id }}" {{ request('customer_id') == $cust->id ? 'selected' : '' }}>{{ $cust->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Return Mode</label>
                    <select name="return_mode" class="form-control form-control-sm">
                        <option value="">All Modes</option>
                        @foreach ($returnModes as $rm)
                            <option value="{{ $rm }}" {{ request('return_mode') == $rm ? 'selected' : '' }}>{{ $rm }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.sales-return-summary') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Return Date</th>
                        <th>Return No</th>
                        <th>Customer</th>
                        <th>Bill No</th>
                        <th>Return Mode</th>
                        <th class="text-right">Return Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $return)
                        <tr>
                            <td>{{ $return->branch?->name }}</td>
                            <td>{{ $return->return_date->format('d-m-Y') }}</td>
                            <td>{{ $return->return_number }}</td>
                            <td>{{ $return->customer?->name }}</td>
                            <td>{{ $return->salesBill?->bill_number }}</td>
                            <td>{{ $return->return_mode }}</td>
                            <td class="text-right">{{ number_format($return->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No returns in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($returns->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="6">NetTotal</td>
                            <td class="text-right">{{ number_format($returns->sum('total'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
