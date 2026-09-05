@extends('adminlte::page')

@section('title', 'GST Sales Summary')

@section('content_header')
    <h1>GST Sales Summary</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>HSN Code</th>
                        <th class="text-right">GST %</th>
                        <th class="text-right">Taxable Amount</th>
                        <th class="text-right">GST Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->hsn_code }}</td>
                            <td class="text-right">{{ $row->gst_percent }}</td>
                            <td class="text-right">{{ number_format($row->taxable_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($row->gst_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No sales in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="2">NetTotal</td>
                            <td class="text-right">{{ number_format($rows->sum('taxable_amount'), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum('gst_amount'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
