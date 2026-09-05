@extends('adminlte::page')

@section('title', 'Edit Sales Return')

@section('content_header')
    <h1>Edit Sales Return "{{ $salesReturn->return_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('sales.sales-returns.update', $salesReturn) }}" method="POST">
            @csrf
            @method('PUT')
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
