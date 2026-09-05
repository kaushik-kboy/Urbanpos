@extends('adminlte::page')

@section('title', 'Edit UOM')

@section('content_header')
    <h1>Edit UOM "{{ $uom->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.uoms.update', $uom) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.uoms._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.uoms.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
