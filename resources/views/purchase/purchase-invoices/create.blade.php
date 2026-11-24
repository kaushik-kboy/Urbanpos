@extends('adminlte::page')

@section('title', 'Add Purchase Invoice')

@section('plugins.Sweetalert2', true)

@section('classes_body', 'sidebar-mini sidebar-collapse')

@section('content_header')
    <h1>Create Purchase Invoice</h1>
@stop

@section('content')
    <script>
        document.body.classList.add('sidebar-collapse');
    </script>
    <div class="card card-primary card-outline">
        <form action="{{ route('purchase.purchase-invoices.store') }}" method="POST" id="pinv-form" novalidate>
            <input type="hidden" name="posting_key" value="{{ old('posting_key', (string) \Illuminate\Support\Str::uuid()) }}">
            @csrf
            <div class="card-body">
                <x-error-summary />
                @include('purchase.purchase-invoices._form')
            </div>
            <x-transaction-rich-footer
                total-id="display-pinv-final-total"
                items-badge-id="pinv-total-items-badge"
                save-btn-id="pinv-main-save-btn"
                save-btn-text="Save Invoice"
                cancel-route="{{ route('purchase.purchase-invoices.index') }}"
                reset-btn-id="btn-reset-form"
            >
                <x-slot:customLeft>
                    <div id="final-amount-match-badge" class="d-inline-block ml-2"></div>
                </x-slot:customLeft>
            </x-transaction-rich-footer>
        </form>
    </div>
@stop
