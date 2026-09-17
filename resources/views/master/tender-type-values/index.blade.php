@extends('adminlte::page')

@section('title', 'Tender Type Values')

@section('content_header')
    <h1>Tender Type Values</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.tender-type-values.import')" :sample-route="route('master.tender-type-values.import-sample')" title="Tender Type Value" />
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.tender-type-values.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Tender Type Value
                </a>
                <x-table-column-customizer table-key="master.tender-type-values" table-id="tender-type-values-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="tender-type-values-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Tender Name</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenderTypeValues as $value)
                        <tr>
                            <td>{{ $value->name }}</td>
                            <td>{{ $value->tenderType?->name }}</td>
                            <td>{{ $value->branch?->name ?? 'GLOBAL' }}</td>
                            <td><x-status-badge :active="$value->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.tender-type-values.edit', $value) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No tender type values yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$tenderTypeValues->perPage()" />
            {{ $tenderTypeValues->appends(request()->query())->links() }}
        </div>
    </div>
@stop
