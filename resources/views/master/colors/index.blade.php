@extends('adminlte::page')

@section('title', 'Colors')

@section('content_header')
    <h1>Color Master</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-palette mr-1"></i> Color List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.colors.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Color
                </a>
                <x-table-column-customizer table-key="master.colors" table-id="colors-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.colors.import')" :sample-route="route('master.colors.import-sample')" title="Color" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="colors-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($colors as $color)
                        <tr>
                            <td>{{ $color->name }}</td>
                            <td><x-status-badge :active="$color->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.colors.edit', $color) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No colors yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$colors->perPage()" />
            {{ $colors->appends(request()->query())->links() }}
        </div>
    </div>
@stop
