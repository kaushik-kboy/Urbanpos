@extends('adminlte::page')

@section('title', 'Add Tender Type Value')

@section('content_header')
    <h1>Create Tender Type Value</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.tender-type-values.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.tender-type-values._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.tender-type-values.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
