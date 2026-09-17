@extends('adminlte::page')

@section('title', 'Customers')

@section('content_header')
    <h1>Customer</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.customers.import')" :sample-route="route('master.customers.import-sample')" title="Customer" />
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.customers.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Customer
                </a>
                <x-table-column-customizer table-key="master.customers" table-id="customers-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.customers.index') }}" class="row align-items-end">
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, Code, Phone...">
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
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="{{ route('master.customers.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="customers-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>Customer Id</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->customer_code }}</td>
                            <td>{{ $customer->category?->name }}</td>
                            <td><x-status-badge :active="$customer->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.customers.edit', $customer) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$customers->perPage()" />
            {{ $customers->appends(request()->query())->links() }}
        </div>
    </div>
@stop
