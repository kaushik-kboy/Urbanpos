@extends('adminlte::page')

@section('title', 'UOM')

@section('content_header')
    <h1>UOM (Conversion)</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.uoms.import')" :sample-route="route('master.uoms.import-sample')" title="UOM" />
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.uoms.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add UOM
                </a>
                <x-table-column-customizer table-key="master.uoms" table-id="uoms-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="uoms-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Alias</th>
                        <th>Status</th>
                        <th>Updated Time</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($uoms as $uom)
                        <tr>
                            <td>{{ $uom->name }}</td>
                            <td>{{ $uom->alias }}</td>
                            <td><x-status-badge :active="$uom->status" /></td>
                            <td>{{ $uom->updated_at->format('d-m-Y H:i') }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.uoms.edit', $uom) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">No UOMs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$uoms->perPage()" />
            {{ $uoms->appends(request()->query())->links() }}
        </div>
    </div>
@stop
