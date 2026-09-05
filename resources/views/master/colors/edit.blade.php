@extends('adminlte::page')

@section('title', 'Edit Color')

@section('content_header')
    <h1>Edit Color "{{ $color->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.colors.update', $color) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.colors._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.colors.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
