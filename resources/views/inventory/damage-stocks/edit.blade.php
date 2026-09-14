@extends('adminlte::page')

@section('title', "Edit Damage Stock {$damageStock->damage_number}")

@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark">
                <i class="fas fa-pen mr-2 text-warning"></i> Edit Damage Stock: <strong>{{ $damageStock->damage_number }}</strong>
            </h1>
            <small class="text-muted">Modify line items and reverse previous write-off</small>
        </div>
        <div>
            <a href="{{ route('inventory.damage-stocks.show', $damageStock) }}" class="btn btn-info mr-1 shadow-sm">
                <i class="fas fa-eye mr-1"></i> View Entry
            </a>
            <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-warning shadow-sm">
        <form action="{{ route('inventory.damage-stocks.update', $damageStock) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @include('inventory.damage-stocks._form')
            </div>
            <div class="card-footer bg-light py-2 d-flex justify-content-between">
                <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-secondary">
                    <i class="fas fa-times mr-1"></i> Cancel
                </a>
                <button type="submit" class="btn btn-warning font-weight-bold px-4 shadow-sm">
                    <i class="fas fa-save mr-1"></i> Update Damage Stock
                </button>
            </div>
        </form>
    </div>
@stop
