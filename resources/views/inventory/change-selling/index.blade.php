@extends('adminlte::page')

@section('title', 'Change Selling Price')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-dollar-sign text-success mr-2"></i>Change Selling Price & MRP</h1>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-light">
            <form method="GET" class="form-row align-items-center">
                <div class="col-md-3 mb-1">
                    <label class="font-weight-bold mr-1">Branch:</label>
                    <select name="branch_id" class="form-control form-control-sm d-inline-block w-auto" onchange="this.form.submit()">
                        @foreach ($branches as $id => $name)
                            <option value="{{ $id }}" @selected((string)$branchId === (string)$id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-1">
                    <select name="brand_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All Brands</option>
                        @foreach ($brands as $id => $name)
                            <option value="{{ $id }}" @selected((string)$brandId === (string)$id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-1">
                    <select name="category_value_id" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected((string)$catValueId === (string)$id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-1 d-flex">
                    <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm mr-1" placeholder="Search item or barcode...">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('inventory.change-selling.update') }}">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branchId }}">

            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                    <span class="text-muted small font-weight-bold">Items Listing</span>
                    <x-table-column-customizer table-key="inventory.change-selling" table-id="changeSellingTable" button-class="btn btn-sm btn-light border text-secondary" />
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0" id="changeSellingTable">
                        <thead class="bg-white">
                            <tr>
                                <th>#</th>
                                <th>Item Details</th>
                                <th>Brand</th>
                                <th class="text-right">Cost Price</th>
                                <th class="text-right">Current Stock</th>
                                <th style="width: 150px;">New Sell Price (₹)</th>
                                <th style="width: 150px;">New MRP (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $idx => $item)
                                @php
                                    $stock = $item->stocks->first();
                                    $currentSell = $stock && $stock->sell_price > 0 ? $stock->sell_price : $item->sell_price;
                                    $currentMrp = $stock && $stock->mrp > 0 ? $stock->mrp : $item->mrp;
                                    $qty = $stock ? $stock->quantity : 0;
                                @endphp
                                <tr>
                                    <td>{{ $items->firstItem() + $idx }}</td>
                                    <td>
                                        <span class="font-weight-bold">{{ $item->name }}</span>
                                        @if ($item->ean_upc_code)
                                            <br><small class="text-muted"><i class="fas fa-barcode mr-1"></i>{{ $item->ean_upc_code }}</small>
                                        @endif
                                    </td>
                                    <td><small>{{ $item->brand?->name ?: '-' }}</small></td>
                                    <td class="text-right text-muted">₹ {{ number_format($item->cost_price, 2) }}</td>
                                    <td class="text-right">
                                        <span class="badge {{ $qty > 0 ? 'badge-success' : 'badge-secondary' }}">
                                            {{ number_format($qty, 0) }}
                                        </span>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" 
                                               name="prices[{{ $item->id }}][sell_price]" 
                                               value="{{ $currentSell }}" 
                                               class="form-control form-control-sm font-weight-bold text-primary">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" 
                                               name="prices[{{ $item->id }}][mrp]" 
                                               value="{{ $currentMrp }}" 
                                               class="form-control form-control-sm font-weight-bold">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No items found matching the selected filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center py-2">
                <div>{{ $items->appends(request()->query())->links() }}</div>
                @if ($items->isNotEmpty())
                    <button type="submit" class="btn btn-success font-weight-bold px-4 shadow-sm">
                        <i class="fas fa-save mr-1"></i> Save All Price Changes
                    </button>
                @endif
            </div>
        </form>
    </div>
@stop
