@extends('adminlte::page')

@section('title', 'Purchase Order ' . $purchaseOrder->po_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-file-alt mr-2 text-primary"></i>Purchase Order: {{ $purchaseOrder->po_number }}
            </h1>
            <div class="text-muted small mt-1">
                Dated: {{ optional($purchaseOrder->po_date)->format('d M Y') }} | Branch: {{ $purchaseOrder->branch?->name }} | Status: <span class="badge badge-{{ $purchaseOrder->status === 'Closed' ? 'success' : ($purchaseOrder->status === 'Cancelled' ? 'danger' : 'primary') }}">{{ $purchaseOrder->status ?? 'Draft' }}</span>
            </div>
        </div>
        <div>
            <a href="{{ route('purchase.purchase-orders.print', $purchaseOrder) }}" target="_blank" class="btn btn-primary btn-sm mr-1 shadow-sm">
                <i class="fas fa-print mr-1"></i> Print
            </a>
            <a href="{{ route('purchase.purchase-orders.edit', $purchaseOrder) }}" class="btn btn-outline-secondary btn-sm mr-1 shadow-sm">
                <i class="fas fa-pen mr-1"></i> Edit
            </a>
            <a href="{{ route('purchase.purchase-orders.index') }}" class="btn btn-secondary btn-sm shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-info-circle mr-1 text-info"></i> Order Summary</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">PO Number</span>
                    <strong>{{ $purchaseOrder->po_number }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">PO Date</span>
                    <strong>{{ optional($purchaseOrder->po_date)->format('d-m-Y') }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Supplier</span>
                    <strong>{{ $purchaseOrder->supplier?->name ?: '—' }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Branch / Location</span>
                    <strong>{{ $purchaseOrder->branch?->name ?: '—' }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Purchase Type</span>
                    <span class="badge badge-info">{{ $purchaseOrder->purchase_type ?? 'Local' }}</span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Total Items / Qty</span>
                    <strong>{{ $purchaseOrder->items->count() }} items / {{ number_format($purchaseOrder->total_qty, 2) }} units</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Total Tax (GST)</span>
                    <strong>₹{{ number_format((float)($purchaseOrder->total_gst ?? 0), 2) }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Grand Total</span>
                    <strong class="text-success h6 font-weight-bold">₹{{ number_format((float)($purchaseOrder->total ?? 0), 2) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-boxes mr-1 text-primary"></i> Order Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-striped mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center" style="width: 40px;">#</th>
                            <th>Code</th>
                            <th>Item Description</th>
                            <th class="text-right">Ordered Qty</th>
                            <th class="text-right">Free Qty</th>
                            <th class="text-right">Cost Price</th>
                            <th class="text-right">Disc Amt</th>
                            <th class="text-right">GST %</th>
                            <th class="text-right">GST Tax</th>
                            <th class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrder->items as $idx => $line)
                            <tr>
                                <td class="text-center font-weight-bold text-muted">{{ $idx + 1 }}</td>
                                <td><code>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</code></td>
                                <td class="font-weight-bold">{{ $line->item?->name }}</td>
                                <td class="text-right font-weight-bold text-primary">{{ number_format($line->qty, 2) }}</td>
                                <td class="text-right">{{ $line->free_qty > 0 ? number_format($line->free_qty, 2) : '—' }}</td>
                                <td class="text-right">₹{{ number_format($line->cost_price, 2) }}</td>
                                <td class="text-right">{{ $line->disc_amount > 0 ? '₹' . number_format($line->disc_amount, 2) : '—' }}</td>
                                <td class="text-right">{{ number_format($line->gst_percent, 2) }}%</td>
                                <td class="text-right">₹{{ number_format($line->gst_tax_amount, 2) }}</td>
                                <td class="text-right font-weight-bold text-success">₹{{ number_format($line->net_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-3 text-muted">No items recorded on this purchase order.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="3" class="text-right">Totals:</td>
                            <td class="text-right text-primary">{{ number_format($purchaseOrder->items->sum('qty'), 2) }}</td>
                            <td class="text-right">{{ number_format($purchaseOrder->items->sum('free_qty'), 2) }}</td>
                            <td colspan="3"></td>
                            <td class="text-right">₹{{ number_format($purchaseOrder->total_gst, 2) }}</td>
                            <td class="text-right text-success">₹{{ number_format($purchaseOrder->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if ($purchaseOrder->remarks || $purchaseOrder->message)
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header bg-light py-2">
                <h5 class="card-title font-weight-bold mb-0 text-dark"><i class="fas fa-sticky-note mr-1 text-secondary"></i> Notes & Remarks</h5>
            </div>
            <div class="card-body">
                @if ($purchaseOrder->remarks)
                    <p class="mb-1"><strong>Remarks:</strong> {{ $purchaseOrder->remarks }}</p>
                @endif
                @if ($purchaseOrder->message)
                    <p class="mb-0"><strong>Message to Supplier:</strong> {{ $purchaseOrder->message }}</p>
                @endif
            </div>
        </div>
    @endif
@stop
