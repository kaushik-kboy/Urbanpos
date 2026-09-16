@extends('adminlte::page')

@section('title', 'Supplier Master Report')

@section('content_header')
    <h1>Supplier Master Report</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.supplier-master') }}" class="row align-items-end">
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Code, Name, Contact, GSTIN, Phone..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">State</label>
                    <select name="state" class="form-control form-control-sm">
                        <option value="">All States</option>
                        @foreach ($states as $st)
                            <option value="{{ $st }}" {{ request('state') == $st ? 'selected' : '' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.supplier-master') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Supplier Name</th>
                        <th>Mobile</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>GST No</th>
                        <th>City / State</th>
                        <th>Address</th>
                        <th class="text-right">Credit Limit</th>
                        <th class="text-right">Credit Balance</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><strong>{{ $supplier->name }}</strong></td>
                            <td>{{ $supplier->mobile ?: '-' }}</td>
                            <td>{{ $supplier->phone ?: '-' }}</td>
                            <td>{{ $supplier->email ?: '-' }}</td>
                            <td>{{ $supplier->gst_no ?: '-' }}</td>
                            <td>{{ $supplier->city ? $supplier->city . ', ' . $supplier->state : $supplier->state }}</td>
                            <td>{{ $supplier->address ?: '-' }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($supplier->credit_limit, 2) }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($supplier->credit_balance, 2) }}</td>
                            <td class="text-center"><x-status-badge :active="$supplier->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center text-muted py-4">No suppliers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Total: {{ $suppliers->total() }} suppliers</span>
            {{ $suppliers->links() }}
        </div>
    </div>
@stop
