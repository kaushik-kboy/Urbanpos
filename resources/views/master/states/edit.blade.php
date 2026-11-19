@extends('adminlte::page')

@section('title', 'Edit State')

@section('content_header')
    <h1>Edit State</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.states.update', $state) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.states._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update State</button>
                <a href="{{ route('master.states.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
