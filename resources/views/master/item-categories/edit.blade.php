@extends('adminlte::page')

@section('title', 'Edit Item Category')

@section('content_header')
    <h1>Edit Item Category "{{ $itemCategory->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.item-categories.update', $itemCategory) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.item-categories._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.item-categories.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
