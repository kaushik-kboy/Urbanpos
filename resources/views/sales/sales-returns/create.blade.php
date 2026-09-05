@extends('adminlte::page')

@section('title', 'Add Sales Return')

@section('content_header')
    <h1>Create Sales Return</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('sales.sales-returns.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('sales.sales-returns._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('sales.sales-returns.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
