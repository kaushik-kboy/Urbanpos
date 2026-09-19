@extends('adminlte::page')

@section('title', 'Add Supplier')

@section('content_header')
    <h1>Create Supplier</h1>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h5><i class="icon fas fa-ban"></i> Please fix the following errors:</h5>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-primary card-outline">
        <form action="{{ route('master.suppliers.store') }}" method="POST">
            @csrf
            <div class="card-body">
                @include('master.suppliers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.suppliers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
