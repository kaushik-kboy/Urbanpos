@extends('adminlte::page')

@section('title', 'Billwise Itemwise Sales Detail')

@section('content_header')
    <h1>Billwise Itemwise Sales Detail</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Bill Date</th>
                        <th>Bill No</th>
                        <th>Customer</th>
                        <th>Item</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">MRP</th>
                        <th class="text-right">Net Amount</th>
                        <th>Branch</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        @forelse ($bill->items as $line)
                            <tr>
                                <td>{{ $bill->bill_date->format('d-m-Y') }}</td>
                                <td>{{ $bill->bill_number }}</td>
                                <td>{{ $bill->customer?->name }}</td>
                                <td>{{ $line->item?->name }}</td>
                                <td class="text-right">{{ $line->qty }}</td>
                                <td class="text-right">{{ number_format($line->mrp, 2) }}</td>
                                <td class="text-right">{{ number_format($line->net_amount, 2) }}</td>
                                <td>{{ $bill->branch?->name }}</td>
                            </tr>
                        @empty
                        @endforelse
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop
