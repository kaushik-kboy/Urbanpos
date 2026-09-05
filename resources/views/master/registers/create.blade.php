@extends('adminlte::page')

@section('title', 'Add Register')

@section('content_header')
    <h1>Create Register</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.registers.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.registers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.registers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
