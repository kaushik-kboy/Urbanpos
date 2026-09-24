@extends('adminlte::page')

@section('title', 'Purchase Invoice ' . $purchaseInvoice->invoice_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>
                <i class="fas fa-file-invoice mr-2 text-primary"></i>Purchase Invoice: {{ $purchaseInvoice->invoice_number }}
            </h1>
            <div class="text-muted small">
                Dated: {{ optional($purchaseInvoice->invoice_date)->format('d M Y') }} | Branch: {{ $purchaseInvoice->branch?->name }}
            </div>
        </div>
        <div>
            <a href="{{ route('master.barcodes.print', ['purchase_invoice_id' => $purchaseInvoice->id, 'format' => '50x38_2up']) }}" target="_blank" class="btn btn-warning btn-sm font-weight-bold mr-1">
                <i class="fas fa-barcode mr-1"></i> Print Stickers (TSC TE244)
            </a>
            <a href="{{ route('purchase.purchase-invoices.print', $purchaseInvoice) }}" target="_blank" class="btn btn-primary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print
            </a>
            <a href="{{ route('purchase.purchase-invoices.edit', $purchaseInvoice) }}" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-pen mr-1"></i> Edit
            </a>
            <a href="{{ route('purchase.purchase-invoices.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-info-circle mr-1 text-info"></i> Invoice Header</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Invoice Number</span>
                    <strong>{{ $purchaseInvoice->invoice_number }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Invoice Date</span>
                    <strong>{{ optional($purchaseInvoice->invoice_date)->format('d-m-Y') }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Supplier</span>
                    <strong>{{ $purchaseInvoice->supplier?->name }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Branch</span>
                    <strong>{{ $purchaseInvoice->branch?->name }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Supplier Inv No / Date</span>
                    <strong>{{ $purchaseInvoice->supplier_inv_no ?: '—' }}</strong>
                    @if($purchaseInvoice->supplier_inv_date)
                        <span class="text-muted small">({{ $purchaseInvoice->supplier_inv_date->format('d-m-Y') }})</span>
                    @endif
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Supplier Inv Amount</span>
                    <strong>₹{{ number_format((float)($purchaseInvoice->supplier_inv_amount ?? 0), 2) }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Purchase Type</span>
                    <span class="badge badge-info">{{ $purchaseInvoice->purchase_type ?? 'Local' }}</span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Purchase Order</span>
                    <strong>{{ $purchaseInvoice->purchaseOrder?->po_number ?: 'Direct' }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-boxes mr-1 text-primary"></i> Invoice Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-striped mb-0">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th class="text-center" style="width: 40px;">#</th>
                            <th>Code</th>
                            <th>Description</th>
                            <th class="text-center">Exp Date</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Free</th>
                            <th class="text-right">Cost Price</th>
                            <th class="text-right">Sell Price</th>
                            <th class="text-right">MRP</th>
                            <th class="text-right">Disc Amt</th>
                            <th class="text-right">GST %</th>
                            <th class="text-right">GST Tax Amt</th>
                            <th class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseInvoice->items as $idx => $line)
                            <tr>
                                <td class="text-center font-weight-bold">{{ $idx + 1 }}</td>
                                <td>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                                <td class="font-weight-bold">{{ $line->item?->name }}</td>
                                <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                                <td class="text-right font-weight-bold text-primary">{{ number_format($line->qty, 3) }}</td>
                                <td class="text-right">{{ $line->free_qty > 0 ? number_format($line->free_qty, 3) : '—' }}</td>
                                <td class="text-right">₹{{ number_format($line->cost_price, 2) }}</td>
                                <td class="text-right">₹{{ number_format($line->sell_price, 2) }}</td>
                                <td class="text-right">₹{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-right">{{ $line->disc_amount > 0 ? '₹' . number_format($line->disc_amount, 2) : '—' }}</td>
                                <td class="text-right">{{ number_format($line->gst_percent, 2) }}%</td>
                                <td class="text-right">₹{{ number_format($line->gst_tax_amount, 2) }}</td>
                                <td class="text-right font-weight-bold text-success">₹{{ number_format($line->net_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center py-3 text-muted">No items recorded on this invoice.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="4" class="text-right">Totals:</td>
                            <td class="text-right text-primary">{{ number_format($purchaseInvoice->items->sum('qty') + $purchaseInvoice->items->sum('free_qty'), 3) }}</td>
                            <td colspan="4"></td>
                            <td class="text-right text-danger">₹{{ number_format($purchaseInvoice->items->sum('disc_amount'), 2) }}</td>
                            <td></td>
                            <td class="text-right">₹{{ number_format($purchaseInvoice->total_gst, 2) }}</td>
                            <td class="text-right text-success">₹{{ number_format($purchaseInvoice->items->sum('net_amount'), 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            @if ($purchaseInvoice->remarks)
                <div class="card card-outline card-info shadow-sm mb-3">
                    <div class="card-header py-2"><strong>Remarks</strong></div>
                    <div class="card-body py-2">{{ $purchaseInvoice->remarks }}</div>
                </div>
            @endif
        </div>
        <div class="col-md-6">
            <div class="card card-outline card-success shadow-sm mb-3">
                <div class="card-header py-2 bg-light font-weight-bold">Summary & Totals</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <tr>
                            <td>Items Total (Net):</td>
                            <td class="text-right font-weight-bold">₹{{ number_format($purchaseInvoice->items->sum('net_amount'), 2) }}</td>
                        </tr>
                        @if ($purchaseInvoice->freight > 0)
                            <tr>
                                <td>Freight:</td>
                                <td class="text-right">₹{{ number_format($purchaseInvoice->freight, 2) }}</td>
                            </tr>
                        @endif
                        @if ($purchaseInvoice->scheme_item_disc_amt > 0)
                            <tr>
                                <td>Scheme Item Discount:</td>
                                <td class="text-right text-danger">-₹{{ number_format($purchaseInvoice->scheme_item_disc_amt, 2) }}</td>
                            </tr>
                        @endif
                        @if ($purchaseInvoice->other_disc_amt > 0)
                            <tr>
                                <td>Other Discount:</td>
                                <td class="text-right text-danger">-₹{{ number_format($purchaseInvoice->other_disc_amt, 2) }}</td>
                            </tr>
                        @endif
                        @if ($purchaseInvoice->round_off != 0)
                            <tr>
                                <td>Round Off:</td>
                                <td class="text-right">₹{{ number_format($purchaseInvoice->round_off, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="bg-light font-weight-bold text-primary h5 mb-0">
                            <td>Grand Total:</td>
                            <td class="text-right">₹{{ number_format($purchaseInvoice->total, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
