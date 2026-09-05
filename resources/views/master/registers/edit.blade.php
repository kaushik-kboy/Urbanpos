@extends('adminlte::page')

@section('title', 'Edit Register')

@section('content_header')
    <h1>Edit Register "{{ $register->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.registers.update', $register) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                @include('master.registers._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="{{ route('master.registers.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
