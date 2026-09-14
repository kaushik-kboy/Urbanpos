@extends('adminlte::page')

@section('title', 'Price Drop')

@section('content_header')
    <h1><i class="fas fa-level-down-alt text-primary mr-2"></i>Price Drop (Purchase Cost Adjustment)</h1>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-light">
            <form method="GET" class="form-inline">
                <label class="mr-2 font-weight-bold">Location:</label>
                <select name="branch_id" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
                    @foreach ($branches as $id => $name)
                        <option value="{{ $id }}" @selected((string)$branchId === (string)$id)>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="card-body">
            <p class="text-muted">
                Record post-purchase supplier price drops or rate concessions against inward purchase invoices to recalculate assessable stock values.
            </p>

            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="bg-light">
                        <tr>
                            <th>Invoice Number</th>
                            <th>Invoice Date</th>
                            <th>Supplier</th>
                            <th class="text-right">Invoice Total</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $inv)
                            <tr>
                                <td class="font-weight-bold">{{ $inv->invoice_number }}</td>
                                <td>{{ $inv->invoice_date->format('d-m-Y') }}</td>
                                <td>{{ $inv->supplier?->name ?: '-' }}</td>
                                <td class="text-right">₹ {{ number_format($inv->total, 2) }}</td>
                                <td><span class="badge badge-success">Completed</span></td>
                                <td class="text-right">
                                    <button type="button" class="btn btn-xs btn-primary" onclick="alert('Price drop credit note for invoice #{{ $inv->invoice_number }}')">
                                        <i class="fas fa-file-invoice mr-1"></i> Apply Price Drop
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No recent purchase invoices found for this location.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
