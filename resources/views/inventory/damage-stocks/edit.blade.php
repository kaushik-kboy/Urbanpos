@extends('adminlte::page')

@section('title', 'Edit Damage Stock')

@section('content_header')
    <h1>Edit Damage Stock "{{ $damageStock->damage_number }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('inventory.damage-stocks.update', $damageStock) }}" method="POST">
            @csrf
            @method('PUT')
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
