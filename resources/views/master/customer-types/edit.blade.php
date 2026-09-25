@extends('adminlte::page')

@section('title', 'Edit Customer Type')

@section('content_header')
    <h1><i class="fas fa-pen mr-2 text-primary"></i>Edit Customer Type: {{ $customerType->name }}</h1>
@stop

@section('content')
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold">Update Customer Type Details</h3>
        </div>
        <form action="{{ route('master.customer-types.update', $customerType) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.customer-types._form')
            </div>
            <div class="card-footer d-flex justify-content-between">
                <a href="{{ route('master.customer-types.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
                <button type="submit" class="btn btn-primary font-weight-bold">
                    <i class="fas fa-save mr-1"></i> Update Customer Type
                </button>
            </div>
        </form>
    </div>
@stop
