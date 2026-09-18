@extends('adminlte::page')

@section('title', 'Purchase Detail')

@section('content_header')
    <h1>Purchase Detail</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.purchase-detail') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Inv No, Supplier Inv No, Supplier...">
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
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $supp)
                            <option value="{{ $supp->id }}" {{ request('supplier_id') == $supp->id ? 'selected' : '' }}>{{ $supp->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Purchase Type</label>
                    <select name="purchase_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($purchaseTypes as $pt)
                            <option value="{{ $pt }}" {{ request('purchase_type') == $pt ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $pt)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.purchase-detail') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-shopping-bag mr-1"></i> Purchase Invoices</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <x-table-column-customizer table-key="reports.purchase-detail" table-id="purchase-detail-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body">

            <table id="purchase-detail-table" class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Inv Date</th>
                        <th>Inv No</th>
                        <th>Supplier</th>
                        <th>Item</th>
                        <th class="text-right">Received Qty</th>
                        <th class="text-right">Purchase Rate</th>
                        <th class="text-right">MRP</th>
                        <th class="text-right">GST %</th>
                        <th class="text-right">Net Amount</th>
                        <th>Branch</th>
                        <th class="text-center" style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @forelse ($invoice->items as $line)
                            <tr>
                                <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->supplier?->name }}</td>
                                <td>{{ $line->item?->name }}</td>
                                <td class="text-right">{{ $line->qty }}</td>
                                <td class="text-right">{{ number_format($line->cost_price, 2) }}</td>
                                <td class="text-right">{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-right">{{ $line->gst_percent }}</td>
                                <td class="text-right">{{ number_format($line->net_amount, 2) }}</td>
                                <td>{{ $invoice->branch?->name }}</td>
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('purchase.purchase-invoices.show', $invoice->id) }}" class="btn btn-xs btn-info" title="View Purchase Invoice" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="{{ route('purchase.purchase-invoices.print', $invoice->id) }}" class="btn btn-xs btn-secondary ml-1" title="Print Invoice" target="_blank">
                                        <i class="fas fa-print"></i> Print
                                    </a>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-3">No purchases in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
