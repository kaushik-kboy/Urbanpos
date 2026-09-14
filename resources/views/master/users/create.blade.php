@extends('adminlte::page')

@section('title', 'Add User')

@section('content_header')
    <h1>Create User</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.users.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('master.users._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.users.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
