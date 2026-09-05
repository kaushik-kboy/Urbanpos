@extends('adminlte::page')

@section('title', 'Customer Master')

@section('content_header')
    <h1>Customer Master</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Customer Name</th>
                        <th>Mobile</th>
                        <th>City</th>
                        <th>GST No</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th class="text-right">Credit Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->customer_code }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->mobile }}</td>
                            <td>{{ $customer->city }}</td>
                            <td>{{ $customer->gst_no }}</td>
                            <td>{{ $customer->category?->name }}</td>
                            <td>{{ $customer->branch?->name ?? 'GLOBAL' }}</td>
                            <td class="text-right">{{ number_format($customer->credit_balance, 2) }}</td>
                            <td><x-status-badge :active="$customer->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-3">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $customers->links() }}</div>
    </div>
@stop
