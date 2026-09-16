@extends('adminlte::page')

@section('title', 'GST Purchase Summary')

@section('content_header')
    <h1>GST Purchase Summary (ITC)</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>HSN Code</th>
                            <th class="text-right">GST %</th>
                            <th class="text-right">Taxable Amount</th>
                            <th class="text-right">CGST</th>
                            <th class="text-right">SGST</th>
                            <th class="text-right">IGST</th>
                            <th class="text-right">Total GST</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><strong>{{ $row->hsn_code }}</strong></td>
                                <td class="text-right">{{ $row->gst_percent }}%</td>
                                <td class="text-right">{{ number_format($row->taxable_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->cgst_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->sgst_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->igst_amount, 2) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($row->gst_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No purchase records in this period.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="bg-light font-weight-bold">
                            <tr>
                                <td colspan="2">Net Total</td>
                                <td class="text-right">{{ number_format($rows->sum('taxable_amount'), 2) }}</td>
                                <td class="text-right">{{ number_format($rows->sum('cgst_amount'), 2) }}</td>
                                <td class="text-right">{{ number_format($rows->sum('sgst_amount'), 2) }}</td>
                                <td class="text-right">{{ number_format($rows->sum('igst_amount'), 2) }}</td>
                                <td class="text-right text-primary font-weight-bold">{{ number_format($rows->sum('gst_amount'), 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@stop
