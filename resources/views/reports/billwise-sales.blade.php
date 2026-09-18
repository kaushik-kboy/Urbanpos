@extends('adminlte::page')

@section('title', 'Billwise Itemwise Sales Detail')

@section('content_header')
    <h1>Billwise Itemwise Sales Detail</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-file-invoice mr-1"></i> Sales Detail</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="reports.billwise-sales" table-id="billwise-sales-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.billwise-sales') }}" class="row align-items-end mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Bill No, Customer Name/Phone...">
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
                    <label class="small font-weight-bold mb-1">Invoice Type</label>
                    <select name="invoice_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($invoiceTypes as $it)
                            <option value="{{ $it }}" {{ request('invoice_type') == $it ? 'selected' : '' }}>{{ $it }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.billwise-sales') }}" class="btn btn-outline-secondary btn-sm mr-1"><i class="fas fa-undo"></i> Reset</a>
                    <button type="button" class="btn btn-outline-info btn-sm font-weight-bold" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print Report</button>
                </div>
            </form>

            <table id="billwise-sales-table" class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Bill Date</th>
                        <th>Bill No</th>
                        <th>Customer</th>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">MRP</th>
                        <th class="text-right">Net Amount</th>
                        <th>Branch</th>
                        <th class="text-center" style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        @forelse ($bill->items as $line)
                            <tr>
                                <td>{{ $bill->bill_date->format('d-m-Y') }}</td>
                                <td>{{ $bill->bill_number }}</td>
                                <td>{{ $bill->customer?->name }}</td>
                                <td>{{ $line->item?->name }}</td>
                                <td class="text-right">{{ $line->qty }}</td>
                                <td class="text-right">{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-right">{{ number_format($line->net_amount, 2) }}</td>
                                <td>{{ $bill->branch?->name }}</td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('sales.sales-bills.show', $bill->id) }}" class="btn btn-xs btn-info" title="View Bill" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="{{ route('sales.sales-bills.receipt', $bill->id) }}" class="btn btn-xs btn-secondary ml-1" title="Print Receipt" target="_blank">
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
