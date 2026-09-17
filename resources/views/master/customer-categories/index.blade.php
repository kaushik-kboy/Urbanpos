@extends('adminlte::page')

@section('title', 'Customer Categories')

@section('content_header')
    <h1>Customer Category</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.customer-categories.import')" :sample-route="route('master.customer-categories.import-sample')" title="Customer Category" />
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.customer-categories.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Customer Category
                </a>
                <x-table-column-customizer table-key="master.customer-categories" table-id="customer-categories-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="customer-categories-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Business Type</th>
                        <th>Discount %</th>
                        <th>Loyalty</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customerCategories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->business_type }}</td>
                            <td>{{ $category->discount_percent }}</td>
                            <td>{{ $category->enable_loyalty ? 'Yes' : 'No' }}</td>
                            <td><x-status-badge :active="$category->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.customer-categories.edit', $category) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No customer categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$customerCategories->perPage()" />
            {{ $customerCategories->appends(request()->query())->links() }}
        </div>
    </div>
@stop
