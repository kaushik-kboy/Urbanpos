@extends('adminlte::page')

@section('title', 'New Damage Stock')

@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-boxes-alt mr-2 text-danger"></i> New Damage Stock Entry</h1>
            <small class="text-muted">Adjust stock quantity and write off at cost inside a location</small>
        </div>
        <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>
@stop

@section('content')
    <div class="card card-outline card-danger shadow-sm">
        <form action="{{ route('inventory.damage-stocks.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('inventory.damage-stocks._form')
            </div>
            <div class="card-footer bg-light py-2 d-flex justify-content-between">
                <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Cancel
                </a>
                <div>
                    <button type="reset" class="btn btn-outline-secondary mr-2">
                        <i class="fas fa-eraser mr-1"></i> Clear
                    </button>
                    <button type="submit" class="btn btn-danger font-weight-bold px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> Save Damage Stock (F6)
                    </button>
                </div>
            </div>
        </form>
    </div>
@stop
