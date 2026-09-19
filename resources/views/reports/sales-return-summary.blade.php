@extends('adminlte::page')

@section('title', 'Sales Return Summary')

@section('content_header')
    <h1>Sales Return Summary</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.sales-return-summary') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Return No, Bill No, Customer...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="from" value="{{ $from }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="to" value="{{ $to }}" class="form-control form-control-sm datepicker" placeholder="YYYY-MM-DD" autocomplete="off">
                        <div class="input-group-append">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                        </div>
                    </div>
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
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-undo mr-1"></i> Returns</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <button type="button" onclick="exportTableToCSV('sales-return-table', 'sales-return-summary-report')" class="btn btn-sm btn-outline-success mr-2"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.sales-return-summary" table-id="sales-return-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body">
            <table class="table table-sm table-striped" id="sales-return-table">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Return Date</th>
                        <th>Return No</th>
                        <th>Customer</th>
                        <th>Bill No</th>
                        <th>Return Mode</th>
                        <th class="text-right">Return Amount</th>
                        <th class="text-center" style="width: 130px;">Action</th>
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
                            <td class="text-center text-nowrap">
                                <a href="{{ route('sales.sales-returns.show', $return->id) }}" class="btn btn-xs btn-info" title="View Return" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="{{ route('sales.sales-returns.print', $return->id) }}" class="btn btn-xs btn-secondary ml-1" title="Print Return" target="_blank">
                                    <i class="fas fa-print"></i> Print
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No returns in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($returns->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="6">NetTotal</td>
                            <td class="text-right">{{ number_format($returns->sum('total'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
