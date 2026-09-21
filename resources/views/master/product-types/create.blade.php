@extends('adminlte::page')

@section('title', 'Add Product Type')

@section('content_header')
    <h1><i class="fas fa-plus mr-2 text-primary"></i>Add Product Type</h1>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold">New Product Type Details</h3>
        </div>
        <form action="{{ route('master.product-types.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.product-types._form')
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('master.product-types.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Save Product Type
                </button>
            </div>
        </form>
    </div>
@stop
