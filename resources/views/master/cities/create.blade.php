@extends('adminlte::page')

@section('title', 'Add City')

@section('content_header')
    <h1>Create City</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.cities.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.cities._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save City</button>
                <a href="{{ route('master.cities.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
