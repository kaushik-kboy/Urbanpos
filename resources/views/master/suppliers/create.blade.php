@extends('adminlte::page')

@section('title', 'Add Supplier')

@section('content_header')
    <h1>Create Supplier</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.suppliers.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.suppliers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.suppliers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
