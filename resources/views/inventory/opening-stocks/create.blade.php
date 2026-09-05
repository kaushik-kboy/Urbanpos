@extends('adminlte::page')

@section('title', 'Add Opening Stock')

@section('content_header')
    <h1>Create Opening Stock</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.opening-stocks.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('inventory.opening-stocks._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('inventory.opening-stocks.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
