@extends('adminlte::page')

@section('title', 'Sales Returns')

@section('content_header')
    <h1>Sales Return</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('sales.sales-returns.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Sales Return
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Return No</th>
                        <th>Return Date</th>
                        <th>Customer</th>
                        <th>Bill No</th>
                        <th>Return Mode</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesReturns as $return)
                        <tr>
                            <td>{{ $return->return_number }}</td>
                            <td>{{ $return->return_date->format('d-m-Y') }}</td>
                            <td>{{ $return->customer?->name }}</td>
                            <td>{{ $return->salesBill?->bill_number }}</td>
                            <td>{{ $return->return_mode }}</td>
                            <td>{{ number_format($return->total, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('sales.sales-returns.edit', $return) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('sales.sales-returns.destroy', $return) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this sales return? Stock will be reversed.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales returns yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $salesReturns->links() }}</div>
    </div>
@stop
