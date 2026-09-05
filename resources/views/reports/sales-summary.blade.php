@extends('adminlte::page')

@section('title', 'Daily Sales Summary')

@section('content_header')
    <h1>Daily Sales Summary [Store Wise]</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th class="text-right">Total Bills</th>
                        <th class="text-right">Bill Amount</th>
                        <th class="text-right">Discount</th>
                        <th class="text-right">GST</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($row->bill_date)->format('d-m-Y') }}</td>
                            <td>{{ $branches[$row->branch_id] ?? '' }}</td>
                            <td class="text-right">{{ $row->bill_count }}</td>
                            <td class="text-right">{{ number_format($row->total_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($row->total_disc, 2) }}</td>
                            <td class="text-right">{{ number_format($row->total_gst, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="2">NetTotal</td>
                            <td class="text-right">{{ $rows->sum('bill_count') }}</td>
                            <td class="text-right">{{ number_format($rows->sum('total_amount'), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum('total_disc'), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum('total_gst'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
