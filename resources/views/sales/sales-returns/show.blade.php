@extends('adminlte::page')

@section('title', 'Sales Return ' . $salesReturn->return_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1>
                <i class="fas fa-undo mr-2 text-warning"></i>Sales Return: {{ $salesReturn->return_number }}
            </h1>
            <div class="text-muted small">
                Dated: {{ optional($salesReturn->return_date)->format('d M Y') }} | Branch: {{ $salesReturn->branch?->name }}
            </div>
        </div>
        <div>
            <a href="{{ route('sales.sales-returns.print', $salesReturn) }}" target="_blank" class="btn btn-primary btn-sm mr-1">
                <i class="fas fa-print mr-1"></i> Print Slip
            </a>
            <a href="{{ route('sales.sales-returns.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-warning shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-info-circle mr-1 text-info"></i> Return Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Return Number</span>
                    <strong>{{ $salesReturn->return_number }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Return Date</span>
                    <strong>{{ optional($salesReturn->return_date)->format('d-m-Y') }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Customer</span>
                    <strong>{{ $salesReturn->customer?->name ?? 'Walking Customer' }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Branch</span>
                    <strong>{{ $salesReturn->branch?->name }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Original Bill</span>
                    <strong>{{ $salesReturn->salesBill?->bill_number ?? 'Direct Return' }}</strong>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Return Mode</span>
                    <span class="badge badge-info">{{ $salesReturn->return_mode }}</span>
                </div>
                <div class="col-md-3 mb-2">
                    <span class="text-muted d-block small">Total Amount</span>
                    <strong class="text-success h6 mb-0">₹{{ number_format($salesReturn->total, 2) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light">
            <h5 class="card-title font-weight-bold mb-0"><i class="fas fa-boxes mr-1 text-primary"></i> Returned Items</h5>
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
                            <th class="text-right">Sell Price</th>
                            <th class="text-right">Disc Amt</th>
                            <th class="text-right">GST %</th>
                            <th class="text-right">GST Tax</th>
                            <th class="text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($salesReturn->items as $idx => $line)
                            <tr>
                                <td class="text-center font-weight-bold">{{ $idx + 1 }}</td>
                                <td>{{ $line->item?->item_code ?? $line->item?->ean_upc_code ?? '—' }}</td>
                                <td class="font-weight-bold">{{ $line->item?->name }}</td>
                                <td class="text-center">{{ optional($line->exp_date)->format('d-m-Y') ?: '—' }}</td>
                                <td class="text-right font-weight-bold text-primary">{{ number_format($line->qty, 3) }}</td>
                                <td class="text-right">₹{{ number_format($line->sell_price, 2) }}</td>
                                <td class="text-right">{{ $line->disc_amount > 0 ? '₹' . number_format($line->disc_amount, 2) : '—' }}</td>
                                <td class="text-right">{{ number_format($line->gst_percent, 2) }}%</td>
                                <td class="text-right">₹{{ number_format($line->gst_tax_amount, 2) }}</td>
                                <td class="text-right font-weight-bold text-success">₹{{ number_format($line->net_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-light font-weight-bold">
                        <tr>
                            <td colspan="4" class="text-right">Totals:</td>
                            <td class="text-right text-primary">{{ number_format($salesReturn->items->sum('qty'), 3) }}</td>
                            <td colspan="3"></td>
                            <td class="text-right">₹{{ number_format($salesReturn->total_gst, 2) }}</td>
                            <td class="text-right text-success">₹{{ number_format($salesReturn->total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if ($salesReturn->remarks)
        <div class="card card-outline card-info shadow-sm mb-3">
            <div class="card-header py-2"><strong>Remarks</strong></div>
            <div class="card-body py-2">{{ $salesReturn->remarks }}</div>
        </div>
    @endif
@stop
