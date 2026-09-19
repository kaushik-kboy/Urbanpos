@extends('adminlte::page')

@section('title', 'Edit Item')

@section('content_header')
    <h1>Edit Item "{{ $item->name }}"</h1>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5><i class="icon fas fa-ban mr-1"></i> Please correct the following errors:</h5>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

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
