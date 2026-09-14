@extends('adminlte::page')

@section('title', 'Price Fixing')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-tags text-primary mr-2"></i>Price Fixing & Price Levels</h1>
        <div>
            <span class="badge badge-info mr-2 px-3 py-2">F6: Save</span>
            <span class="badge badge-secondary mr-2 px-3 py-2">F9: Clear</span>
            <span class="badge badge-dark px-3 py-2">F10: Close</span>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-1"></i> {{ session('status') }}
        </div>
    @endif

    <!-- Nav tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link font-weight-bold {{ $tab === 'markup_markdown' ? 'active' : '' }}" href="{{ route('inventory.price-fixing.index', ['tab' => 'markup_markdown', 'branch_id' => $branchId]) }}">
                <i class="fas fa-percentage mr-1"></i> 4.6.1 Mark Up / Down
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold {{ $tab === 'price_levels' ? 'active' : '' }}" href="{{ route('inventory.price-fixing.index', ['tab' => 'price_levels', 'branch_id' => $branchId]) }}">
                <i class="fas fa-layer-group mr-1"></i> 4.6.2 Price Level Master
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link font-weight-bold {{ $tab === 'price_level_items' ? 'active' : '' }}" href="{{ route('inventory.price-fixing.index', ['tab' => 'price_level_items', 'branch_id' => $branchId]) }}">
                <i class="fas fa-list-ol mr-1"></i> 4.6.3 Price Level Vs Items
            </a>
        </li>
    </ul>

    @if ($tab === 'markup_markdown')
        <!-- 4.6.1 Mark Up / Down -->
        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title font-weight-bold mb-0">Pricing Rule & Calculation</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('inventory.price-fixing.apply') }}" id="price-fixing-form">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branchId }}">
                            <input type="hidden" name="brand_id" value="{{ $brandId }}">
                            <input type="hidden" name="category_value_id" value="{{ $catValueId }}">
                            <input type="hidden" name="search" value="{{ $search }}">

                            <div class="form-group">
                                <label>Target Price Field</label>
                                <select name="target_field" class="form-control font-weight-bold">
                                    <option value="sell_price">Selling Price (sell_price)</option>
                                    <option value="mrp">Maximum Retail Price (MRP)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Grid &rarr; Mark Up/Down</label>
                                <select name="operation" class="form-control font-weight-bold">
                                    <option value="markup">MarkUp (Increase / Margin)</option>
                                    <option value="markdown">MarkDown (Discount / Reduction)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Selling / Target Based On</label>
                                <select name="base_field" class="form-control">
                                    <option value="cost_price">Purchase Price / Cost Price</option>
                                    <option value="landing_cost">Landing Cost</option>
                                    <option value="mrp">MRP</option>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Amount / %</label>
                                        <select name="calc_type" class="form-control">
                                            <option value="percentage">Percentage (%)</option>
                                            <option value="amount">Fixed Amount (₹)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label>Value</label>
                                        <input type="number" step="0.01" name="value" class="form-control font-weight-bold" placeholder="e.g. 25" required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>RoundOff Type / RoundOff Value</label>
                                <select name="round_off" class="form-control">
                                    <option value="none">None (Exact decimals)</option>
                                    <option value="near_1">Nearest ₹1</option>
                                    <option value="near_10">Nearest ₹10</option>
                                    <option value="round_up">Round Up (Ceil)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Applicable For</label>
                                <div class="custom-control custom-radio mb-1">
                                    <input type="radio" id="scope-filtered" name="apply_scope" value="filtered" class="custom-control-input" checked>
                                    <label class="custom-control-label font-weight-normal" for="scope-filtered">
                                        All items matching filter ({{ $items->total() }} items)
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="scope-selected" name="apply_scope" value="selected" class="custom-control-input">
                                    <label class="custom-control-label font-weight-normal" for="scope-selected">
                                        Only checked items on this page
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block font-weight-bold shadow-sm" onclick="return confirm('Apply this price rule?')">
                                <i class="fas fa-magic mr-1"></i> Apply & Save Prices
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card card-primary card-outline shadow-sm">
                    <div class="card-header bg-light py-2">
                        <form method="GET" class="form-row align-items-center">
                            <input type="hidden" name="tab" value="markup_markdown">
                            <div class="col-md-3 mb-1">
                                <select name="branch_id" class="form-control form-control-sm">
                                    @foreach ($branches as $id => $name)
                                        <option value="{{ $id }}" @selected((string)$branchId === (string)$id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-1">
                                <select name="brand_id" class="form-control form-control-sm">
                                    <option value="">All Brands</option>
                                    @foreach ($brands as $id => $name)
                                        <option value="{{ $id }}" @selected((string)$brandId === (string)$id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-1">
                                <select name="category_value_id" class="form-control form-control-sm">
                                    <option value="">All Categories</option>
                                    @foreach ($categories as $id => $name)
                                        <option value="{{ $id }}" @selected((string)$catValueId === (string)$id)>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-1 d-flex">
                                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm mr-1" placeholder="Search...">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
                            </div>
                        </form>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead class="bg-white">
                                    <tr>
                                        <th style="width: 35px;" class="text-center"><input type="checkbox" id="check-all"></th>
                                        <th>Item Name</th>
                                        <th>Brand</th>
                                        <th class="text-right">Cost Price</th>
                                        <th class="text-right">Branch Sell</th>
                                        <th class="text-right">Branch MRP</th>
                                        <th class="text-right">Stock</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($items as $item)
                                        @php
                                            $stock = $item->stocks->first();
                                            $sellPrice = $stock && $stock->sell_price > 0 ? $stock->sell_price : $item->sell_price;
                                            $mrp = $stock && $stock->mrp > 0 ? $stock->mrp : $item->mrp;
                                            $qty = $stock ? $stock->quantity : 0;
                                        @endphp
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" name="selected_ids[]" value="{{ $item->id }}" form="price-fixing-form" class="item-checkbox">
                                            </td>
                                            <td>
                                                <span class="font-weight-bold">{{ $item->name }}</span>
                                                @if ($item->ean_upc_code)
                                                    <br><small class="text-muted"><i class="fas fa-barcode mr-1"></i>{{ $item->ean_upc_code }}</small>
                                                @endif
                                            </td>
                                            <td><small>{{ $item->brand?->name ?: '-' }}</small></td>
                                            <td class="text-right">₹ {{ number_format($item->cost_price, 2) }}</td>
                                            <td class="text-right font-weight-bold text-primary">₹ {{ number_format($sellPrice, 2) }}</td>
                                            <td class="text-right font-weight-bold">₹ {{ number_format($mrp, 2) }}</td>
                                            <td class="text-right"><span class="badge {{ $qty > 0 ? 'badge-success' : 'badge-secondary' }}">{{ number_format($qty, 0) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="7" class="text-center text-muted py-4">No items found matching the selected filter.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer py-2">{{ $items->appends(request()->query())->links() }}</div>
                </div>
            </div>
        </div>

    @elseif ($tab === 'price_levels')
        <!-- 4.6.2 Price Level Master -->
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title font-weight-bold mb-0">Price Level Master Rules</h5>
                <button type="button" class="btn btn-sm btn-primary" onclick="alert('Create Price Level modal')">
                    <i class="fas fa-plus mr-1"></i> Create Price Level Master
                </button>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped table-hover mb-0">
                    <thead class="bg-white">
                        <tr>
                            <th>Price Level Name</th>
                            <th>Type</th>
                            <th>Based On</th>
                            <th>By</th>
                            <th class="text-right">Default Value</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($defaultPriceLevels as $pl)
                            <tr>
                                <td class="font-weight-bold">{{ $pl['name'] }}</td>
                                <td><span class="badge badge-info">{{ $pl['type'] }}</span></td>
                                <td>{{ $pl['based_on'] }}</td>
                                <td>{{ $pl['by'] }}</td>
                                <td class="text-right font-weight-bold">{{ $pl['value'] }}%</td>
                                <td class="text-right">
                                    <a href="{{ route('inventory.price-fixing.index', ['tab' => 'price_level_items', 'branch_id' => $branchId, 'level' => $pl['name']]) }}" class="btn btn-xs btn-outline-primary">
                                        <i class="fas fa-link mr-1"></i> Map Items
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    @elseif ($tab === 'price_level_items')
        <!-- 4.6.3 Price Level Vs Items -->
        <div class="card card-primary card-outline shadow-sm">
            <div class="card-header bg-light">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <label class="small font-weight-bold mb-1">Pricelevel Name</label>
                        <select class="form-control form-control-sm font-weight-bold">
                            @foreach ($defaultPriceLevels as $pl)
                                <option value="{{ $pl['name'] }}">{{ $pl['name'] }} ({{ $pl['type'] }} by {{ $pl['by'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="small font-weight-bold mb-1">Type</label>
                        <input type="text" class="form-control form-control-sm" readonly value="MarkUp">
                    </div>
                    <div class="col-md-3">
                        <label class="small font-weight-bold mb-1">Based On</label>
                        <input type="text" class="form-control form-control-sm" readonly value="Cost Price">
                    </div>
                    <div class="col-md-3">
                        <label class="small font-weight-bold mb-1">By</label>
                        <input type="text" class="form-control form-control-sm" readonly value="Percentage">
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-striped mb-0">
                        <thead class="bg-white">
                            <tr>
                                <th style="width: 50px;">S.No</th>
                                <th style="width: 140px;">Item Code</th>
                                <th>Description</th>
                                <th style="width: 140px;">Round Off</th>
                                <th style="width: 120px;">Round To</th>
                                <th style="width: 140px;">Mark Value (Override)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items->take(15) as $idx => $item)
                                <tr>
                                    <td class="text-center">{{ $idx + 1 }}</td>
                                    <td><code>{{ $item->ean_upc_code ?: 'ITEM-'.$item->id }}</code></td>
                                    <td class="font-weight-bold">{{ $item->name }}</td>
                                    <td>
                                        <select class="form-control form-control-sm">
                                            <option value="None">None</option>
                                            <option value="Lower">Lower</option>
                                            <option value="Near" selected>Near</option>
                                            <option value="Upper">Upper</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select class="form-control form-control-sm">
                                            <option value="0">0</option>
                                            <option value="0.5">0.5</option>
                                            <option value="1" selected>1</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" step="0.1" class="form-control form-control-sm text-right" placeholder="Default: 15%">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-light d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" onclick="location.reload()">
                    <i class="fas fa-undo mr-1"></i> F9: Clear
                </button>
                <button type="button" class="btn btn-success font-weight-bold px-4 shadow-sm" onclick="alert('Price level mapping saved!')">
                    <i class="fas fa-save mr-1"></i> F6: Save Mapping
                </button>
            </div>
        </div>
    @endif
@stop

@section('js')
<script>
    $('#check-all').on('change', function() {
        $('.item-checkbox').prop('checked', $(this).prop('checked'));
    });

    // Keyboard Shortcuts: F6 Save, F9 Clear, F10 Close
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F6') {
            e.preventDefault();
            let form = document.getElementById('price-fixing-form');
            if (form) form.submit();
        } else if (e.key === 'F9') {
            e.preventDefault();
            location.reload();
        } else if (e.key === 'F10') {
            e.preventDefault();
            window.location.href = "{{ route('home') }}";
        }
    });
</script>
@stop
