@extends('adminlte::page')

@section('title', 'Add Opening Stock')

@section('plugins.Select2', true)

@section('content_header')
    <h1>Create Opening Stock</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.opening-stocks.store') }}" method="POST" id="opening-stock-form" novalidate>
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('inventory.opening-stocks._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" id="btn-reset-form" class="btn btn-warning mr-2 btn-reset-form" title="Reset all form fields">
                    <i class="fas fa-undo mr-1"></i> Reset Form
                </button>
                <a href="{{ route('inventory.opening-stocks.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
