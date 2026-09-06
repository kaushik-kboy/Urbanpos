@extends('adminlte::page')

@section('title', 'Tender Type')

@section('content_header')
    <h1>Tender Type</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.tender-types.import')" :sample-route="route('master.tender-types.import-sample')" title="Tender Type" />
            <a href="{{ route('master.tender-types.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Tender Type
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenderTypes as $tenderType)
                        <tr>
                            <td>{{ $tenderType->name }}</td>
                            <td>{{ $tenderType->type }}</td>
                            <td>{{ $tenderType->branch?->name ?? 'GLOBAL' }}</td>
                            <td><x-status-badge :active="$tenderType->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.tender-types.edit', $tenderType) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.tender-types.destroy', $tenderType) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this tender type?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No tender types yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$tenderTypes->perPage()" />
            {{ $tenderTypes->appends(request()->query())->links() }}
        </div>
    </div>
@stop
