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
        <form action="{{ route('inventory.damage-stocks.store') }}" method="POST" id="damage-stock-form" novalidate>
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('inventory.damage-stocks._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-ds-final-total"
                items-badge-id="ds-total-items-badge"
                save-btn-id="ds-main-save-btn"
                save-btn-text="Save Damage Stock"
                cancel-route="{{ route('inventory.damage-stocks.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop
