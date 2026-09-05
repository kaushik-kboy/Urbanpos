@extends('adminlte::page')

@section('title', 'Add Breed')

@section('content_header')
    <h1>Create Breed</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.breeds.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.breeds._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.breeds.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
