@extends('adminlte::page')

@section('title', 'Add New Role')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark"><i class="fas fa-plus-circle text-primary mr-2"></i> Create Access Role</h1>
            <p class="text-muted small mb-0">Define a custom staff role and select granular module permissions.</p>
        </div>
        <a href="{{ route('master.roles.index') }}" class="btn btn-outline-secondary btn-sm font-weight-bold">
            <i class="fas fa-arrow-left mr-1"></i> Back to Roles
        </a>
    </div>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <form action="{{ route('master.roles.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('master.roles._form')
            </div>
            <div class="card-footer bg-white border-top py-3 d-flex justify-content-between">
                <a href="{{ route('master.roles.index') }}" class="btn btn-default font-weight-bold px-4">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold px-4 shadow-sm">
                    <i class="fas fa-save mr-1"></i> Save &amp; Create Role
                </button>
            </div>
        </form>
    </div>
@stop
