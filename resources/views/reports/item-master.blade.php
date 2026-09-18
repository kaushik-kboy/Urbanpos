@extends('adminlte::page')

@section('title', 'Item Master Report')

@section('content_header')
    <h1>Item Master Report</h1>
@stop

@section('content')
    <div class="card card-default mb-3 shadow-none border">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('reports.item-master') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Code, Barcode, Name, Alias..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3 col-sm-6 mb-2">
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
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Status</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="">All Statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2 col-sm-12 mb-2">
                    <button type="submit" class="btn btn-primary btn-sm mr-1"><i class="fas fa-filter"></i> Apply</button>
                    <a href="{{ route('reports.item-master') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0">Items</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary mr-2"><i class="fas fa-print mr-1"></i> Print</button>
                <button type="button" onclick="exportTableToCSV('itemMasterReportTable', 'item-master-report')" class="btn btn-sm btn-outline-success mr-2"><i class="fas fa-file-csv mr-1"></i> Export CSV</button>
                <x-table-column-customizer table-key="reports.item-master" table-id="itemMasterReportTable" button-class="btn btn-sm btn-light border text-secondary" />
            </div>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-striped table-hover mb-0" id="itemMasterReportTable">
                <thead class="thead-light">
                    <tr>
                        <th>Code</th>
                        <th>Barcode</th>
                        <th>Item Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>UOM</th>
                        <th>HSN</th>
                        <th>Tax %</th>
                        <th class="text-right">Cost Price</th>
                        <th class="text-right">Sell Price</th>
                        <th class="text-right">MRP</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><strong>{{ $item->item_code }}</strong></td>
                            <td>{{ $item->ean_upc_code ?: '-' }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->brand?->name ?? '-' }}</td>
                            <td>{{ $item->categoryValue?->name ?? '-' }}</td>
                            <td>{{ $item->base_uom ?: '-' }}</td>
                            <td>{{ $item->hsn_code ?: '-' }}</td>
                            <td>{{ $item->gstTax ? $item->gstTax->percentage . '%' : '-' }}</td>
                            <td class="text-right font-weight-bold text-muted">{{ number_format($item->cost_price, 2) }}</td>
                            <td class="text-right font-weight-bold text-primary">{{ number_format($item->sell_price, 2) }}</td>
                            <td class="text-right font-weight-bold">{{ number_format($item->mrp, 2) }}</td>
                            <td class="text-center"><x-status-badge :active="$item->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-4">No items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="text-muted small">Total: {{ $items->total() }} items</span>
            {{ $items->links() }}
        </div>
    </div>
@stop
