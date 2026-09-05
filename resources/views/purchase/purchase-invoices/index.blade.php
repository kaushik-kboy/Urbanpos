@extends('adminlte::page')

@section('title', 'Purchase Invoices')

@section('content_header')
    <h1>Purchase Invoice</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <a href="{{ route('purchase.purchase-invoices.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Purchase Invoice
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Invoice Date</th>
                        <th>Supplier</th>
                        <th>Branch</th>
                        <th>PO No</th>
                        <th>Total</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchaseInvoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->invoice_date->format('d-m-Y') }}</td>
                            <td>{{ $invoice->supplier?->name }}</td>
                            <td>{{ $invoice->branch?->name }}</td>
                            <td>{{ $invoice->purchaseOrder?->po_number }}</td>
                            <td>{{ number_format($invoice->total, 2) }}</td>
                            <td class="text-right">
                                <a href="{{ route('purchase.purchase-invoices.edit', $invoice) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('purchase.purchase-invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this purchase invoice? Stock will be reversed.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No purchase invoices yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $purchaseInvoices->links() }}</div>
    </div>
@stop
