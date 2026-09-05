@extends('adminlte::page')

@section('title', 'Edit Stock Update')

@section('content_header')
    <h1>Edit Stock Update "{{ $stockUpdate->update_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.stock-updates.update', $stockUpdate) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('inventory.stock-updates._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('inventory.stock-updates.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
