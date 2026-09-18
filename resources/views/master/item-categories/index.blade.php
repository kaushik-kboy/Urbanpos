@extends('adminlte::page')

@section('title', 'Item Categories')

@section('content_header')
    <h1>Item Category</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-sitemap mr-1"></i> Item Category List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.item-categories.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Item Category
                </a>
                <x-table-column-customizer table-key="master.item-categories" table-id="item-categories-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.item-categories.import')" :sample-route="route('master.item-categories.import-sample')" title="Item Category" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="item-categories-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th data-col-key="category-name">Item Category Name</th>
                        <th data-col-key="is-mandatory">Is Mandatory</th>
                        <th data-col-key="status">Status</th>
                        <th data-col-key="actions" data-no-hide="true" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($itemCategories as $itemCategory)
                        <tr>
                            <td data-col-key="category-name">{{ $itemCategory->name }}</td>
                            <td data-col-key="is-mandatory">{{ $itemCategory->is_mandatory ? 'Yes' : 'No' }}</td>
                            <td data-col-key="status"><x-status-badge :active="$itemCategory->status" /></td>
                            <td data-col-key="actions" class="text-right">
                                <a href="{{ route('master.item-categories.edit', $itemCategory) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No item categories yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$itemCategories->perPage()" />
            {{ $itemCategories->appends(request()->query())->links() }}
        </div>
    </div>
@stop
