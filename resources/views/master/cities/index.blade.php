@extends('adminlte::page')

@section('title', 'Cities')

@section('content_header')
    <h1>City Master</h1>
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
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-city mr-1"></i> City List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <form action="{{ route('master.cities.index') }}" method="GET" class="form-inline mr-2">
                    <select name="state_id" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
                        <option value="">All States</option>
                        @foreach ($states as $sId => $sName)
                            <option value="{{ $sId }}" @selected(($stateId ?? '') == $sId)>{{ $sName }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="search" value="{{ $search ?? '' }}" class="form-control form-control-sm mr-1" placeholder="Search city...">
                    <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-search"></i></button>
                </form>
                <a href="{{ route('master.cities.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add City
                </a>
                <x-import-button :import-route="route('master.cities.import')" :sample-route="route('master.cities.import-sample')" title="City" />
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>City Name</th>
                        <th>State</th>
                        <th>Country</th>
                        <th>Status</th>
                        <th class="text-right" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cities as $idx => $city)
                        <tr>
                            <td>{{ $cities->firstItem() + $idx }}</td>
                            <td class="font-weight-bold">{{ $city->name }}</td>
                            <td><span class="badge badge-light border">{{ $city->state?->name ?? '—' }}</span></td>
                            <td>{{ $city->state?->country ?? 'India' }}</td>
                            <td><x-status-badge :active="$city->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.cities.edit', $city) }}" class="btn btn-xs btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No cities found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$cities->perPage()" />
            {{ $cities->appends(request()->query())->links() }}
        </div>
    </div>
@stop
