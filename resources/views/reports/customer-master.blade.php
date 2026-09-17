@extends('adminlte::page')

@section('title', 'Customer Master')

@section('content_header')
    <h1>Customer Master</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.customer-master') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Code, Name, Mobile..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Category</label>
                    <select name="category_id" class="form-control form-control-sm">
                        <option value="">All Categories</option>
                        @foreach ($categories as $cId => $cName)
                            <option value="{{ $cId }}" {{ request('category_id') == $cId ? 'selected' : '' }}>{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="" {{ empty($branchId) ? 'selected' : '' }}>All Branches</option>
                        @foreach ($branches as $bId => $bName)
                            <option value="{{ $bId }}" @selected((string) ($branchId ?? '') === (string) $bId)>{{ $bName }}</option>
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
                <div class="col-md-2 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.customer-master') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

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
