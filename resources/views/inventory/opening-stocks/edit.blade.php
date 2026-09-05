@extends('adminlte::page')

@section('title', 'Edit Opening Stock')

@section('content_header')
    <h1>Edit Opening Stock "{{ $openingStock->entry_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.opening-stocks.update', $openingStock) }}" method="POST">
            @csrf
            @method('PUT')
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
