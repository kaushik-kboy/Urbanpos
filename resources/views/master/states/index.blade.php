@extends('adminlte::page')

@section('title', 'States')

@section('content_header')
    <h1>State Master</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-flag mr-1"></i> State List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <form action="{{ route('master.states.index') }}" method="GET" class="form-inline mr-2">
                    <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm mr-1" placeholder="Search state...">
                    <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-search"></i></button>
                </form>
                <a href="{{ route('master.states.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add State
                </a>
                <x-import-button :import-route="route('master.states.import')" :sample-route="route('master.states.import-sample')" title="State" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>State Name</th>
                        <th>State Code</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th class="text-right" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($states as $idx => $state)
                        <tr>
                            <td>{{ $states->firstItem() + $idx }}</td>
                            <td class="font-weight-bold">{{ $state->name }}</td>
                            <td><span class="badge badge-light border">{{ $state->code ?: '—' }}</span></td>
                            <td>{{ $state->country }}</td>
                            <td><x-status-badge :active="$state->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.states.edit', $state) }}" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No states found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$states->perPage()" />
            {{ $states->appends(request()->query())->links() }}
        </div>
    </div>
@stop
