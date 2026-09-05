@extends('adminlte::page')

@section('title', 'Add GST Tax')

@section('content_header')
    <h1>Create GST Tax</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.gst-taxes.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.gst-taxes._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.gst-taxes.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
