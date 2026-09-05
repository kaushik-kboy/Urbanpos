@extends('adminlte::page')

@section('title', 'Add Item')

@section('content_header')
    <h1>Create Item</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.items.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.items._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.items.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
