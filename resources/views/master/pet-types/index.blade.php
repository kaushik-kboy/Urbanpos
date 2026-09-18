@extends('adminlte::page')

@section('title', 'Pet Types')

@section('content_header')
    <h1>Pet Types</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-paw mr-1"></i> Pet Type List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.pet-types.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Pet Type
                </a>
                <x-table-column-customizer table-key="master.pet-types" table-id="pet-types-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.pet-types.import')" :sample-route="route('master.pet-types.import-sample')" title="Pet Type" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="pet-types-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($petTypes as $petType)
                        <tr>
                            <td>{{ $petType->name }}</td>
                            <td><x-status-badge :active="$petType->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.pet-types.edit', $petType) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No pet types yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$petTypes->perPage()" />
            {{ $petTypes->appends(request()->query())->links() }}
        </div>
    </div>
@stop
