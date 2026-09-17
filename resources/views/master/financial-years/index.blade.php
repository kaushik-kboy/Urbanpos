@extends('adminlte::page')

@section('title', 'Financial Years')

@section('content_header')
    <h1>Financial Years</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header">
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.financial-years.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add Financial Year
                </a>
                <x-table-column-customizer table-key="master.financial-years" table-id="financial-years-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0">
            <table id="financial-years-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($financialYears as $fy)
                        <tr>
                            <td>{{ $fy->name }}</td>
                            <td>{{ $fy->start_date->toDateString() }}</td>
                            <td>{{ $fy->end_date->toDateString() }}</td>
                            <td>
                                @if ($fy->is_locked)
                                    <span class="badge badge-danger">Locked</span>
                                @else
                                    <span class="badge badge-success">Open</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('master.financial-years.edit', $fy) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                @if ($fy->is_locked)
                                    <form action="{{ route('master.financial-years.reopen', $fy) }}" method="POST" class="d-inline" onsubmit="return confirm('Reopen {{ $fy->name }}? This will be audit-logged.')">
                                        @csrf
                                        <button class="btn btn-xs btn-outline-success">Reopen</button>
                                    </form>
                                @else
                                    <form action="{{ route('master.financial-years.lock', $fy) }}" method="POST" class="d-inline" onsubmit="return confirm('Lock {{ $fy->name }}? No documents will be postable into it until reopened.')">
                                        @csrf
                                        <button class="btn btn-xs btn-outline-danger">Lock</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No financial years defined yet — posting is unrestricted until at least one is created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$financialYears->perPage()" />
            {{ $financialYears->appends(request()->query())->links() }}
        </div>
    </div>
@stop
