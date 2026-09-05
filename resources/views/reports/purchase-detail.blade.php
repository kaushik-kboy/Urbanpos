@extends('adminlte::page')

@section('title', 'Purchase Detail')

@section('content_header')
    <h1>Purchase Detail</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Inv Date</th>
                        <th>Inv No</th>
                        <th>Supplier</th>
                        <th>Item</th>
                        <th class="text-right">Received Qty</th>
                        <th class="text-right">Purchase Rate</th>
                        <th class="text-right">MRP</th>
                        <th class="text-right">GST %</th>
                        <th class="text-right">Net Amount</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @forelse ($invoice->items as $line)
                            <tr>
                                <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                                <td>{{ $invoice->invoice_number }}</td>
                                <td>{{ $invoice->supplier?->name }}</td>
                                <td>{{ $line->item?->name }}</td>
                                <td class="text-right">{{ $line->qty }}</td>
                                <td class="text-right">{{ number_format($line->cost_price, 2) }}</td>
                                <td class="text-right">{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-right">{{ $line->gst_percent }}</td>
                                <td class="text-right">{{ number_format($line->net_amount, 2) }}</td>
                                <td>{{ $invoice->branch?->name }}</td>
                            </tr>
                        @empty
                        @endforelse
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-3">No purchases in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
