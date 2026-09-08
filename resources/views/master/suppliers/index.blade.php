@extends('adminlte::page')

@section('title', 'Suppliers')

@section('content_header')
    <h1>Supplier</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header">
            <x-import-button :import-route="route('master.suppliers.import')" :sample-route="route('master.suppliers.import-sample')" title="Supplier" />
            <a href="{{ route('master.suppliers.create') }}" class="btn btn-primary btn-sm float-right">
                <i class="fas fa-plus"></i> Add Supplier
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">ID</th>
                        <th>Name</th>
                        <th>Purchase Type</th>
                        <th>Purchase Mode</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td><span class="badge badge-secondary">#{{ $supplier->id }}</span></td>
                            <td><strong>{{ $supplier->name }}</strong></td>
                            <td>{{ $supplier->purchase_type }}</td>
                            <td>{{ $supplier->purchase_mode }}</td>
                            <td><x-status-badge :active="$supplier->status" /></td>
                            <td class="text-right">
                                <a href="{{ route('master.suppliers.edit', $supplier) }}" class="btn btn-xs btn-outline-secondary"><i class="fas fa-pen"></i></a>
                                <form action="{{ route('master.suppliers.destroy', $supplier) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this supplier?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No suppliers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$suppliers->perPage()" />
            {{ $suppliers->appends(request()->query())->links() }}
        </div>
    </div>
@stop
