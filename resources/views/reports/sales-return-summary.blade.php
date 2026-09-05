@extends('adminlte::page')

@section('title', 'Sales Return Summary')

@section('content_header')
    <h1>Sales Return Summary</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            @include('reports._date-branch-filter')

            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th>Return Date</th>
                        <th>Return No</th>
                        <th>Customer</th>
                        <th>Bill No</th>
                        <th>Return Mode</th>
                        <th class="text-right">Return Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($returns as $return)
                        <tr>
                            <td>{{ $return->branch?->name }}</td>
                            <td>{{ $return->return_date->format('d-m-Y') }}</td>
                            <td>{{ $return->return_number }}</td>
                            <td>{{ $return->customer?->name }}</td>
                            <td>{{ $return->salesBill?->bill_number }}</td>
                            <td>{{ $return->return_mode }}</td>
                            <td class="text-right">{{ number_format($return->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No returns in this period.</td></tr>
                    @endforelse
                </tbody>
                @if ($returns->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="6">NetTotal</td>
                            <td class="text-right">{{ number_format($returns->sum('total'), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
