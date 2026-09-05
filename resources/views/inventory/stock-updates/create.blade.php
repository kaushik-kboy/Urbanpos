@extends('adminlte::page')

@section('title', 'Add Stock Update')

@section('content_header')
    <h1>Create Stock Update</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.stock-updates.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('inventory.stock-updates._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('inventory.stock-updates.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
