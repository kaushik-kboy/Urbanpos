@extends('adminlte::page')

@section('title', 'GST Purchase Detail')

@section('content_header')
    <h1>GST Purchase Detail</h1>
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
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
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
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="btn btn-sm btn-outline-primary mr-2"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                {{-- Export CSV removed --}}
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
                        <th>GST No</th>
                        <th class="text-right">Taxable Amt</th>
                        <th class="text-right">Tax %</th>
                        <th class="text-right">CGST</th>
                        <th class="text-right">SGST</th>
                        <th class="text-right">IGST</th>
                        <th class="text-right">Freight</th>
                        <th class="text-right">TCS</th>
                        <th class="text-right">Total Amount</th>
                        <th>Branch</th>
                        <th class="text-center" style="width: 130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php
                            $taxable = $invoice->items->sum(fn ($i) => (float) $i->net_amount - (float) $i->gst_tax_amount);
                            $taxPercents = $invoice->items->pluck('gst_percent')->filter(fn ($p) => !is_null($p))->unique()->sort()->implode(', ');
                            $cgst = (float) ($invoice->total_cgst ?: $invoice->items->sum('cgst_amount'));
                            $sgst = (float) ($invoice->total_sgst ?: $invoice->items->sum('sgst_amount'));
                            $igst = (float) ($invoice->total_igst ?: $invoice->items->sum('igst_amount'));
                            $gstNo = $invoice->supplier_gstin ?: $invoice->supplier?->gst_no;
                        @endphp
                        <tr>
                            <td>{{ $invoice->invoice_date?->format('d-m-Y') }}</td>
                            <td><span class="font-weight-bold">{{ $invoice->invoice_number }}</span></td>
                            <td>{{ $invoice->supplier?->name ?? 'Unknown Supplier' }}</td>
                            <td><small class="text-muted">{{ $gstNo ?: '—' }}</small></td>
                            <td class="text-right">{{ number_format($taxable, 2) }}</td>
                            <td class="text-right">{{ $taxPercents ?: '0' }}%</td>
                            <td class="text-right">{{ number_format($cgst, 2) }}</td>
                            <td class="text-right">{{ number_format($sgst, 2) }}</td>
                            <td class="text-right">{{ number_format($igst, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $invoice->freight, 2) }}</td>
                            <td class="text-right">{{ number_format((float) $invoice->tcs_amount, 2) }}</td>
                            <td class="text-right font-weight-bold">{{ number_format((float) $invoice->total, 2) }}</td>
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
                        <tr><td colspan="14" class="text-center text-muted py-3">No purchases in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($invoices->hasPages())
                <div class="mt-2">{{ $invoices->links() }}</div>
            @endif
        </div>
    </div>
@stop
