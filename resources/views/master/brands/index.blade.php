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
        </div>
        <div class="card-body p-0">
            <table id="brands-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Prefix</th>
                        <th>Alias Code</th>
                        <th>Status</th>
                        <th>Updated Time</th>
                        <th class="text-right">Actions</th>
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
