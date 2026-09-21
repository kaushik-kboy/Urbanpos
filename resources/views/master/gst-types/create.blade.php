@extends('adminlte::page')

@section('title', 'Add GST Type')

@section('content_header')
    <h1><i class="fas fa-plus mr-2 text-primary"></i>Add GST Type</h1>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold">New GST Type Details</h3>
        </div>
        <form action="{{ route('master.gst-types.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.gst-types._form')
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('master.gst-types.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Save GST Type
                </button>
            </div>
        </form>
    </div>
@stop
