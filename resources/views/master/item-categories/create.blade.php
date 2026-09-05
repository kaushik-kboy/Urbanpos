@extends('adminlte::page')

@section('title', 'Add Item Category')

@section('content_header')
    <h1>Create Item Category</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.item-categories.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.item-categories._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.item-categories.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
