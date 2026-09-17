@extends('adminlte::page')

@section('title', 'Purchase Return ' . $purchaseReturn->return_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <i class="fas fa-undo-alt mr-2 text-warning"></i>Purchase Return: {{ $purchaseReturn->return_number }}
            @if ($purchaseReturn->status === 'Posted')
                <span class="badge badge-success">Posted</span>
            @elseif ($purchaseReturn->status === 'Cancelled')
                <span class="badge badge-danger">Cancelled</span>
            @endif
        </h1>
        <div>
            <a href="{{ route('purchase.purchase-returns.print', $purchaseReturn) }}" target="_blank" class="btn btn-primary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print Slip
            </a>
            @if ($purchaseReturn->status !== 'Cancelled')
                <form action="{{ route('purchase.purchase-returns.destroy', $purchaseReturn) }}" method="POST" class="d-inline" onsubmit="return confirm('Cancel this purchase return? Stock will be restored.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm mr-1">
                        <i class="fas fa-ban mr-1"></i> Cancel & Reverse
                    </button>
                </form>
            @endif
            <a href="{{ route('purchase.purchase-returns.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-info-circle mr-1 text-info"></i> Return Details</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Return Number</span>
                    <strong>{{ $purchaseReturn->return_number }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Return Date</span>
                    <strong>{{ $purchaseReturn->return_date->format('d-m-Y') }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Supplier</span>
                    <strong>{{ $purchaseReturn->supplier?->name }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Branch</span>
                    <strong>{{ $purchaseReturn->branch?->name }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Original Purchase Invoice</span>
                    <strong>{{ $purchaseReturn->purchaseInvoice?->invoice_number ?? 'Direct Return' }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Debit Note No</span>
                    <strong>{{ $purchaseReturn->supplier_debit_note_no ?? '—' }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Purchase Type</span>
                    <span class="badge badge-info">{{ $purchaseReturn->purchase_type }}</span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Status</span>
                    <span class="badge badge-{{ $purchaseReturn->status === 'Posted' ? 'success' : 'danger' }}">{{ $purchaseReturn->status }}</span>
                </div>
            </div>
            @if ($purchaseReturn->remarks)
                <div class="alert alert-light border mt-2 mb-0">
                    <strong>Remarks:</strong> {{ $purchaseReturn->remarks }}
                </div>
            @endif
        </div>
    </div>

    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header bg-light">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-boxes mr-1 text-primary"></i> Returned Items</h5>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-bordered table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th>Exp Date</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Cost Price (₹)</th>
                        <th class="text-right">Disc (₹)</th>
                        <th class="text-right">GST %</th>
                        <th class="text-right">GST Tax (₹)</th>
                        <th class="text-right">Net Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($purchaseReturn->items as $idx => $item)
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td class="font-weight-bold">{{ $item->item?->name ?? 'Unknown Item' }}</td>
                            <td>{{ $item->exp_date ? $item->exp_date->format('d-m-Y') : '—' }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($item->qty, 3) }}</td>
                            <td class="text-right">₹{{ number_format($item->cost_price, 2) }}</td>
                            <td class="text-right text-danger">₹{{ number_format($item->disc_amount, 2) }}</td>
                            <td class="text-right">{{ $item->gst_percent }}%</td>
                            <td class="text-right text-primary">₹{{ number_format($item->gst_tax_amount, 2) }}</td>
                            <td class="text-right font-weight-bold">₹{{ number_format($item->net_amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-light font-weight-bold">
                    <tr>
                        <td colspan="3" class="text-right">Totals:</td>
                        <td class="text-right text-primary">{{ number_format($purchaseReturn->items->sum('qty'), 3) }}</td>
                        <td colspan="2" class="text-right text-danger">₹{{ number_format($purchaseReturn->disc_amount, 2) }}</td>
                        <td></td>
                        <td class="text-right text-primary">₹{{ number_format($purchaseReturn->total_gst, 2) }}</td>
                        <td class="text-right text-success h5 mb-0">₹{{ number_format($purchaseReturn->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@stop
