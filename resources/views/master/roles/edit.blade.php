@extends('adminlte::page')

@section('title', 'Edit Role - ' . $role->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark">
                <i class="fas fa-user-shield text-primary mr-2"></i> Edit Role: <span class="text-primary">{{ $role->name }}</span>
            </h1>
            <p class="text-muted small mb-0">Modify granular permissions and access levels for this staff role.</p>
        </div>
        <a href="{{ route('master.roles.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> Back to Roles
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <form action="{{ route('master.roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('master.roles._form')
            </div>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between">
                <a href="{{ route('master.roles.index') }}" class="btn btn-default font-weight-bold px-4">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                    <i class="fas fa-save mr-1"></i> Update Role Permissions
                </button>
            </div>
        </form>
    </div>
@stop
