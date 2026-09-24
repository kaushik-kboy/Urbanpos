@extends('adminlte::page')

@section('title', 'Open Till')

@section('content_header')
    <h1>Open Till</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('till.sessions.open') }}" method="POST">
            @csrf
            <div class="card-body">
                <x-error-summary />
                <x-select name="register_id" label="Register" :options="$registers" placeholder="-- Select Register --" />
                <x-field name="opening_cash" label="Opening Cash" type="number" step="0.01" value="0" />
                <x-field name="opened_at" label="Shift Time / Opened At (Manual / Auto)" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Open Till</button>
                <a href="{{ route('till.sessions.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
