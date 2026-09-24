@extends('adminlte::page')

@section('title', 'Purchase Invoices')

@section('content_header')
    <h1>Purchase Invoices</h1>
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Purchase Invoices</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('purchase.purchase-invoices.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Purchase Invoice
                </a>
                <x-table-column-customizer table-key="purchase.purchase-invoices" table-id="purchaseInvoicesTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0" id="purchaseInvoicesTable">
                <thead>
                    <tr>
                        <th>Supplier Inv No.</th>
                        <th>GRN No</th>
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
                            <td>
                                <strong>{{ $invoice->supplier_inv_no ?: '-' }}</strong>
                                @if($invoice->invoice_number)
                                    <br><small class="text-muted">{{ $invoice->invoice_number }}</small>
                                @endif
                            </td>
                            <td><span class="badge badge-light border text-dark font-weight-bold">{{ $invoice->grn_number ?: '-' }}</span></td>
                            <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                            <td>{{ $invoice->supplier?->name }}</td>
                            <td>{{ $invoice->branch?->name }}</td>
                            <td>{{ $invoice->purchaseOrder?->po_number ?: '-' }}</td>
                            <td>{{ number_format($invoice->total, 2) }}</td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('master.barcodes.print', ['purchase_invoice_id' => $invoice->id, 'format' => '50x25_2up']) }}" target="_blank" class="btn btn-xs btn-outline-warning mr-1" title="Print Barcode Stickers (TSC TE244)"><i class="fas fa-barcode"></i> Stickers</a>
                                <a href="{{ route('purchase.purchase-invoices.show', $invoice) }}" class="btn btn-xs btn-outline-info mr-1" title="View"><i class="fas fa-eye"></i> View</a>
                                <a href="{{ route('purchase.purchase-invoices.print', $invoice) }}" target="_blank" class="btn btn-xs btn-outline-primary mr-1" title="Print"><i class="fas fa-print"></i> Print</a>
                                <a href="{{ route('purchase.purchase-invoices.edit', $invoice) }}" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i> Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No purchase invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $purchaseInvoices->links() }}</div>
    </div>
@stop
