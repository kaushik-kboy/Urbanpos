@extends('adminlte::page')

@section('title', 'Edit Item Category Value')

@section('content_header')
    <h1>Edit Item Category Value "{{ $itemCategoryValue->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.item-category-values.update', $itemCategoryValue) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.item-category-values._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.item-category-values.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
