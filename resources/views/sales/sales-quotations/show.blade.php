@extends('adminlte::page')

@section('title', 'Sales Quotation #' . $salesQuotation->quotation_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                <i class="fas fa-file-signature text-primary mr-2"></i>Sales Quotation: {{ $salesQuotation->quotation_number }}
                @php
                    $badgeClass = match($salesQuotation->status) {
                        'Converted' => 'badge-success',
                        'Accepted' => 'badge-primary',
                        'Sent' => 'badge-info',
                        'Cancelled' => 'badge-danger',
                        default => 'badge-secondary'
                    };
                @endphp
                <span class="badge {{ $badgeClass }} ml-2" style="font-size: 0.6em; vertical-align: middle;">{{ $salesQuotation->status }}</span>
            </h1>
        </div>
        <div>
            @if($salesQuotation->status !== 'Converted' && $salesQuotation->status !== 'Cancelled')
                <a href="{{ route('sales.sales-bills.create', ['from_quotation' => $salesQuotation->id]) }}" class="btn btn-success btn-sm mr-1 shadow-sm font-weight-bold">
                    <i class="fas fa-cash-register mr-1"></i> Convert to Sales Bill
                </a>
                <a href="{{ route('sales.sales-orders.create', ['from_quotation' => $salesQuotation->id]) }}" class="btn btn-primary btn-sm mr-1 shadow-sm">
                    <i class="fas fa-shopping-basket mr-1"></i> Convert to Order
                </a>
                <a href="{{ route('sales.sales-quotations.edit', $salesQuotation) }}" class="btn btn-outline-secondary btn-sm mr-1">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
                <form action="{{ route('sales.sales-quotations.destroy', $salesQuotation) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this quotation?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm mr-1"><i class="fas fa-ban mr-1"></i> Cancel</button>
                </form>
            @elseif($salesQuotation->converted_sales_bill_id)
                <a href="{{ route('sales.sales-bills.show', $salesQuotation->converted_sales_bill_id) }}" class="btn btn-outline-success btn-sm mr-1 font-weight-bold">
                    <i class="fas fa-external-link-alt mr-1"></i> View Converted Bill #{{ $salesQuotation->salesBill?->bill_number }}
                </a>
            @endif
            <button type="button" class="btn btn-outline-dark btn-sm mr-1" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <a href="{{ route('sales.sales-quotations.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title text-primary font-weight-bold mb-0"><i class="fas fa-user mr-2"></i>Customer Details</h5>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Customer:</td>
                            <td class="font-weight-bold">{{ $salesQuotation->customer?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Phone:</td>
                            <td>{{ $salesQuotation->customer?->phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email:</td>
                            <td>{{ $salesQuotation->customer?->email ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">GSTIN:</td>
                            <td>{{ $salesQuotation->customer?->gstin ?: '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-outline card-info shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title text-info font-weight-bold mb-0"><i class="fas fa-info-circle mr-2"></i>Quotation Meta</h5>
                </div>
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 140px;">Quotation Date:</td>
                            <td class="font-weight-bold">{{ $salesQuotation->quotation_date?->format('d-M-Y') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Valid Until:</td>
                            <td>{{ $salesQuotation->valid_until ? $salesQuotation->valid_until->format('d-M-Y') : 'Not specified' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Branch:</td>
                            <td>{{ $salesQuotation->branch?->name }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Sales Type:</td>
                            <td><span class="badge badge-light border">{{ $salesQuotation->sales_type }}</span></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header py-2">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-boxes mr-2 text-primary"></i>Quotation Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" style="width: 40px;">#</th>
                            <th>Item Description</th>
                            <th>Code</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Sell Price</th>
                            <th class="text-right">MRP</th>
                            <th class="text-right">Disc</th>
                            <th class="text-right">GST %</th>
                            <th class="text-right">Tax (₹)</th>
                            <th class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesQuotation->items as $idx => $line)
                            <tr>
                                <td class="text-center font-weight-bold">{{ $idx + 1 }}</td>
                                <td class="font-weight-bold">{{ $line->item?->name }}</td>
                                <td><span class="badge badge-light border">{{ $line->item?->item_code ?: '-' }}</span></td>
                                <td class="text-right font-weight-bold">{{ number_format($line->qty, 3) }}</td>
                                <td class="text-right">₹{{ number_format($line->sell_price, 2) }}</td>
                                <td class="text-right">₹{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-right text-danger">
                                    {{ $line->disc_amount > 0 ? '₹'.number_format($line->disc_amount, 2) : '-' }}
                                    @if($line->disc_percent > 0)
                                        <small class="text-muted">({{ $line->disc_percent }}%)</small>
                                    @endif
                                </td>
                                <td class="text-right">{{ $line->gst_percent }}%</td>
                                <td class="text-right text-info">₹{{ number_format($line->gst_tax_amount, 2) }}</td>
                                <td class="text-right font-weight-bold text-success">₹{{ number_format($line->net_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            @if($salesQuotation->remarks)
                <div class="card card-outline card-light shadow-sm">
                    <div class="card-header py-2">
                        <strong class="text-muted"><i class="fas fa-comment mr-1"></i> Remarks</strong>
                    </div>
                    <div class="card-body p-3">
                        <p class="mb-0">{{ $salesQuotation->remarks }}</p>
                    </div>
                </div>
            @endif
        </div>
        <div class="col-md-6">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-body p-3">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Total Items Qty:</td>
                            <td class="text-right font-weight-bold">{{ number_format($salesQuotation->items->sum('qty'), 3) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Discount:</td>
                            <td class="text-right text-danger font-weight-bold">-₹{{ number_format($salesQuotation->disc_amount, 2) }}</td>
                        </tr>
                        @if($salesQuotation->sales_type === 'Interstate')
                            <tr>
                                <td class="text-muted">Total IGST:</td>
                                <td class="text-right text-info font-weight-bold">₹{{ number_format($salesQuotation->total_igst, 2) }}</td>
                            </tr>
                        @else
                            <tr>
                                <td class="text-muted">Total CGST:</td>
                                <td class="text-right text-info font-weight-bold">₹{{ number_format($salesQuotation->total_cgst, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Total SGST:</td>
                                <td class="text-right text-info font-weight-bold">₹{{ number_format($salesQuotation->total_sgst, 2) }}</td>
                            </tr>
                        @endif
                        @if((float)$salesQuotation->round_off != 0)
                            <tr>
                                <td class="text-muted">Round Off:</td>
                                <td class="text-right font-weight-bold">₹{{ number_format($salesQuotation->round_off, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="border-top">
                            <td class="text-lg font-weight-bold">Grand Total:</td>
                            <td class="text-right text-lg text-success font-weight-bold">₹{{ number_format($salesQuotation->total, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
