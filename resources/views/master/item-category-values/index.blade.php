@extends('adminlte::page')

@section('title', 'Item Category Values')

@section('content_header')
    <h1>Item Category Value</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-layer-group mr-1"></i> Category Value List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.item-category-values.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Category Value
                </a>
                <x-table-column-customizer table-key="master.item-category-values" table-id="item-category-values-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.item-category-values.import')" :sample-route="route('master.item-category-values.import-sample')" title="Item Category Value" />
            </div>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.item-category-values.index') }}" class="form-row align-items-center">
                <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="name" value="{{ request('name') }}" class="form-control" placeholder="Search by name...">
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-2 mb-md-0">
                    <select name="category_id" class="form-control form-control-sm select2" data-placeholder="All Categories">
                        <option value="">All Categories</option>
                        @foreach ($itemCategories as $catId => $catName)
                            <option value="{{ $catId }}" {{ request('category_id') == $catId ? 'selected' : '' }}>{{ $catName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2 mb-md-0">
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Status</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    @if(request()->filled('name') || request()->filled('category_id') || request()->filled('status'))
                        <a href="{{ route('master.item-category-values.index') }}" class="btn btn-sm btn-outline-secondary ml-1">
                            <i class="fas fa-times"></i> Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="item-category-values-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category Head</th>
                        <th>Show in Webstore</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($itemCategoryValues as $value)
                        <tr>
                            <td>{{ $value->name }}</td>
                            <td>{{ $value->itemCategory?->name }}</td>
                            <td>{{ $value->show_in_webstore ? 'Yes' : 'No' }}</td>
                            <td><x-status-badge :active="$value->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.item-category-values.edit', $value) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No item category values yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$itemCategoryValues->perPage()" />
            {{ $itemCategoryValues->appends(request()->query())->links() }}
        </div>
    </div>
@stop
