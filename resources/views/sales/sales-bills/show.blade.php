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
            <a href="{{ route('sales.sales-bills.eway-json', $salesBill) }}" class="btn btn-warning btn-sm mr-1 shadow-sm font-weight-bold" title="Download Official NIC JSON for ewaybillgst.gov.in">
                <i class="fas fa-file-code mr-1"></i> E-Way JSON
            </a>
            <button type="button" class="btn btn-info btn-sm mr-1 shadow-sm font-weight-bold" data-toggle="modal" data-target="#ewayModal" title="Update E-Way Bill Number and Transport Details">
                <i class="fas fa-truck mr-1"></i> {{ $salesBill->hasEwayBill() ? 'E-Way #' . $salesBill->eway_bill_no : 'Update E-Way' }}
            </button>
            <a href="{{ route('sales.sales-bills.receipt', $salesBill) }}" target="_blank" class="btn btn-success btn-sm mr-1 shadow-sm">
                <i class="fas fa-receipt mr-1"></i> Thermal Receipt (80mm)
            </a>
            <button type="button" id="btn-show-send-whatsapp" class="btn btn-success btn-sm mr-1 shadow-sm font-weight-bold" onclick="sendWhatsAppInvoiceShow()" style="background-color: #25d366; border-color: #25d366;" title="Send Digital Bill via WhatsApp">
                <i class="fab fa-whatsapp mr-1"></i> Send WhatsApp
            </button>
            <button onclick="window.print()" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print (A4)
            </button>
            @if ($salesBill->status !== 'Cancelled')
                <a href="{{ route('sales.sales-returns.create', ['customer_id' => $salesBill->customer_id, 'sales_bill_id' => $salesBill->id]) }}" class="btn btn-warning btn-sm mr-1 shadow-sm font-weight-bold" title="Create Sales Return for this Bill">
                    <i class="fas fa-undo mr-1"></i> Sales Return
                </a>
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
                    <span class="text-muted small d-block">Bill Date & Time</span>
                    <strong>{{ $salesBill->bill_date->format('d-m-Y h:i A') }}</strong>
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

    {{-- Government E-Invoice (IRN) Banner / Card --}}
    @if ($salesBill->hasIrn())
        <div class="card card-outline card-success shadow-sm mb-3">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h5 class="card-title font-weight-bold mb-0 text-success">
                    <i class="fas fa-check-circle mr-1"></i> Government E-Invoice (IRN Generated &amp; Signed)
                </h5>
                <span class="badge badge-success px-2 py-1">IRP Acknowledged</span>
            </div>
            <div class="card-body py-3">
                <div class="row align-items-center">
                    <div class="col-md-9">
                        <div class="mb-2">
                            <span class="text-muted small d-block font-weight-bold">INVOICE REFERENCE NUMBER (IRN - 64 CHARACTERS):</span>
                            <code class="text-dark font-weight-bold" style="word-break: break-all; font-size: 13px;">{{ $salesBill->irn }}</code>
                        </div>
                        <div class="row">
                            <div class="col-sm-4">
                                <span class="text-muted small d-block">Ack Number:</span>
                                <strong>{{ $salesBill->ack_no ?? 'N/A' }}</strong>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted small d-block">Ack Date:</span>
                                <strong>{{ $salesBill->ack_date ? $salesBill->ack_date->format('d-m-Y h:i A') : 'N/A' }}</strong>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted small d-block">Status:</span>
                                <span class="badge badge-success px-2">COMPLETED</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center border-left">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode($salesBill->irn) }}" alt="Govt QR Code" class="img-thumbnail" style="width: 100px; height: 100px;">
                        <span class="d-block small text-muted mt-1">Official Govt QR</span>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($salesBill->einvoice_status === 'Failed')
        <div class="alert alert-danger shadow-sm mb-3 py-2">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>Govt E-Invoice Upload Failed:</strong> {{ $salesBill->einvoice_error ?? 'Validation error at portal schema check.' }}
                </div>
                <a href="{{ route('tools.integrations-gst', ['tab' => 'failed', 'search' => $salesBill->bill_number]) }}" class="btn btn-outline-danger btn-sm">
                    Resolve in E-Filing Hub
                </a>
            </div>
        </div>
    @elseif ($salesBill->total >= 50000 || !empty($salesBill->customer?->gst_no))
        <div class="alert alert-warning shadow-sm mb-3 py-2 d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-clock mr-1"></i>
                <strong>Govt E-Invoice Pending:</strong> This invoice exceeds ₹50,000 / B2B threshold and is queued for Government IRN generation.
            </div>
            <a href="{{ route('tools.integrations-gst', ['tab' => 'pending', 'search' => $salesBill->bill_number]) }}" class="btn btn-primary btn-sm">
                Open in GST E-Filing Hub
            </a>
        </div>
    @endif

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
                                {{ $item->item?->item_code ?: ($item->item?->ean_upc_code ?? '—') }}
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

    {{-- E-Way Bill & Transport Logistics Card --}}
    <div class="card card-outline {{ $salesBill->hasEwayBill() ? 'card-success' : ($salesBill->requiresEwayBill() ? 'card-warning' : 'card-secondary') }} shadow-sm mb-4">
        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold mb-0 text-dark">
                <i class="fas fa-truck mr-1 text-primary"></i> Government E-Way Bill & Transport Details (Part-A & Part-B)
            </h5>
            <div>
                @if ($salesBill->hasEwayBill())
                    <span class="badge badge-success px-3 py-1 font-weight-bold"><i class="fas fa-check-circle mr-1"></i> E-Way Bill Generated</span>
                @elseif ($salesBill->requiresEwayBill())
                    <span class="badge badge-warning px-3 py-1 font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i> E-Way Bill Required (> ₹50,000)</span>
                @else
                    <span class="badge badge-light border text-muted px-2 py-1">Optional for Local Retail</span>
                @endif
                <button type="button" class="btn btn-primary btn-xs ml-2 shadow-sm" data-toggle="modal" data-target="#ewayModal">
                    <i class="fas fa-edit mr-1"></i> Edit Transport Info
                </button>
            </div>
        </div>
        <div class="card-body py-3">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block font-weight-bold">E-Way Bill Number</span>
                    @if ($salesBill->hasEwayBill())
                        <span class="h5 text-success font-weight-bold"><i class="fas fa-id-card mr-1"></i> {{ $salesBill->eway_bill_no }}</span>
                    @else
                        <span class="text-warning font-weight-bold"><i class="fas fa-clock mr-1"></i> Not Generated Yet</span>
                    @endif
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block font-weight-bold">E-Way Bill Validity</span>
                    @if ($salesBill->eway_valid_until)
                        <strong><i class="far fa-calendar-alt text-info mr-1"></i> {{ $salesBill->eway_valid_until->format('d-m-Y h:i A') }}</strong>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block font-weight-bold">Vehicle Number</span>
                    @if ($salesBill->vehicle_no)
                        <span class="badge badge-secondary px-2 py-1 font-weight-bold text-uppercase" style="font-size: 14px; letter-spacing: 1px;">
                            <i class="fas fa-car mr-1"></i> {{ $salesBill->vehicle_no }}
                        </span>
                        <small class="text-muted d-block mt-1">Type: {{ $salesBill->vehicle_type === 'O' ? 'Over Dimensional' : 'Regular' }}</small>
                    @else
                        <span class="text-muted">Not Specified</span>
                    @endif
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <span class="text-muted small d-block font-weight-bold">Transporter</span>
                    @if ($salesBill->transporter_name)
                        <strong>{{ $salesBill->transporter_name }}</strong>
                        @if ($salesBill->transporter_id)
                            <small class="text-muted d-block">ID/GSTIN: {{ $salesBill->transporter_id }}</small>
                        @endif
                    @else
                        <span class="text-muted">Self / Not Specified</span>
                    @endif
                </div>
            </div>

            <hr class="my-2">

            <div class="row pt-2 text-muted small">
                <div class="col-md-3 col-sm-6">
                    <strong>Transport Mode:</strong>
                    @php
                        $modes = ['1' => 'Road', '2' => 'Rail', '3' => 'Air', '4' => 'Ship'];
                    @endphp
                    {{ $modes[$salesBill->transport_mode ?? '1'] ?? 'Road' }}
                </div>
                <div class="col-md-3 col-sm-6">
                    <strong>Distance:</strong> {{ $salesBill->transport_distance ? $salesBill->transport_distance . ' KM' : 'Approx 20 KM' }}
                </div>
                <div class="col-md-3 col-sm-6">
                    <strong>Doc / LR No:</strong> {{ $salesBill->transport_doc_no ?: 'None' }}
                </div>
                <div class="col-md-3 col-sm-6">
                    <strong>Doc Date:</strong> {{ $salesBill->transport_doc_date ? $salesBill->transport_doc_date->format('d-m-Y') : 'None' }}
                </div>
            </div>
        </div>
    </div>

    {{-- E-Way Bill Update Modal --}}
    <div class="modal fade" id="ewayModal" tabindex="-1" role="dialog" aria-labelledby="ewayModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <form action="{{ route('sales.sales-bills.eway-update', $salesBill) }}" method="POST">
                    @csrf
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title font-weight-bold" id="ewayModalLabel">
                            <i class="fas fa-truck mr-2 text-warning"></i> Government E-Way Bill & Logistics Details
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Workflow:</strong> 1) Click <strong>"E-Way JSON"</strong> button to download official JSON. 2) Upload it on <strong><a href="https://ewaybillgst.gov.in" target="_blank" class="text-dark font-weight-bold text-underline">ewaybillgst.gov.in</a></strong>. 3) Copy the 12-digit E-Way Bill Number below and click Save.
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-dark">12-Digit E-Way Bill Number (EWB No)</label>
                                <input type="text" name="eway_bill_no" class="form-control" value="{{ old('eway_bill_no', $salesBill->eway_bill_no) }}" placeholder="e.g. 101234567890" maxlength="25">
                                <small class="text-muted">Issued by National Informatics Centre (NIC) portal</small>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-dark">Valid Until (Expiry Date & Time)</label>
                                <input type="datetime-local" name="eway_valid_until" class="form-control" value="{{ old('eway_valid_until', $salesBill->eway_valid_until ? $salesBill->eway_valid_until->format('Y-m-d\TH:i') : '') }}">
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="font-weight-bold text-secondary mb-3"><i class="fas fa-shipping-fast mr-1"></i> Part-B Transport Details</h6>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-dark">Vehicle Number</label>
                                <input type="text" name="vehicle_no" class="form-control text-uppercase" value="{{ old('vehicle_no', $salesBill->vehicle_no) }}" placeholder="e.g. MH 12 AB 1234">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="font-weight-bold small text-dark">Vehicle Type</label>
                                <select name="vehicle_type" class="form-control">
                                    <option value="R" {{ $salesBill->vehicle_type === 'R' ? 'selected' : '' }}>Regular</option>
                                    <option value="O" {{ $salesBill->vehicle_type === 'O' ? 'selected' : '' }}>Over Dimensional (ODC)</option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="font-weight-bold small text-dark">Distance (in KM)</label>
                                <input type="number" name="transport_distance" class="form-control" value="{{ old('transport_distance', $salesBill->transport_distance ?: 20) }}" min="1" max="4000">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-dark">Transporter Name</label>
                                <input type="text" name="transporter_name" class="form-control" value="{{ old('transporter_name', $salesBill->transporter_name) }}" placeholder="e.g. VRL Logistics, SafeXpress, etc.">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="font-weight-bold small text-dark">Transporter ID / GSTIN / TRANSIN</label>
                                <input type="text" name="transporter_id" class="form-control text-uppercase" value="{{ old('transporter_id', $salesBill->transporter_id) }}" placeholder="15-digit GSTIN or Transporter ID">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label class="font-weight-bold small text-dark">Mode of Transport</label>
                                <select name="transport_mode" class="form-control">
                                    <option value="1" {{ ($salesBill->transport_mode ?? '1') == '1' ? 'selected' : '' }}>1 - Road</option>
                                    <option value="2" {{ ($salesBill->transport_mode ?? '1') == '2' ? 'selected' : '' }}>2 - Rail</option>
                                    <option value="3" {{ ($salesBill->transport_mode ?? '1') == '3' ? 'selected' : '' }}>3 - Air</option>
                                    <option value="4" {{ ($salesBill->transport_mode ?? '1') == '4' ? 'selected' : '' }}>4 - Ship</option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="font-weight-bold small text-dark">Doc / LR / Bilty Number</label>
                                <input type="text" name="transport_doc_no" class="form-control" value="{{ old('transport_doc_no', $salesBill->transport_doc_no) }}" placeholder="e.g. LR-98765">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="font-weight-bold small text-dark">Doc / LR Date</label>
                                <input type="date" name="transport_doc_date" class="form-control" value="{{ old('transport_doc_date', $salesBill->transport_doc_date ? $salesBill->transport_doc_date->format('Y-m-d') : '') }}">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success font-weight-bold px-4">
                            <i class="fas fa-save mr-1"></i> Save E-Way Bill Details
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
function sendWhatsAppInvoiceShow() {
    let phone = '{{ $salesBill->customer?->phone ?: $salesBill->customer?->mobile }}';
    if (!phone || phone.trim().length < 10) {
        phone = prompt('Customer has no mobile number saved. Enter 10-digit WhatsApp number:');
        if (!phone) return;
    }

    const btn = document.getElementById('btn-show-send-whatsapp');
    if (!btn) return;
    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';

    fetch('{{ route('sales.sales-bills.send-whatsapp', $salesBill) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ phone: phone })
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('⚠️ ' + (data.error || 'Failed to send WhatsApp message'));
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = origHtml;
        alert('Network error while dispatching WhatsApp: ' + err.message);
    });
}
</script>
@stop
