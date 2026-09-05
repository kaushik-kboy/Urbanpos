@extends('adminlte::page')

@section('title', 'Add Branch')

@section('content_header')
    <h1>Create Branch</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.branches.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.branches._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.branches.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
