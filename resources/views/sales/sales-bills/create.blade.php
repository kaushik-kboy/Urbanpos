@extends('adminlte::page')

@section('title', 'Add Sales Bill')

@section('content_header')
    <h1>Create Sales Bill</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('sales.sales-bills.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('sales.sales-bills._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('sales.sales-bills.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
