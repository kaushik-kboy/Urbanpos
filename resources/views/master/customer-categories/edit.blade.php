@extends('adminlte::page')

@section('title', 'Edit Customer Category')

@section('content_header')
    <h1>Edit Customer Category "{{ $customerCategory->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.customer-categories.update', $customerCategory) }}" method="POST">
            @csrf
            @method('PUT')
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
