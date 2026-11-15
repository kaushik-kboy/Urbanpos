@extends('adminlte::page')

@section('title', 'Current Stock Branchwise')

@section('content_header')
    <h1>Current Stock Branchwise</h1>
@stop

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold text-muted small mb-0"><i class="fas fa-boxes mr-1"></i> Stock List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel', 'page' => null]) }}" class="btn btn-sm btn-success mr-2 font-weight-bold shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</a>
                <button type="button" onclick="exportTableToCSV('current-stock-table', 'current-stock-report')" class="btn btn-sm btn-outline-success mr-2"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.current-stock" table-id="current-stock-table" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.current-stock') }}" class="row align-items-end mb-3">
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search Keywords</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Item name, barcode...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Item Code</label>
                    <input type="text" name="item_code" value="{{ request('item_code') }}" class="form-control form-control-sm" placeholder="Exact / partial code...">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Location</label>
                    <select name="branch_id" class="form-control form-control-sm" {{ count($branches) <= 1 ? 'readonly style=pointer-events:none;background:#f4f6f9;' : '' }}>
                        @if(count($branches) > 1)
                            <option value="all" {{ (!request('branch_id') || request('branch_id') === 'all') ? 'selected' : '' }}>All Locations (Combined)</option>
                        @endif
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
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $sId => $sName)
                            <option value="{{ $sId }}" {{ request('supplier_id') == $sId ? 'selected' : '' }}>{{ $sName }}</option>
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
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Updated From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Updated To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-4 col-sm-12 mb-2 d-flex">
                    <button type="submit" class="btn btn-primary btn-sm mr-1 flex-fill"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.current-stock') }}" class="btn btn-outline-secondary btn-sm mr-1"><i class="fas fa-undo"></i> Reset</a>
                    <button type="submit" name="export" value="excel" class="btn btn-success btn-sm font-weight-bold shadow-sm"><i class="fas fa-file-excel mr-1"></i> Export Excel</button>
                </div>
            </form>

            <table id="current-stock-table" class="table table-sm table-striped table-hover">
                <thead class="bg-light">
                    <tr>
                        <th>Store / Location</th>
                        <th>Item Description</th>
                        <th class="text-right">Stock Qty</th>
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
                            <td><strong>{{ $row->branch?->name }}</strong></td>
                            <td>
                                <strong>{{ $row->item?->name }}</strong>
                                @if(!request('branch_id') || request('branch_id') === 'all')
                                    <span class="badge badge-info ml-1" title="Combined stock across both branches">All-Branch Total: {{ number_format($combinedStocks[$row->item_id] ?? $row->quantity) }}</span>
                                @endif
                                <br>
                                <small class="text-muted">
                                    <code>{{ $row->item?->item_code }}</code> &bull;
                                    Brand: {{ $row->item?->brand?->name ?: '-' }} &bull;
                                    Supplier: {{ $row->item?->supplier?->name ?: '-' }}
                                </small>
                            </td>
                            @php
                                $cost = (float) ($row->cost_price > 0 ? $row->cost_price : ($row->item?->cost_price ?? 0));
                                $sell = (float) ($row->sell_price > 0 ? $row->sell_price : ($row->item?->sell_price ?? 0));
                                $mrp = (float) ($row->mrp > 0 ? $row->mrp : ($row->item?->mrp ?? 0));
                            @endphp
                            <td class="text-right font-weight-bold text-success">{{ number_format($row->quantity) }}</td>
                            <td class="text-right">₹ {{ number_format($cost, 2) }}</td>
                            <td class="text-right">₹ {{ number_format($sell, 2) }}</td>
                            <td class="text-right">₹ {{ number_format($mrp, 2) }}</td>
                            <td class="text-right font-weight-bold">₹ {{ number_format($row->quantity * $cost, 2) }}</td>
                            <td class="text-right font-weight-bold text-primary">₹ {{ number_format($row->quantity * $sell, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-boxes fa-2x mb-2 d-block"></i> No stock on hand for the selected criteria.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr class="font-weight-bold bg-light">
                            <td colspan="2" class="text-right font-weight-bold">Total Stock & Value (All Matching):</td>
                            <td class="text-right font-weight-bold text-success" style="font-size: 1.05rem;">{{ number_format((float) $totals->qty) }}</td>
                            <td colspan="3"></td>
                            <td class="text-right font-weight-bold">₹ {{ number_format((float) $totals->cost_value, 2) }}</td>
                            <td class="text-right font-weight-bold text-primary">₹ {{ number_format((float) $totals->sell_value, 2) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
            <div class="mt-2">{{ $rows->links() }}</div>
        </div>
    </div>
@stop
