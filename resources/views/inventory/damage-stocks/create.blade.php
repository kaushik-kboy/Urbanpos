@extends('adminlte::page')

@section('title', 'New Damage Stock')

@section('content_header')
    <h1>New Damage Stock</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.damage-stocks.store') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('inventory.damage-stocks._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
