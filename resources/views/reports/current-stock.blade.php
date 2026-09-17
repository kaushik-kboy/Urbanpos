@extends('adminlte::page')

@section('title', 'Current Stock Branchwise')

@section('content_header')
    <h1>Current Stock Branchwise</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-boxes mr-1"></i> Stock List</h3>
            <div class="card-tools ml-auto">
                <x-table-column-customizer table-key="reports.current-stock" table-id="current-stock-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.current-stock') }}" class="row align-items-end mb-3">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Item name, code, barcode...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Location</label>
                    <select name="branch_id" class="form-control form-control-sm">
                        <option value="">All Locations</option>
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string) $branchId === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Brand</label>
                    <select name="brand_id" class="form-control form-control-sm">
                        <option value="">All Brands</option>
                        @foreach ($brands as $bId => $bName)
                            <option value="{{ $bId }}" {{ request('brand_id') == $bId ? 'selected' : '' }}>{{ $bName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Category</label>
                    <select name="category_value_id" class="form-control form-control-sm">
                        <option value="">All Categories</option>
                        @foreach ($categories as $cId => $cName)
                            <option value="{{ $cId }}" {{ request('category_value_id') == $cId ? 'selected' : '' }}>{{ $cName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.current-stock') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>

            <table id="current-stock-table" class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Item</th>
                        <th class="text-right">Stock</th>
                        <th class="text-right">Cost Price</th>
                        <th class="text-right">Selling</th>
                        <th class="text-right">MRP</th>
                        <th class="text-right">Value on Cost</th>
                        <th class="text-right">Value on Selling</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row->branch?->name }}</td>
                            <td>{{ $row->item?->name }}</td>
                            <td class="text-right">{{ $row->quantity }}</td>
                            <td class="text-right">{{ number_format($row->item?->cost_price ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($row->item?->sell_price ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($row->item?->mrp ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($row->quantity * ($row->item?->cost_price ?? 0), 2) }}</td>
                            <td class="text-right">{{ number_format($row->quantity * ($row->item?->sell_price ?? 0), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">No stock on hand.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold">
                            <td colspan="6">NetTotal</td>
                            <td class="text-right">{{ number_format($rows->sum(fn ($r) => $r->quantity * ($r->item?->cost_price ?? 0)), 2) }}</td>
                            <td class="text-right">{{ number_format($rows->sum(fn ($r) => $r->quantity * ($r->item?->sell_price ?? 0)), 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@stop
