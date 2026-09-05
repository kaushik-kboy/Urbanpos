@extends('adminlte::page')

@section('title', 'Add Customer Category')

@section('content_header')
    <h1>Create Customer Category</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.customer-categories.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.customer-categories._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.customer-categories.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
