@extends('adminlte::page')

@section('title', 'Item Price Change')

@section('plugins.Select2', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark font-weight-bold">Item Price Change</h1>
            <small class="text-muted">Branch-wise price revision and configuration</small>
        </div>
        <a href="{{ route('master.items.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-boxes mr-1"></i> Item Master
        </a>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if (isset($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Item Selector Card -->
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('master.item-price-change.index') }}" id="item-select-form">
                <div class="row align-items-center">
                    <div class="col-lg-7 col-md-8">
                        <label for="item_search_select" class="font-weight-bold mb-1 text-secondary">
                            <i class="fas fa-search mr-1"></i> Select or Search Item:
                        </label>
                        <select id="item_search_select" name="item_id" class="form-control" style="width: 100%;">
                            @if ($selectedItem)
                                <option value="{{ $selectedItem->id }}" selected>
                                    {{ $selectedItem->name }} 
                                    @if ($selectedItem->ean_upc_code) (Barcode: {{ $selectedItem->ean_upc_code }}) @endif
                                    @if ($selectedItem->brand) [{{ $selectedItem->brand->name }}] @endif
                                </option>
                            @endif
                        </select>
                    </div>
                    @if ($selectedItem)
                        <div class="col-lg-5 col-md-4 mt-2 mt-md-0 border-left pl-md-3">
                            <div class="small">
                                <span class="text-muted">Selected Item:</span>
                                <strong class="text-dark d-block" style="font-size: 1.05rem;">{{ $selectedItem->name }}</strong>
                                <span class="badge badge-light border mr-1">ID: #{{ $selectedItem->id }}</span>
                                @if ($selectedItem->ean_upc_code)
                                    <span class="badge badge-secondary mr-1"><i class="fas fa-barcode mr-1"></i>{{ $selectedItem->ean_upc_code }}</span>
                                @endif
                                @if ($selectedItem->brand)
                                    <span class="badge badge-info">{{ $selectedItem->brand->name }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($selectedItem)
        <!-- Branch-wise Price Table Form (Exact TruePOS layout) -->
        <form method="POST" action="{{ route('master.item-price-change.update') }}">
            @csrf
            <input type="hidden" name="item_id" value="{{ $selectedItem->id }}">

            <div class="card shadow-sm border mb-4">
                <div class="table-responsive">
                    <table class="table mb-0 truepos-price-table">
                        <thead>
                            <tr class="bg-light">
                                <th style="width: 32%; border-top: 0; border-bottom: 1px solid #dee2e6; font-size: 0.95rem; font-weight: 600; color: #333; padding: 12px 18px;">
                                    Branch
                                </th>
                                <th style="border-top: 0; border-bottom: 1px solid #dee2e6; font-size: 0.95rem; font-weight: 600; color: #333; padding: 12px 18px;">
                                    Price
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($branches as $branch)
                                @php
                                    $stock = $stocksByBranch->get($branch->id);
                                    $costVal = $stock?->cost_price ?? $selectedItem->cost_price;
                                    $landingVal = $stock?->landing_cost ?? $selectedItem->landing_cost;
                                    $sellVal = $stock?->sell_price ?? $selectedItem->sell_price;
                                    $mrpVal = $stock?->mrp ?? $selectedItem->mrp;

                                    $fmt = fn($v) => (float)$v == 0 ? '0' : rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
                                @endphp
                                <tr class="price-row">
                                    <td class="align-middle" style="padding: 18px; border-bottom: 1px solid #e9ecef;">
                                        <div class="font-weight-bold text-dark text-uppercase" style="font-size: 0.92rem; letter-spacing: 0.3px;">
                                            {{ $branch->name }}
                                        </div>
                                    </td>
                                    <td style="padding: 14px 18px; border-bottom: 1px solid #e9ecef;">
                                        <!-- Top Row: Cost Price & Landing Cost -->
                                        <div class="form-row align-items-center mb-2">
                                            <div class="col-sm-6 d-flex align-items-center justify-content-start justify-content-sm-end pr-sm-3 mb-2 mb-sm-0">
                                                <span class="price-label">Cost Price</span>
                                                <input type="number" step="0.01" min="0" 
                                                    name="prices[{{ $branch->id }}][cost_price]" 
                                                    value="{{ $fmt($costVal) }}" 
                                                    class="form-control price-input text-right"
                                                    placeholder="0.00">
                                            </div>
                                            <div class="col-sm-6 d-flex align-items-center justify-content-start justify-content-sm-end pr-sm-3">
                                                <span class="price-label">Landing Cost</span>
                                                <input type="number" step="0.01" min="0" 
                                                    name="prices[{{ $branch->id }}][landing_cost]" 
                                                    value="{{ $fmt($landingVal) }}" 
                                                    class="form-control price-input text-right"
                                                    placeholder="0.00">
                                            </div>
                                        </div>

                                        <!-- Bottom Row: Sell Price & MRP -->
                                        <div class="form-row align-items-center">
                                            <div class="col-sm-6 d-flex align-items-center justify-content-start justify-content-sm-end pr-sm-3 mb-2 mb-sm-0">
                                                <span class="price-label">Sell Price</span>
                                                <input type="number" step="0.01" min="0" 
                                                    name="prices[{{ $branch->id }}][sell_price]" 
                                                    value="{{ $fmt($sellVal) }}" 
                                                    class="form-control price-input font-weight-bold text-right" 
                                                    required 
                                                    placeholder="0.00">
                                            </div>
                                            <div class="col-sm-6 d-flex align-items-center justify-content-start justify-content-sm-end pr-sm-3">
                                                <span class="price-label">MRP</span>
                                                <input type="number" step="0.01" min="0" 
                                                    name="prices[{{ $branch->id }}][mrp]" 
                                                    value="{{ $fmt($mrpVal) }}" 
                                                    class="form-control price-input font-weight-bold text-right" 
                                                    required 
                                                    placeholder="0.00">
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Form Action Buttons (Exact style: Blue Update + White Cancel) -->
                <div class="card-footer bg-white border-top py-3 d-flex align-items-center">
                    <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold mr-2" style="border-radius: 4px; font-size: 0.95rem; background-color: #007bff; border-color: #007bff;">
                        Update
                    </button>
                    <a href="{{ route('master.item-price-change.index', ['item_id' => $selectedItem->id]) }}" class="btn btn-light border px-4 py-2 font-weight-normal" style="border-radius: 4px; font-size: 0.95rem; background: #fff; border-color: #ced4da; color: #495057;">
                        Cancel
                    </a>
                </div>
            </div>
        </form>
    @else
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-1"></i> No item found or selected. Please search or pick an item below.
        </div>
    @endif

    <!-- All Items Browser / Quick Finder -->
    <div class="card card-outline card-secondary shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold"><i class="fas fa-list mr-1"></i> Quick Item Finder</h3>
            <form method="GET" action="{{ route('master.item-price-change.index') }}" class="form-inline ml-auto mt-2 mt-md-0">
                <input type="text" name="search" class="form-control form-control-sm mr-2" placeholder="Filter items by name/code..." value="{{ request('search') }}">
                <select name="brand_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()">
                    <option value="">-- All Brands --</option>
                    @foreach ($brands as $id => $name)
                        <option value="{{ $id }}" {{ request('brand_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary mr-1"><i class="fas fa-search"></i></button>
                @if (request()->hasAny(['search', 'brand_id']))
                    <a href="{{ route('master.item-price-change.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-times"></i></a>
                @endif
            </form>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 130px;">Barcode</th>
                        <th>Item Name</th>
                        <th>Brand</th>
                        <th class="text-right">Default Sell</th>
                        <th class="text-right">Default MRP</th>
                        <th class="text-center" style="width: 110px;">Select</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr class="{{ ($selectedItem && $selectedItem->id == $item->id) ? 'table-primary font-weight-bold' : '' }}">
                            <td>#{{ $item->id }}</td>
                            <td><code>{{ $item->ean_upc_code ?: '-' }}</code></td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->brand?->name ?: '-' }}</td>
                            <td class="text-right text-primary">₹{{ number_format($item->sell_price, 2) }}</td>
                            <td class="text-right text-success">₹{{ number_format($item->mrp, 2) }}</td>
                            <td class="text-center">
                                <a href="{{ route('master.item-price-change.index', array_merge(request()->query(), ['item_id' => $item->id])) }}" 
                                   class="btn btn-xs {{ ($selectedItem && $selectedItem->id == $item->id) ? 'btn-primary disabled' : 'btn-outline-primary' }}">
                                    <i class="fas fa-edit mr-1"></i> Edit Prices
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No items found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center flex-wrap py-2">
            <x-per-page-select :current="$items->perPage()" />
            {{ $items->appends(request()->query())->links() }}
        </div>
    </div>
@stop

@section('css')
<style>
    .truepos-price-table {
        border-collapse: collapse;
    }
    .truepos-price-table th {
        background-color: #fcfcfc !important;
        border-top: 0 !important;
    }
    .price-row:hover {
        background-color: #fafbfc;
    }
    .price-label {
        min-width: 100px;
        font-size: 0.9rem;
        color: #495057;
        font-weight: 500;
        margin-right: 12px;
        text-align: right;
    }
    .price-input {
        max-width: 170px;
        height: 36px;
        border: 1px solid #ced4da;
        border-radius: 4px !important;
        font-size: 0.95rem;
        background-color: #ffffff;
        box-shadow: none;
        transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    }
    .price-input:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }
    .select2-container .select2-selection--single {
        height: 38px !important;
        border-color: #ced4da !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        font-size: 0.95rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
</style>
@stop

@section('js')
<script>
    $(document).ready(function() {
        // Initialize Select2 with AJAX live search
        $('#item_search_select').select2({
            placeholder: 'Type item name, barcode, or alias to search...',
            allowClear: false,
            minimumInputLength: 1,
            ajax: {
                url: '{{ route('master.item-price-change.search') }}',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });

        // When item selection changes, reload page for that item
        $('#item_search_select').on('change', function() {
            var itemId = $(this).val();
            if (itemId) {
                window.location.href = '{{ route('master.item-price-change.index') }}?item_id=' + itemId;
            }
        });
    });
</script>
@stop
