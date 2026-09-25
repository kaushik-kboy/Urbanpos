@extends('adminlte::page')

@section('title', 'Sales Types Master')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-file-contract mr-2 text-primary"></i>Sales Types Master</h1>
        <div>
            <a href="{{ route('master.sales-types.create') }}" class="btn btn-primary font-weight-bold">
                <i class="fas fa-plus mr-1"></i> Add Sales Type
            </a>
        </div>
    </div>
@stop

@section('content')
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('master.sales-types.index') }}" class="form-row align-items-center">
                <div class="col-md-4 mb-2 mb-md-0">
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, code, description..." value="{{ request('search') }}">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    @if(request('search'))
                        <a href="{{ route('master.sales-types.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body table-responsive p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th style="width: 100px;">Status</th>
                        <th class="text-right" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($salesTypes as $index => $type)
                        <tr>
                            <td>{{ $salesTypes->firstItem() + $index }}</td>
                            <td class="font-weight-bold text-dark">{{ $type->name }}</td>
                            <td><span class="badge badge-light border">{{ $type->code ?: '—' }}</span></td>
                            <td class="text-muted small">{{ $type->description ?: '—' }}</td>
                            <td>
                                @if($type->status)
                                    <span class="badge badge-success"><i class="fas fa-check-circle mr-1"></i> Active</span>
                                @else
                                    <span class="badge badge-secondary"><i class="fas fa-times-circle mr-1"></i> Inactive</span>
                                @endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('master.sales-types.edit', $type) }}" class="btn btn-xs btn-outline-primary" title="Edit Sales Type">
                                    <i class="fas fa-pen"></i> Edit
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle mr-1"></i> No Sales Types found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($salesTypes->hasPages())
            <div class="card-footer py-2">
                {{ $salesTypes->links() }}
            </div>
        @endif
    </div>
@stop
