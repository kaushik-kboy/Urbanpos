@extends('adminlte::page')

@section('title', 'Add Pet Type')

@section('content_header')
    <h1>Create Pet Type</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.pet-types.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.pet-types._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.pet-types.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
