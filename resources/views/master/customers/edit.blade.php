@extends('adminlte::page')

@section('title', 'Edit Customer')

@section('content_header')
    <h1>Edit Customer "{{ $customer->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.customers.update', $customer) }}" method="POST">
            @csrf
            @method('PUT')
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
