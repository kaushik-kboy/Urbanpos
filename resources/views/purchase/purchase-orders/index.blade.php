@extends('adminlte::page')

@section('title', 'Purchase Orders')

@section('content_header')
    <h1>Purchase Order</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('purchase.purchase-orders.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Purchase Order
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>PO No</th>
                        <th>PO Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseOrders as $po)
                        <tr>
                            <td>{{ $po->po_number }}</td>
                            <td>{{ $po->po_date->format('d-m-Y') }}</td>
                            <td>{{ $po->supplier?->name }}</td>
                            <td>{{ $po->branch?->name }}</td>
                            <td>{{ number_format($po->total, 2) }}</td>
                            <td><span class="badge badge-{{ $po->status === 'Open' ? 'success' : ($po->status === 'Cancelled' ? 'danger' : 'secondary') }}">{{ $po->status }}</span></td>
                            <td class="text-right">
                                <a href="{{ route('purchase.purchase-orders.edit', $po) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('purchase.purchase-orders.destroy', $po) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this purchase order?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No purchase orders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $purchaseOrders->links() }}</div>
    </div>
@stop
