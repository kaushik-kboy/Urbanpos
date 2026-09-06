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
            <a href="{{ route('master.customers.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Customer
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
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
                                <form action="{{ route('master.customers.destroy', $customer) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this customer?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
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
