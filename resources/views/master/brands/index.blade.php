@extends('adminlte::page')

@section('title', 'Brands')

@section('content_header')
    <h1>Brands</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-tags mr-1"></i> Brand List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.brands.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Brand
                </a>
                <x-table-column-customizer table-key="master.brands" table-id="brands-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.brands.import')" :sample-route="route('master.brands.import-sample')" title="Brand" />
            </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.brands.index') }}" class="row align-items-end">
                @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                @endif
                @if(request('direction'))
                    <input type="hidden" name="direction" value="{{ request('direction') }}">
                @endif
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search brand name, prefix, alias...">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="{{ route('master.brands.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="brands-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <x-sortable-th column="name" label="Name" data-col-key="name" />
                        <x-sortable-th column="prefix" label="Prefix" data-col-key="prefix" />
                        <x-sortable-th column="alias_code" label="Alias Code" data-col-key="alias_code" />
                        <x-sortable-th column="status" label="Status" data-col-key="status" />
                        <x-sortable-th column="updated_at" label="Updated Time" data-col-key="updated_at" />
                        <th class="text-right" data-col-key="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($brands as $brand)
                        <tr>
                            <td>{{ $brand->name }}</td>
                            <td>{{ $brand->prefix }}</td>
                            <td>{{ $brand->alias_code }}</td>
                            <td><x-status-badge :active="$brand->status" /></td>
                            <td>{{ $brand->updated_at->format('d-m-Y H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.brands.edit', $brand) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No brands yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$brands->perPage()" />
            {{ $brands->appends(request()->query())->links() }}
        </div>
    </div>
@stop
