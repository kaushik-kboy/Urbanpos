@extends('adminlte::page')

@section('title', 'Goods Receipt Note - ' . $purchaseReceiptNote->receipt_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>
                Goods Receipt Note: <span class="text-primary">{{ $purchaseReceiptNote->receipt_number }}</span>
                @if ($purchaseReceiptNote->status === 'Received')
                    <span class="badge badge-success ml-2"><i class="fas fa-box mr-1"></i> Received</span>
                @elseif ($purchaseReceiptNote->status === 'Invoiced')
                    <span class="badge badge-primary ml-2"><i class="fas fa-file-invoice mr-1"></i> Invoiced</span>
                @else
                    <span class="badge badge-danger ml-2"><i class="fas fa-times-circle mr-1"></i> Cancelled</span>
                @endif
            </h1>
            <div class="text-muted small">Dated: {{ $purchaseReceiptNote->receipt_date->format('d M Y') }} | Created by {{ $purchaseReceiptNote->createdBy?->name ?? 'System' }}</div>
        </div>
        <div>
            <a href="{{ route('purchase.purchase-receipt-notes.print', $purchaseReceiptNote) }}" target="_blank" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print Slip
            </a>
            @if ($purchaseReceiptNote->status === 'Received')
                <a href="{{ route('purchase.purchase-invoices.create', ['from_receipt_note' => $purchaseReceiptNote->id]) }}" class="btn btn-success btn-sm font-weight-bold mr-1">
                    <i class="fas fa-file-invoice-dollar mr-1"></i> Generate Purchase Invoice
                </a>
                <form action="{{ route('purchase.purchase-receipt-notes.destroy', $purchaseReceiptNote) }}" method="POST" class="d-inline grn-cancel-form">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="reason" class="grn-cancel-reason">
                    <button type="button" class="btn btn-outline-danger btn-sm grn-cancel-btn">
                        <i class="fas fa-ban mr-1"></i> Cancel & Reverse Stock
                    </button>
                </form>
            @endif
            <a href="{{ route('purchase.purchase-receipt-notes.index') }}" class="btn btn-outline-secondary btn-sm ml-1">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    @if ($purchaseReceiptNote->status === 'Invoiced' && $purchaseReceiptNote->purchaseInvoice)
        <div class="alert alert-primary py-2 mb-3 d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-file-invoice mr-1"></i> This Goods Receipt Note was invoiced on Purchase Invoice:
                <strong>{{ $purchaseReceiptNote->purchaseInvoice->invoice_number }}</strong>
                ({{ $purchaseReceiptNote->purchaseInvoice->invoice_date->format('d-m-Y') }}).
            </div>
            <a href="{{ route('purchase.purchase-invoices.show', $purchaseReceiptNote->purchaseInvoice) }}" class="btn btn-xs btn-light">View Invoice</a>
        </div>
    @endif

    @if ($purchaseReceiptNote->status === 'Cancelled')
        <div class="alert alert-danger py-2 mb-3">
            <i class="fas fa-times-circle mr-1"></i> <strong>Cancelled on {{ optional($purchaseReceiptNote->cancelled_at)->format('d-m-Y H:i') }}</strong>
            @if ($purchaseReceiptNote->cancelledBy) by {{ $purchaseReceiptNote->cancelledBy->name }} @endif:
            <em>{{ $purchaseReceiptNote->cancellation_reason ?: 'No reason recorded' }}</em>.
            Inventory has been reversed.
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card card-outline card-info shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-truck mr-1"></i> Supplier Information</h5>
                </div>
                <div class="card-body p-3">
                    <h5 class="font-weight-bold text-dark">{{ $purchaseReceiptNote->supplier?->name }}</h5>
                    <div class="text-muted small mb-1"><i class="fas fa-phone mr-1"></i> {{ $purchaseReceiptNote->supplier?->phone ?: 'No phone' }}</div>
                    <div class="text-muted small mb-1"><i class="fas fa-id-card mr-1"></i> GSTIN: {{ $purchaseReceiptNote->supplier?->gstin ?: 'Unregistered' }}</div>
                    <div class="text-muted small"><i class="fas fa-map-marker-alt mr-1"></i> {{ $purchaseReceiptNote->supplier?->address ?: 'No address' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-store mr-1"></i> Receiving Branch & PO</h5>
                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <span class="text-muted small d-block">Receiving Location:</span>
                        <strong>{{ $purchaseReceiptNote->branch?->name }}</strong>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Purchase Order Reference:</span>
                        @if ($purchaseReceiptNote->purchaseOrder)
                            <a href="{{ route('purchase.purchase-orders.show', $purchaseReceiptNote->purchaseOrder) }}" class="font-weight-bold">
                                {{ $purchaseReceiptNote->purchaseOrder->po_number }}
                            </a>
                            <span class="badge badge-light border ml-1">{{ $purchaseReceiptNote->purchaseOrder->status }}</span>
                        @else
                            <span class="text-muted">Direct Receipt (No PO)</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-outline card-secondary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-shipping-fast mr-1"></i> Dispatch & Transport</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <span class="text-muted small d-block">Challan / DC No:</span>
                            <strong>{{ $purchaseReceiptNote->supplier_challan_no ?: '—' }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <span class="text-muted small d-block">Challan Date:</span>
                            <strong>{{ optional($purchaseReceiptNote->supplier_challan_date)->format('d-m-Y') ?: '—' }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block">Vehicle No:</span>
                            <strong>{{ $purchaseReceiptNote->vehicle_no ?: '—' }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block">Transporter:</span>
                            <strong>{{ $purchaseReceiptNote->transporter_name ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-default shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-1"></i> Inward Item Breakdown</h5>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0">
                <thead class="thead-light">
                    <tr class="text-center">
                        <th style="width: 40px;">#</th>
                        <th style="min-width: 250px;" class="text-left">Item Name / Code</th>
                        <th style="width: 100px;">Ordered</th>
                        <th style="width: 100px;">Received</th>
                        <th style="width: 100px;">Accepted</th>
                        <th style="width: 90px;">Rejected</th>
                        <th style="width: 120px;" class="text-right">Unit Cost (₹)</th>
                        <th style="width: 110px;" class="text-right">MRP (₹)</th>
                        <th style="width: 110px;">Batch No</th>
                        <th style="width: 120px;">Exp Date</th>
                        <th style="width: 130px;" class="text-right">Line Total (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseReceiptNote->items as $idx => $line)
                        <tr>
                            <td class="text-center align-middle">{{ $idx + 1 }}</td>
                            <td class="align-middle">
                                <strong>{{ $line->item?->name }}</strong>
                                @if ($line->item?->code)
                                    <span class="badge badge-light border ml-1">{{ $line->item->code }}</span>
                                @endif
                                @if ($line->remarks)
                                    <div class="small text-muted font-italic">{{ $line->remarks }}</div>
                                @endif
                            </td>
                            <td class="text-center align-middle">{{ number_format($line->ordered_qty, 2) }}</td>
                            <td class="text-center align-middle font-weight-bold">{{ number_format($line->received_qty, 2) }}</td>
                            <td class="text-center align-middle font-weight-bold text-success">{{ number_format($line->accepted_qty, 2) }}</td>
                            <td class="text-center align-middle {{ $line->rejected_qty > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                {{ number_format($line->rejected_qty, 2) }}
                            </td>
                            <td class="text-right align-middle">₹{{ number_format($line->unit_cost, 2) }}</td>
                            <td class="text-right align-middle">{{ $line->mrp ? '₹'.number_format($line->mrp, 2) : '—' }}</td>
                            <td class="text-center align-middle">{{ $line->batch_no ?: '—' }}</td>
                            <td class="text-center align-middle">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                            <td class="text-right align-middle font-weight-bold text-primary">₹{{ number_format($line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <th colspan="2" class="text-right align-middle">Totals:</th>
                        <th class="text-center align-middle">{{ number_format($purchaseReceiptNote->total_ordered_qty, 2) }}</th>
                        <th class="text-center align-middle">{{ number_format($purchaseReceiptNote->total_received_qty, 2) }}</th>
                        <th class="text-center align-middle text-success">{{ number_format($purchaseReceiptNote->total_accepted_qty, 2) }}</th>
                        <th class="text-center align-middle text-danger">{{ number_format($purchaseReceiptNote->total_rejected_qty, 2) }}</th>
                        <th colspan="4" class="text-right align-middle">Grand Total Goods Value:</th>
                        <th class="text-right align-middle text-primary h5 mb-0">₹{{ number_format($purchaseReceiptNote->total_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($purchaseReceiptNote->remarks)
        <div class="card card-default shadow-sm mb-3">
            <div class="card-body p-3">
                <span class="text-muted font-weight-bold d-block small">Receiver Remarks:</span>
                <p class="mb-0">{{ $purchaseReceiptNote->remarks }}</p>
            </div>
        </div>
    @endif

    @push('js')
    <script>
        document.querySelectorAll('.grn-cancel-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const reason = prompt('Reason for cancelling this Goods Receipt Note (Inventory will be reversed):');
                if (reason === null || reason.trim() === '') {
                    return;
                }
                const form = btn.closest('.grn-cancel-form');
                form.querySelector('.grn-cancel-reason').value = reason.trim();
                form.submit();
            });
        });
    </script>
    @endpush
@stop
