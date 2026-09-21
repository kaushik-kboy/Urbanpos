@extends('adminlte::page')

@section('title', 'Roles & Permissions')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="font-weight-bold text-dark"><i class="fas fa-user-shield mr-2 text-primary"></i> Roles &amp; Permissions</h1>
        <a href="{{ route('master.roles.create') }}" class="btn btn-primary font-weight-bold shadow-sm">
            <i class="fas fa-plus mr-1"></i> Add New Role
        </a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    @error('role')
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i> {{ $message }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @enderror

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-light border-bottom py-3">
            <form method="GET" action="{{ route('master.roles.index') }}" class="row align-items-end">
                <div class="col-md-5 col-sm-8 mb-2 mb-md-0">
                    <label class="small font-weight-bold text-muted text-uppercase mb-1">Search Role Name</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search by role name...">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search mr-1"></i> Search</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-4 mb-2 mb-md-0">
                    @if(request('search'))
                        <a href="{{ route('master.roles.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-undo mr-1"></i> Reset Filter
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 50px;" class="text-center">#</th>
                        <th>Role Name</th>
                        <th class="text-center" style="width: 160px;">Assigned Users</th>
                        <th class="text-center" style="width: 200px;">Permissions Configured</th>
                        <th style="width: 180px;">Role Type</th>
                        <th class="text-center" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $index => $r)
                        @php
                            $isProtected = in_array($r->name, $protectedRoles, true);
                        @endphp
                        <tr>
                            <td class="text-center font-weight-bold text-muted">{{ $roles->firstItem() + $index }}</td>
                            <td>
                                <strong class="text-dark font-weight-bold" style="font-size: 1rem;">{{ $r->name }}</strong>
                                @if($r->name === 'Owner')
                                    <span class="badge badge-danger ml-2 px-2 py-1"><i class="fas fa-crown mr-1"></i> Super Admin</span>
                                @elseif($r->name === 'Manager')
                                    <span class="badge badge-info ml-2 px-2 py-1"><i class="fas fa-user-tie mr-1"></i> Store Manager</span>
                                @elseif($r->name === 'Cashier')
                                    <span class="badge badge-success ml-2 px-2 py-1"><i class="fas fa-cash-register mr-1"></i> POS Operator</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('master.users.index', ['role' => $r->name]) }}" class="badge badge-light border text-primary font-weight-bold px-3 py-2" title="View all users with this role" style="font-size: 0.88rem;">
                                    <i class="fas fa-users mr-1"></i> {{ $r->users_count }} {{ Str::plural('User', $r->users_count) }}
                                </a>
                            </td>
                            <td class="text-center">
                                @if($r->name === 'Owner')
                                    <span class="badge badge-success px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fas fa-infinity mr-1"></i> All Permissions (Unrestricted)
                                    </span>
                                @else
                                    <span class="badge badge-primary px-3 py-2 font-weight-bold" style="font-size: 0.85rem;">
                                        <i class="fas fa-key mr-1"></i> {{ $r->permissions_count }} Active
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($isProtected)
                                    <span class="badge badge-secondary px-2 py-1 font-weight-normal"><i class="fas fa-lock mr-1"></i> Core System Role</span>
                                @else
                                    <span class="badge badge-light border px-2 py-1 text-muted font-weight-normal"><i class="fas fa-user-tag mr-1"></i> Custom Role</span>
                                @endif
                            </td>
                            <td class="text-center text-nowrap">
                                <a href="{{ route('master.roles.edit', $r) }}" class="btn btn-xs btn-primary font-weight-bold px-2 py-1 mr-1" title="Configure Permissions Matrix">
                                    <i class="fas fa-edit mr-1"></i> Permissions
                                </a>

                                @if(! $isProtected)
                                    <form action="{{ route('master.roles.destroy', $r) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete role \'{{ $r->name }}\'? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger font-weight-bold px-2 py-1" title="Delete Role">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-shield-alt fa-2x mb-2 d-block text-secondary"></i>
                                No roles found matching your query.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($roles->hasPages())
            <div class="card-footer bg-white py-2">
                {{ $roles->links() }}
            </div>
        @endif
    </div>
@stop
