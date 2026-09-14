@extends('adminlte::page')

@section('title', 'Add Financial Year')

@section('content_header')
    <h1>Create Financial Year</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.financial-years.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('master.financial-years._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.financial-years.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
