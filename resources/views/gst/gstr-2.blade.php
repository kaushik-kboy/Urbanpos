@extends('adminlte::page')

@section('title', 'GSTR-2 Inward Purchase Register')

@section('content_header')
<div class="d-flex flex-wrap justify-content-between align-items-center">
    <div>
        <h1 class="font-weight-bold text-dark mb-0">
            <i class="fas fa-shopping-cart text-info mr-2"></i> GSTR-2: Inward Supplies Register
        </h1>
        <small class="text-muted">
            CA-Ready Purchase Tax Register &bull; <strong>{{ $companyName }}</strong> &bull; GSTIN: <code>{{ $gstin }}</code>
        </small>
    </div>
    <div class="d-flex align-items-center mt-2 mt-md-0">
        <a href="{{ route('tools.gst.gstr-2.export', request()->all()) }}" class="btn btn-success btn-sm shadow-sm font-weight-bold mr-2">
            <i class="fas fa-file-csv mr-1"></i> Export Inward Register CSV
        </a>
        <a href="{{ route('tools.gst.gstr-3b.page') }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold mr-2">
            <i class="fas fa-balance-scale mr-1"></i> GSTR-3B (Offset)
        </a>
        <a href="{{ route('tools.gst.gstr-1.page') }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold mr-2">
            <i class="fas fa-file-invoice-dollar mr-1"></i> GSTR-1 (Sales)
        </a>
        <a href="{{ route('tools.integrations-gst', ['view' => 'returns']) }}" class="btn btn-outline-secondary btn-sm shadow-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> GST Dashboard
        </a>
    </div>
</div>
@stop

@section('content')
<div class="container-fluid px-0">

    {{-- Filter Toolbar --}}
    <div class="card card-outline card-info shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('tools.gst.gstr-2.page') }}" class="form-inline d-flex justify-content-between flex-wrap">
                <div class="d-flex align-items-center my-1">
                    <span class="font-weight-bold text-dark mr-2 small"><i class="far fa-calendar-alt text-info mr-1"></i> Return Period:</span>
                    <input type="date" name="from_date" class="form-control form-control-sm mr-2 font-weight-bold" value="{{ $fromDate }}">
                    <span class="mr-2 text-muted">to</span>
                    <input type="date" name="to_date" class="form-control form-control-sm mr-2 font-weight-bold" value="{{ $toDate }}">
                    <button type="submit" class="btn btn-info btn-sm font-weight-bold px-3">
                        <i class="fas fa-filter mr-1"></i> Filter Inward Bills
                    </button>
                </div>
                <div class="d-flex align-items-center my-1">
                    <span class="badge badge-success px-2 py-1 mr-2 font-weight-bold">
                        <i class="fas fa-check mr-1"></i> {{ $total_registered }} Registered Suppliers
                    </span>
                    <span class="badge badge-secondary px-2 py-1 font-weight-bold">
                        {{ $total_unregistered }} Unregistered
                    </span>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="row">
        <div class="col-md-2 col-sm-4 col-6 mb-3">
            <div class="card shadow-sm border-left-info py-2 mb-0">
                <div class="card-body p-2 text-center">
                    <div class="text-muted small font-weight-bold text-uppercase">Invoices</div>
                    <div class="h4 font-weight-bold text-dark mb-0">{{ $total_invoices }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6 mb-3">
            <div class="card shadow-sm border-left-primary py-2 mb-0">
                <div class="card-body p-2 text-center">
                    <div class="text-muted small font-weight-bold text-uppercase">Total Inward Value</div>
                    <div class="h5 font-weight-bold text-primary mb-0">₹{{ number_format($total_inv_value, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6 mb-3">
            <div class="card shadow-sm border-left-dark py-2 mb-0">
                <div class="card-body p-2 text-center">
                    <div class="text-muted small font-weight-bold text-uppercase">Taxable Value</div>
                    <div class="h5 font-weight-bold text-dark mb-0">₹{{ number_format($total_taxable, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6 mb-3">
            <div class="card shadow-sm border-left-success py-2 mb-0">
                <div class="card-body p-2 text-center">
                    <div class="text-muted small font-weight-bold text-uppercase">Input GST (Gross)</div>
                    <div class="h5 font-weight-bold text-success mb-0">₹{{ number_format($total_gst, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6 mb-3">
            <div class="card shadow-sm border-left-danger py-2 mb-0">
                <div class="card-body p-2 text-center">
                    <div class="text-muted small font-weight-bold text-uppercase">Debit Note Reversals</div>
                    <div class="h5 font-weight-bold text-danger mb-0">₹{{ number_format($reversed_gst, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-4 col-6 mb-3">
            <div class="card shadow-sm bg-success text-white py-2 mb-0">
                <div class="card-body p-2 text-center">
                    <div class="text-white-50 small font-weight-bold text-uppercase">Net Eligible ITC</div>
                    <div class="h4 font-weight-bold mb-0">₹{{ number_format($net_eligible_itc, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Purchase Invoices Register Table --}}
    <div class="card card-outline card-info shadow-sm mb-4">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-file-invoice mr-1 text-info"></i> Inward Purchase Invoices (Eligible ITC Register)
            </h5>
            <span class="badge badge-info">{{ $purchases->count() }} Records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0">
                    <thead class="thead-dark small text-center">
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th class="text-left">Supplier Name</th>
                            <th>GSTIN</th>
                            <th>Invoice No</th>
                            <th>Invoice Date</th>
                            <th class="text-right">Invoice Total (₹)</th>
                            <th class="text-right">Taxable Value (₹)</th>
                            <th class="text-right">IGST (₹)</th>
                            <th class="text-right">CGST (₹)</th>
                            <th class="text-right">SGST (₹)</th>
                            <th class="text-right">Total GST (₹)</th>
                            <th>ITC Status</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($purchases as $index => $inv)
                        <tr>
                            <td class="text-center text-muted">{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $inv['supplier_name'] }}</strong>
                            </td>
                            <td class="text-center">
                                @if($inv['supplier_gstin'])
                                    <code>{{ $inv['supplier_gstin'] }}</code>
                                @else
                                    <span class="badge badge-light border text-muted">Unregistered</span>
                                @endif
                            </td>
                            <td class="text-center font-weight-bold">{{ $inv['invoice_number'] }}</td>
                            <td class="text-center text-muted">{{ date('d M Y', strtotime($inv['invoice_date'])) }}</td>
                            <td class="text-right font-weight-bold">₹{{ number_format($inv['invoice_value'], 2) }}</td>
                            <td class="text-right">₹{{ number_format($inv['taxable_value'], 2) }}</td>
                            <td class="text-right text-info">₹{{ number_format($inv['igst'], 2) }}</td>
                            <td class="text-right text-primary">₹{{ number_format($inv['cgst'], 2) }}</td>
                            <td class="text-right text-success">₹{{ number_format($inv['sgst'], 2) }}</td>
                            <td class="text-right font-weight-bold text-success">₹{{ number_format($inv['total_gst'], 2) }}</td>
                            <td class="text-center">
                                <span class="badge badge-success px-2 py-1">Eligible</span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="12" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i> No purchase invoices found in the selected period.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if($purchases->isNotEmpty())
                    <tfoot class="thead-light font-weight-bold text-right small">
                        <tr>
                            <td colspan="5" class="text-center font-weight-bold">TOTAL REGISTER SUMMARY:</td>
                            <td>₹{{ number_format($total_inv_value, 2) }}</td>
                            <td>₹{{ number_format($total_taxable, 2) }}</td>
                            <td class="text-info">₹{{ number_format($total_igst, 2) }}</td>
                            <td class="text-primary">₹{{ number_format($total_cgst, 2) }}</td>
                            <td class="text-success">₹{{ number_format($total_sgst, 2) }}</td>
                            <td class="text-success">₹{{ number_format($total_gst, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Purchase Debit Notes / Returns Table --}}
    @if($debit_notes->isNotEmpty())
    <div class="card card-outline card-danger shadow-sm mb-4">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold text-danger mb-0">
                <i class="fas fa-undo mr-1"></i> Inward Debit Notes (ITC Reversals)
            </h5>
            <span class="badge badge-danger">{{ $debit_notes->count() }} Returns</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light small text-center">
                        <tr>
                            <th>#</th>
                            <th class="text-left">Supplier Name</th>
                            <th>GSTIN</th>
                            <th>Return / DN No</th>
                            <th>Date</th>
                            <th class="text-right">Return Value (₹)</th>
                            <th class="text-right">Taxable (₹)</th>
                            <th class="text-right">IGST Reversed</th>
                            <th class="text-right">CGST Reversed</th>
                            <th class="text-right">SGST Reversed</th>
                            <th class="text-right">Total Reversal (₹)</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @foreach($debit_notes as $idx => $dn)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>{{ $dn['supplier_name'] }}</td>
                            <td class="text-center"><code>{{ $dn['supplier_gstin'] ?: '-' }}</code></td>
                            <td class="text-center font-weight-bold">{{ $dn['return_number'] }}</td>
                            <td class="text-center">{{ date('d M Y', strtotime($dn['return_date'])) }}</td>
                            <td class="text-right font-weight-bold">₹{{ number_format($dn['return_value'], 2) }}</td>
                            <td class="text-right">₹{{ number_format($dn['taxable_value'], 2) }}</td>
                            <td class="text-right text-danger">₹{{ number_format($dn['igst'], 2) }}</td>
                            <td class="text-right text-danger">₹{{ number_format($dn['cgst'], 2) }}</td>
                            <td class="text-right text-danger">₹{{ number_format($dn['sgst'], 2) }}</td>
                            <td class="text-right font-weight-bold text-danger">₹{{ number_format($dn['total_gst'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>
@stop
