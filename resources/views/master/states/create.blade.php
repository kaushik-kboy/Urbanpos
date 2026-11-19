@extends('adminlte::page')

@section('title', 'Add State')

@section('content_header')
    <h1>Create State</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.states.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.states._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save State</button>
                <a href="{{ route('master.states.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
