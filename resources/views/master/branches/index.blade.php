@extends('adminlte::page')

@section('title', 'Branches')

@section('content_header')
    <h1>Branch</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.branches.import')" :sample-route="route('master.branches.import-sample')" title="Branch" />
            <a href="{{ route('master.branches.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Branch
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Address Line1</th>
                        <th>City</th>
                        <th>Business Type</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($branches as $branch)
                        <tr>
                            <td>{{ $branch->name }}</td>
                            <td>{{ $branch->address_line1 }}</td>
                            <td>{{ $branch->city }}</td>
                            <td>{{ $branch->business_type }}</td>
                            <td><x-status-badge :active="$branch->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.branches.edit', $branch) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.branches.destroy', $branch) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this branch?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No branches yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$branches->perPage()" />
            {{ $branches->appends(request()->query())->links() }}
        </div>
    </div>
@stop
