@extends('adminlte::page')

@section('title', 'Edit Branch')

@section('content_header')
    <h1>Edit Branch "{{ $branch->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.branches.update', $branch) }}" method="POST">
            @csrf
            @method('PUT')
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
