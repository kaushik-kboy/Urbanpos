@extends('adminlte::page')

@section('title', 'Add Tender Type')

@section('content_header')
    <h1>Create Tender Type</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.tender-types.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.tender-types._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.tender-types.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
