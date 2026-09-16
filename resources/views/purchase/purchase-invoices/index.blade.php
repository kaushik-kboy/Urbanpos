@extends('adminlte::page')

@section('title', 'Purchase Invoices')

@section('content_header')
    <h1>Purchase Invoice</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('purchase.purchase-invoices.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Inv / PO / GRN / Supplier..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from', now()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to', now()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $bId => $bName)
                            <option value="{{ $bId }}" {{ request('branch_id') == $bId ? 'selected' : '' }}>
                                {{ $bName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $sId => $sName)
                            <option value="{{ $sId }}" {{ request('supplier_id') == $sId ? 'selected' : '' }}>
                                {{ $sName }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Purchase Type</label>
                    <select name="purchase_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($purchaseTypes as $pt)
                            <option value="{{ $pt }}" {{ request('purchase_type') == $pt ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $pt)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply Filter</button>
                    <a href="{{ route('purchase.purchase-invoices.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('purchase.purchase-invoices.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Purchase Invoice
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th>PO No</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseInvoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                            <td>{{ $invoice->supplier?->name }}</td>
                            <td>{{ $invoice->branch?->name }}</td>
                            <td>{{ $invoice->purchaseOrder?->po_number }}</td>
                            <td>{{ number_format($invoice->total, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('purchase.purchase-invoices.edit', $invoice) }}" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i> Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No purchase invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $purchaseInvoices->links() }}</div>
    </div>
@stop
