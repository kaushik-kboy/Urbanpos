@extends('adminlte::page')

@section('title', 'New Loyalty Program')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="text-dark font-weight-bold mb-0"><i class="fas fa-plus-circle text-primary mr-2"></i>Create Loyalty Program</h1>
            <small class="text-muted">Set up reward earning rules and redemption values for customers</small>
        </div>
        <a href="{{ route('master.loyalty-programs.index') }}" class="btn btn-default">
            <i class="fas fa-arrow-left mr-1"></i>Back to Programs
        </a>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary shadow-sm">
        <form action="{{ route('master.loyalty-programs.store') }}" method="POST">
            @csrf
            @include('master.loyalty-programs._form')
        </form>
    </div>
@stop
