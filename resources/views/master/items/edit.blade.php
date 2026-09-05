@extends('adminlte::page')

@section('title', 'Edit Item')

@section('content_header')
    <h1>Edit Item "{{ $item->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.items.update', $item) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.items._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.items.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
