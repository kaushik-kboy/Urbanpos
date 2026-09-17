@extends('adminlte::page')

@section('title', 'Users')

@section('content_header')
    <h1>Users &amp; Roles</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @error('user')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="card card-primary card-outline">
        <div class="card-header">
            <div class="card-tools float-right d-flex align-items-center">
                <a href="{{ route('master.users.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus"></i> Add User
                </a>
                <x-table-column-customizer table-key="master.users" table-id="users-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.users.index') }}" class="row align-items-end">
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or Email...">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Role</label>
                    <select name="role" class="form-control form-control-sm">
                        <option value="">All Roles</option>
                        @foreach ($roles as $rName)
                            <option value="{{ $rName }}" {{ request('role') == $rName ? 'selected' : '' }}>{{ $rName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Branch</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="{{ route('master.users.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="users-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="badge badge-info">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>{{ $user->branch?->name ?? 'All Branches' }}</td>
                            <td class="text-right">
                                <a href="{{ route('master.users.edit', $user) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No users yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$users->perPage()" />
            {{ $users->appends(request()->query())->links() }}
        </div>
    </div>
@stop
