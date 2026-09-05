@extends('adminlte::page')

@section('title', 'Edit Supplier')

@section('content_header')
    <h1>Edit Supplier "{{ $supplier->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.suppliers.update', $supplier) }}" method="POST">
            @csrf
            @method('PUT')
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
