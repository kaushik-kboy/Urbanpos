@extends('adminlte::page')

@section('title', 'Sales Bill ' . $salesBill->bill_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <i class="fas fa-file-invoice text-primary mr-2"></i>Sales Bill: {{ $salesBill->bill_number }}
            @if ($salesBill->status === 'Posted')
                <span class="badge badge-success">Posted</span>
            @elseif ($salesBill->status === 'Cancelled')
                <span class="badge badge-danger">Cancelled</span>
            @else
                <span class="badge badge-secondary">{{ $salesBill->status }}</span>
            @endif
        </h1>
        <div>
            <a href="{{ route('sales.sales-bills.receipt', $salesBill) }}" target="_blank" class="btn btn-success btn-sm mr-1 shadow-sm">
                <i class="fas fa-receipt mr-1"></i> Thermal Receipt (80mm)
            </a>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print (A4)
            </button>
            @if ($salesBill->status !== 'Cancelled')
                <a href="{{ route('sales.sales-bills.edit', $salesBill) }}" class="btn btn-outline-primary btn-sm mr-1">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
                <form action="{{ route('sales.sales-bills.destroy', $salesBill) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this sales bill? Stock will be restored and journal entry reversed.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm mr-1">
                        <i class="fas fa-ban mr-1"></i> Cancel Bill
                    </button>
                </form>
            @endif
            <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Bills
            </a>
        </div>
    </div>
@stop

@section('content')
    {{-- Header Details Card --}}
    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold mb-0 text-dark">
                <i class="fas fa-info-circle mr-1 text-info"></i> Bill Information
            </h5>
        </div>
        <div class="card-body py-3">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Bill Number</span>
                    <strong class="h6 mb-0">{{ $salesBill->bill_number }}</strong>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Bill Date</span>
                    <strong>{{ $salesBill->bill_date->format('d-m-Y') }}</strong>
                    <small class="text-muted">({{ $salesBill->created_at ? $salesBill->created_at->format('h:i A') : '' }})</small>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Customer</span>
                    <strong>{{ $salesBill->customer?->name ?? 'Walk-in Customer' }}</strong>
                    @if ($salesBill->customer?->phone)
                        <span class="text-muted small d-block">{{ $salesBill->customer->phone }}</span>
                    @endif
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Branch</span>
                    <strong>{{ $salesBill->branch?->name ?? 'Main Branch' }}</strong>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Invoice Type</span>
                    <span class="badge badge-light border">{{ $salesBill->invoice_type }}</span>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Sales Type</span>
                    <span class="badge badge-info">{{ $salesBill->sales_type }}</span>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Delivery Type</span>
                    <span>{{ $salesBill->delivery_type ?? 'Delivered' }}</span>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block">Payment Mode</span>
                    <strong>{{ $salesBill->payment_type ?? 'None' }}</strong>
                </div>
            </div>
            @if ($salesBill->remarks)
                <div class="alert alert-light border mt-2 mb-0 py-2">
                    <strong>Remarks:</strong> {{ $salesBill->remarks }}
                </div>
            @endif
        </div>
    </div>

    {{-- Items Table Card --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold mb-0 text-dark">
                <i class="fas fa-boxes mr-1 text-primary"></i> Billed Items ({{ count($salesBill->items) }} Lines)
            </h5>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-bordered table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th style="width: 140px;">Code</th>
                        <th>Item Description</th>
                        <th style="width: 110px;">Exp Date</th>
                        <th style="width: 90px;" class="text-right">Qty</th>
                        <th style="width: 110px;" class="text-right">Rate (₹)</th>
                        <th style="width: 100px;" class="text-right">MRP (₹)</th>
                        <th style="width: 90px;" class="text-right">Disc (₹)</th>
                        <th style="width: 80px;" class="text-right">GST %</th>
                        <th style="width: 110px;" class="text-right">GST Tax (₹)</th>
                        <th style="width: 130px;" class="text-right">Net Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesBill->items as $idx => $item)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td class="text-monospace font-weight-bold text-muted small">
                                {{ $item->item?->ean_upc_code ?: ($item->item?->item_code ?? '—') }}
                            </td>
                            <td class="font-weight-bold">
                                {{ $item->item?->name ?? 'Item' }}
                            </td>
                            <td>{{ $item->exp_date ? $item->exp_date->format('d-m-Y') : '—' }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($item->qty, 3) }}</td>
                            <td class="text-right">₹{{ number_format($item->sell_price, 2) }}</td>
                            <td class="text-right text-muted">₹{{ number_format($item->mrp, 2) }}</td>
                            <td class="text-right text-danger">
                                @if ($item->disc_amount > 0)
                                    ₹{{ number_format($item->disc_amount, 2) }}
                                    <small class="text-muted">({{ $item->disc_percent }}%)</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-right">{{ $item->gst_percent }}%</td>
                            <td class="text-right text-primary">₹{{ number_format($item->gst_tax_amount, 2) }}</td>
                            <td class="text-right font-weight-bold text-dark">₹{{ number_format($item->net_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="4" class="text-right">Totals:</td>
                        <td class="text-right text-primary">{{ number_format($salesBill->items->sum('qty'), 3) }}</td>
                        <td colspan="2"></td>
                        <td class="text-right text-danger">₹{{ number_format($salesBill->disc_amount, 2) }}</td>
                        <td></td>
                        <td class="text-right text-primary">₹{{ number_format($salesBill->total_gst, 2) }}</td>
                        <td class="text-right text-success h6 mb-0">₹{{ number_format($salesBill->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- Bottom Summary & Payment Card --}}
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card card-outline card-secondary shadow-sm h-100">
                <div class="card-header bg-light py-2">
                    <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-credit-card mr-1 text-primary"></i> Payment Breakdown</h6>
                </div>
                <div class="card-body p-0">
                    @if ($salesBill->payments && $salesBill->payments->count() > 0)
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Tender Mode</th>
                                    <th class="text-right">Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($salesBill->payments as $pmt)
                                    <tr>
                                        <td class="font-weight-bold">
                                            <i class="fas fa-check-circle text-success mr-1"></i> {{ $pmt->tenderType?->name ?? 'Payment' }}
                                        </td>
                                        <td class="text-right font-weight-bold">₹{{ number_format($pmt->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="p-3 text-muted">
                            <i class="fas fa-wallet mr-1"></i> Payment Mode: <strong>{{ $salesBill->payment_type ?? 'Cash' }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card card-outline card-success shadow-sm h-100">
                <div class="card-header bg-light py-2">
                    <h6 class="font-weight-bold mb-0 text-dark"><i class="fas fa-calculator mr-1 text-success"></i> Bill Financial Summary</h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Taxable Subtotal:</span>
                        <strong>₹{{ number_format(max(0, $salesBill->total - $salesBill->total_gst - $salesBill->round_off), 2) }}</strong>
                    </div>
                    @if ($salesBill->total_cgst > 0 || $salesBill->total_sgst > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">CGST Tax:</span>
                            <span class="text-primary">₹{{ number_format($salesBill->total_cgst, 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">SGST Tax:</span>
                            <span class="text-primary">₹{{ number_format($salesBill->total_sgst, 2) }}</span>
                        </div>
                    @elseif ($salesBill->total_igst > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">IGST Tax:</span>
                            <span class="text-primary">₹{{ number_format($salesBill->total_igst, 2) }}</span>
                        </div>
                    @elseif ($salesBill->total_gst > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total GST Tax:</span>
                            <span class="text-primary">₹{{ number_format($salesBill->total_gst, 2) }}</span>
                        </div>
                    @endif
                    @if ($salesBill->round_off != 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Round Off:</span>
                            <span>₹{{ number_format($salesBill->round_off, 2) }}</span>
                        </div>
                    @endif
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h5 font-weight-bold mb-0">Grand Total:</span>
                        <span class="h4 font-weight-bold text-success mb-0">₹{{ number_format($salesBill->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
