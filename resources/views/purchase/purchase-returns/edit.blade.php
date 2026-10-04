@extends('adminlte::page')

@section('title', 'Edit Purchase Return')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center py-0">
        <h1 class="m-0 font-weight-bold text-dark h5"><i class="fas fa-edit mr-2 text-primary"></i> Edit Purchase Return: {{ $purchaseReturn->return_number }}</h1>
        <a href="{{ route('purchase.purchase-returns.index') }}" class="btn btn-secondary btn-xs font-weight-bold shadow-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </div>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <h6 class="font-weight-bold mb-1"><i class="fas fa-exclamation-circle mr-1"></i> Please correct the following errors:</h6>
            <ul class="mb-0 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card card-outline card-primary shadow-sm mb-0">
        <form action="{{ route('purchase.purchase-returns.update', $purchaseReturn) }}" method="POST" id="pr-form" novalidate>
            @csrf
            @method('PUT')
            <div class="card-body py-2 px-3">
                @include('purchase.purchase-returns._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-pr-final-total"
                items-badge-id="pr-total-items-badge"
                save-btn-id="pr-main-save-btn"
                save-btn-text="Update Purchase Return"
                save-btn-icon="fas fa-check-circle"
                cancel-route="{{ route('purchase.purchase-returns.index') }}"
                reset-btn-id="btn-reset-form"
            />
        </form>
    </div>
@stop

