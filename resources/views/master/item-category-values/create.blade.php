@extends('adminlte::page')

@section('title', 'Add Item Category Value')

@section('content_header')
    <h1>Create Item Category Values</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.item-category-values.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.item-category-values._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.item-category-values.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
