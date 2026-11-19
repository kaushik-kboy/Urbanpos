@extends('adminlte::page')

@section('title', 'Edit City')

@section('content_header')
    <h1>Edit City</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.cities.update', $city) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.cities._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Update City</button>
                <a href="{{ route('master.cities.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
