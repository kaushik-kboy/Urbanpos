@extends('adminlte::page')

@section('title', 'Add Customer')

@section('content_header')
    <h1>Create Customer</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.customers.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.customers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.customers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
