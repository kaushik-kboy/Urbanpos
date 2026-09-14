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
            <a href="{{ route('master.users.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add User
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
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
                                <form action="{{ route('master.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
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
