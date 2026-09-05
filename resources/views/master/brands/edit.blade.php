@extends('adminlte::page')

@section('title', 'Edit Brand')

@section('content_header')
    <h1>Edit Brand "{{ $brand->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.brands.update', $brand) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.brands._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.brands.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
