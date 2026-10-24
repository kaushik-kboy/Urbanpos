@extends('adminlte::page')

@section('title', 'Sales Bill Types Master')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold"><i class="fas fa-file-contract mr-2 text-primary"></i>Sales Bill Types Master</h1>
            <p class="text-muted small mb-0">Branch-wise Sales Bill Types configuration (e.g., Motera: Retail, Home, Spa | Satellite: B2B, IPC, Home, Retail).</p>
        </div>
        <div>
            <a href="{{ route('master.sales-types.create') }}" class="btn btn-primary font-weight-bold shadow-sm">
                <i class="fas fa-plus mr-1"></i> Add Sales Bill Type
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
                <div class="col-md-3 mb-2 mb-md-0">
                    <select name="branch_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="all">-- All Branches --</option>
                        @if(isset($branches))
                            @foreach($branches as $bId => $bName)
                                <option value="{{ $bId }}" @selected(request('branch_id') == $bId)>{{ $bName }}</option>
                            @endforeach
                        @endif
                        <option value="global" @selected(request('branch_id') === 'global')>Global (All Branches)</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2 mb-md-0">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="all">-- All Status --</option>
                        <option value="1" @selected(request('status') === '1')>Active</option>
                        <option value="0" @selected(request('status') === '0')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    @if(request('search') || request('branch_id') || request('status'))
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
                        <th style="width: 50px;">#</th>
                        <th>Sales Bill Type</th>
                        <th>Branch</th>
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
                            <td class="font-weight-bold text-dark">
                                <i class="fas fa-tag text-primary mr-1"></i> {{ $type->name }}
                            </td>
                            <td>
                                @if($type->branch)
                                    <span class="badge badge-info px-2 py-1"><i class="fas fa-store mr-1"></i>{{ $type->branch->name }}</span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1"><i class="fas fa-globe mr-1"></i>Global (All Branches)</span>
                                @endif
                            </td>
                            <td><span class="badge badge-light border font-monospace">{{ $type->code ?: '—' }}</span></td>
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
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle mr-1"></i> No Sales Bill Types found.
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
