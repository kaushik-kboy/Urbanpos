@extends('adminlte::page')

@section('title', 'Items')

@section('content_header')
    <h1>Item</h1>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @include('master.partials.import-result')

    <div class="card card-primary card-outline">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-box mr-1"></i> Item List</h3>
            <div class="card-tools d-flex align-items-center ml-auto">
                <a href="{{ route('master.items.create') }}" class="btn btn-primary btn-sm mr-2">
                    <i class="fas fa-plus mr-1"></i> Add Item
                </a>
                <x-table-column-customizer table-key="master.items" table-id="items-table" button-class="btn btn-sm btn-outline-secondary mr-2" />
                <x-import-button :import-route="route('master.items.import')" :sample-route="route('master.items.import-sample')" title="Item" />
            </div>
        </div>
        <div class="card-header bg-light border-bottom">
            <form method="GET" action="{{ route('master.items.index') }}" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Search</label>
                    <input type="text" name="name" value="{{ request('name') }}" class="form-control form-control-sm" placeholder="Search name, code, barcode...">
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
                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold mb-1">Supplier</label>
                    <select name="supplier_id" class="form-control form-control-sm">
                        <option value="">All Suppliers</option>
                        @foreach ($suppliers as $sId => $sName)
                            <option value="{{ $sId }}" {{ request('supplier_id') == $sId ? 'selected' : '' }}>{{ $sName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 col-sm-6 mb-2">
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
                    <a href="{{ route('master.items.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table id="items-table" class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>Alias</th>
                        <th>Sell Price</th>
                        <th>Supplier</th>
                        <th>Updated Time</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ $item->id }}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->alias }}</td>
                            <td>{{ number_format($item->sell_price, 2) }}</td>
                            <td>{{ $item->supplier?->name }}</td>
                            <td>{{ $item->updated_at->format('d-m-Y H:i') }}</td>
                            <td class="text-right text-nowrap">
                                <a href="{{ route('master.barcodes.print', ['item_id' => $item->id, 'qty' => 1]) }}" target="_blank" class="btn btn-xs btn-outline-warning mr-1" title="Print Barcode Stickers"><i class="fas fa-barcode"></i></a>
                                <a href="{{ route('reports.smart-analytics', ['item_id' => $item->id]) }}" class="btn btn-xs btn-outline-info mr-1" title="View Sales History (360° Analytics)"><i class="fas fa-chart-line"></i></a>
                                <a href="{{ route('master.items.create', ['copy_from' => $item->id]) }}" class="btn btn-xs btn-outline-primary mr-1" title="Copy Item (New Unique Barcode)"><i class="fas fa-copy"></i></a>
                                <a href="{{ route('master.items.edit', $item) }}" class="btn btn-xs btn-outline-secondary" title="Edit Item"><i class="fas fa-pen"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">No items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
            <x-per-page-select :current="$items->perPage()" />
            {{ $items->appends(request()->query())->links() }}
        </div>
    </div>
@stop
