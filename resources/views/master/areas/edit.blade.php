@extends('adminlte::page')

@section('title', 'Edit Area')

@section('content_header')
    <h1>Edit Area "{{ $area->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.areas.update', $area) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.areas._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.areas.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
