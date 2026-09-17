@extends('adminlte::page')

@section('title', 'Areas')

@section('content_header')
    <h1>Area</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.areas.import')" :sample-route="route('master.areas.import-sample')" title="Area" />
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.areas.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Area
                </a>
                <x-table-column-customizer table-key="master.areas" table-id="areas-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="areas-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($areas as $area)
                        <tr>
                            <td>{{ $area->name }}</td>
                            <td>{{ $area->branch?->name ?? 'GLOBAL' }}</td>
                            <td><x-status-badge :active="$area->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.areas.edit', $area) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No areas yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$areas->perPage()" />
            {{ $areas->appends(request()->query())->links() }}
        </div>
    </div>
@stop
