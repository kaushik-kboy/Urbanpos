@extends('adminlte::page')

@section('title', 'Sales Delivery Note - ' . $salesDeliveryNote->delivery_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">
                Delivery Note: <span class="text-primary">{{ $salesDeliveryNote->delivery_number }}</span>
                @if ($salesDeliveryNote->status === 'Dispatched')
                    <span class="badge badge-warning text-dark ml-2"><i class="fas fa-shipping-fast mr-1"></i> Dispatched</span>
                @elseif ($salesDeliveryNote->status === 'Invoiced')
                    <span class="badge badge-success ml-2"><i class="fas fa-file-invoice mr-1"></i> Invoiced</span>
                @else
                    <span class="badge badge-danger ml-2"><i class="fas fa-times-circle mr-1"></i> Cancelled</span>
                @endif
            </h1>
            <div class="text-muted small">Dated: {{ $salesDeliveryNote->delivery_date->format('d M Y') }} | Created by {{ $salesDeliveryNote->createdBy?->name ?? 'System' }}</div>
        </div>
        <div>
            <a href="{{ route('sales.delivery-notes.print', $salesDeliveryNote) }}" target="_blank" class="btn btn-outline-secondary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print Challan
            </a>
            @if ($salesDeliveryNote->status === 'Dispatched')
                <a href="{{ route('sales.sales-bills.create', ['from_delivery_note' => $salesDeliveryNote->id]) }}" class="btn btn-success btn-sm font-weight-bold mr-1 shadow-sm">
                    <i class="fas fa-file-invoice-dollar mr-1"></i> 1-Click Convert to Sales Bill
                </a>
                <form action="{{ route('sales.delivery-notes.destroy', $salesDeliveryNote) }}" method="POST" class="d-inline sdn-cancel-form">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="reason" class="sdn-cancel-reason">
                    <button type="button" class="btn btn-outline-danger btn-sm sdn-cancel-btn">
                        <i class="fas fa-ban mr-1"></i> Cancel & Restore Stock
                    </button>
                </form>
            @endif
            <a href="{{ route('sales.delivery-notes.index') }}" class="btn btn-outline-secondary btn-sm ml-1">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    @if ($salesDeliveryNote->status === 'Invoiced' && $salesDeliveryNote->salesBill)
        <div class="alert alert-success py-2 mb-3 d-flex justify-content-between align-items-center shadow-sm">
            <div>
                <i class="fas fa-file-invoice mr-1"></i> This Delivery Note has been invoiced on Sales Bill:
                <strong>{{ $salesDeliveryNote->salesBill->bill_number }}</strong>
                ({{ optional($salesDeliveryNote->salesBill->bill_date)->format('d-m-Y') }}).
            </div>
            <a href="{{ route('sales.sales-bills.show', $salesDeliveryNote->salesBill) }}" class="btn btn-xs btn-light font-weight-bold">View Sales Bill</a>
        </div>
    @endif

    @if ($salesDeliveryNote->status === 'Cancelled')
        <div class="alert alert-danger py-2 mb-3 shadow-sm">
            <i class="fas fa-times-circle mr-1"></i> <strong>Cancelled on {{ optional($salesDeliveryNote->cancelled_at)->format('d-m-Y H:i') }}</strong>
            @if ($salesDeliveryNote->cancelledBy) by {{ $salesDeliveryNote->cancelledBy->name }} @endif:
            <em>{{ $salesDeliveryNote->cancellation_reason ?: 'No reason recorded' }}</em>.
            Inventory has been reversed and restored to warehouse.
        </div>
    @endif

    <div class="row">
        <div class="col-md-4">
            <div class="card card-outline card-info shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-user mr-1"></i> Customer Information</h5>
                </div>
                <div class="card-body p-3">
                    <h5 class="font-weight-bold text-dark">{{ $salesDeliveryNote->customer?->name }}</h5>
                    <div class="text-muted small mb-1"><i class="fas fa-phone mr-1"></i> {{ $salesDeliveryNote->customer?->phone ?: 'No phone' }}</div>
                    <div class="text-muted small mb-1"><i class="fas fa-id-card mr-1"></i> GSTIN: {{ $salesDeliveryNote->customer?->gstin ?: 'Unregistered' }}</div>
                    <div class="text-muted small"><i class="fas fa-map-marker-alt mr-1"></i> {{ $salesDeliveryNote->delivery_address ?: ($salesDeliveryNote->customer?->address ?: 'No address specified') }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-store mr-1"></i> Dispatching Branch & Order</h5>
                </div>
                <div class="card-body p-3">
                    <div class="mb-2">
                        <span class="text-muted small d-block">Dispatch Branch / Warehouse:</span>
                        <strong>{{ $salesDeliveryNote->branch?->name }}</strong>
                    </div>
                    <div>
                        <span class="text-muted small d-block">Sales Order Reference:</span>
                        @if ($salesDeliveryNote->salesOrder)
                            <a href="{{ route('sales.sales-orders.show', $salesDeliveryNote->salesOrder) }}" class="font-weight-bold">
                                {{ $salesDeliveryNote->salesOrder->order_number }}
                            </a>
                            <span class="text-muted small">({{ optional($salesDeliveryNote->salesOrder->order_date)->format('d-m-Y') }})</span>
                        @else
                            <span class="text-muted">Direct Dispatch (No SO)</span>
                        @endif
                    </div>
                    @if ($salesDeliveryNote->reference_no)
                        <div class="mt-2">
                            <span class="text-muted small d-block">Customer PO / Ref:</span>
                            <span class="font-weight-bold">{{ $salesDeliveryNote->reference_no }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-outline card-warning shadow-sm mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-truck mr-1"></i> Transport & Logistics</h5>
                </div>
                <div class="card-body p-3">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <span class="text-muted small d-block">Transporter:</span>
                            <strong>{{ $salesDeliveryNote->transporter_name ?: '—' }}</strong>
                        </div>
                        <div class="col-6 mb-2">
                            <span class="text-muted small d-block">Vehicle No:</span>
                            <strong>{{ $salesDeliveryNote->vehicle_no ?: '—' }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block">LR / Bilty No:</span>
                            <strong>{{ $salesDeliveryNote->lr_no ?: '—' }}</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-muted small d-block">LR Date:</span>
                            <strong>{{ optional($salesDeliveryNote->lr_date)->format('d-m-Y') ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-default shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-boxes mr-1"></i> Dispatched Goods Items</h5>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-bordered table-striped mb-0">
                <thead class="thead-light text-center">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th class="text-left" style="min-width: 250px;">Item Description</th>
                        <th style="width: 120px;">Ordered Qty</th>
                        <th style="width: 130px;">Dispatched Qty</th>
                        <th style="width: 120px;" class="text-right">Unit Price</th>
                        <th style="width: 110px;" class="text-right">MRP</th>
                        <th style="width: 120px;">Batch No</th>
                        <th style="width: 120px;">Exp Date</th>
                        <th style="width: 130px;" class="text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($salesDeliveryNote->items as $idx => $line)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td>
                                <strong>{{ $line->item?->name }}</strong>
                                @if ($line->item?->item_code)
                                    <span class="badge badge-light border ml-1">{{ $line->item->item_code }}</span>
                                @endif
                                @if ($line->remarks)
                                    <div class="text-muted small font-italic">{{ $line->remarks }}</div>
                                @endif
                            </td>
                            <td class="text-center">{{ number_format($line->ordered_qty, 2) }}</td>
                            <td class="text-center font-weight-bold text-primary">{{ number_format($line->dispatched_qty, 2) }}</td>
                            <td class="text-right">₹{{ number_format($line->unit_price, 2) }}</td>
                            <td class="text-right text-muted">₹{{ number_format($line->mrp ?? $line->item?->mrp ?? 0, 2) }}</td>
                            <td class="text-center">{{ $line->batch_no ?: '—' }}</td>
                            <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                            <td class="text-right font-weight-bold text-success">₹{{ number_format($line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="2" class="text-right">Totals:</td>
                        <td class="text-center">{{ number_format($salesDeliveryNote->total_ordered_qty, 2) }}</td>
                        <td class="text-center text-primary">{{ number_format($salesDeliveryNote->total_dispatched_qty, 2) }}</td>
                        <td colspan="4" class="text-right">Total Goods Valuation:</td>
                        <td class="text-right text-success h5 mb-0">₹{{ number_format($salesDeliveryNote->total_amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    @if ($salesDeliveryNote->remarks)
        <div class="card card-default shadow-sm mb-3">
            <div class="card-body p-3">
                <span class="font-weight-bold text-muted small d-block">Internal Remarks / Instructions:</span>
                <div>{{ $salesDeliveryNote->remarks }}</div>
            </div>
        </div>
    @endif
@stop

@section('js')
<script>
$(function () {
    $('.sdn-cancel-btn').on('click', function (e) {
        e.preventDefault();
        var form = $(this).closest('form');
        var reason = prompt("Enter reason for cancelling this Delivery Note (stock will be restored):");
        if (reason && reason.trim() !== '') {
            form.find('.sdn-cancel-reason').val(reason.trim());
            form.submit();
        }
    });
});
</script>
@stop
