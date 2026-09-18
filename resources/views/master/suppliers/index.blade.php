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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-truck mr-1"></i> Supplier List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.suppliers.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Supplier
                </a>
                <x-table-column-customizer table-key="master.suppliers" table-id="suppliers-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.suppliers.import')" :sample-route="route('master.suppliers.import-sample')" title="Supplier" />
            </div>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.suppliers.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name, GSTIN...">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Purchase Type</label>
                    <select name="purchase_type" class="form-control form-control-sm">
                        <option value="">All Types</option>
                        @foreach ($purchaseTypes as $pt)
                            <option value="{{ $pt }}" {{ request('purchase_type') == $pt ? 'selected' : '' }}>{{ $pt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Purchase Mode</label>
                    <select name="purchase_mode" class="form-control form-control-sm">
                        <option value="">All Modes</option>
                        @foreach ($purchaseModes as $pm)
                            <option value="{{ $pm }}" {{ request('purchase_mode') == $pm ? 'selected' : '' }}>{{ $pm }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-sm btn-primary mr-1">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <a href="{{ route('master.suppliers.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="suppliers-table" class="table table-striped mb-0">
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
