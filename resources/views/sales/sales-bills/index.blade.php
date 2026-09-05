@extends('adminlte::page')

@section('title', 'Sales Bills')

@section('content_header')
    <h1>Sales Bill</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('sales.sales-bills.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Sales Bill
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Bill No</th>
                        <th>Bill Date</th>
                        <th>Customer</th>
                        <th>Branch</th>
                        <th>Invoice Type</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($salesBills as $bill)
                        <tr>
                            <td>{{ $bill->bill_number }}</td>
                            <td>{{ $bill->bill_date->format('d-m-Y') }}</td>
                            <td>{{ $bill->customer?->name }}</td>
                            <td>{{ $bill->branch?->name }}</td>
                            <td>{{ $bill->invoice_type }}</td>
                            <td>{{ number_format($bill->total, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('sales.sales-bills.edit', $bill) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('sales.sales-bills.destroy', $bill) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this sales bill? Stock will be restored.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No sales bills yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $salesBills->links() }}</div>
    </div>
@stop
