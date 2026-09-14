@extends('adminlte::page')

@section('title', 'Edit Financial Year')

@section('content_header')
    <h1>Edit Financial Year "{{ $financialYear->name }}"</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <form action="{{ route('master.financial-years.update', $financialYear) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="card-body">
                <x-error-summary />
                @if ($financialYear->is_locked)
                    <div class="alert alert-warning">This Financial Year is locked. Reopen it from the list page before changing its dates.</div>
                @endif
                @include('master.financial-years._form')
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary" @if($financialYear->is_locked) disabled @endif>Save</button>
                <a href="{{ route('master.financial-years.index') }}" class="btn btn-default">Cancel</a>
            </div>
        </form>
    </div>
@stop
