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

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-triangle mr-1"></i> <strong>Please correct the following errors:</strong>
            <ul class="mb-0 mt-1 pl-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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
                    <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm mr-1" placeholder="Search item name, code...">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('inventory.change-selling.update') }}" id="changeSellingForm">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $branchId }}">

            <div class="card-body p-0">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom bg-light">
                    <span class="text-muted small font-weight-bold">Items & Batches Listing</span>
                    <x-table-column-customizer table-key="inventory.change-selling" table-id="changeSellingTable" button-class="btn btn-sm btn-light border text-secondary" />
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="changeSellingTable">
                        <thead class="bg-white">
                            <tr>
                                <th style="width: 45px;">#</th>
                                <th>Item Details</th>
                                <th style="width: 140px;">Batch No</th>
                                <th style="width: 110px;">Expiry Date</th>
                                <th class="text-right" style="width: 110px;">Cost (₹)</th>
                                <th class="text-right" style="width: 100px;">Stock</th>
                                <th style="width: 140px;">New Sell Price (₹)</th>
                                <th style="width: 140px;">New MRP (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $rowIdx = 0; @endphp
                            @forelse ($items as $itemIdx => $item)
                                @php
                                    $batches = $item->display_batches ?? collect();
                                    $stock = $item->stocks->first();
                                    $defaultSell = $stock && $stock->sell_price > 0 ? $stock->sell_price : ($item->sell_price ?: 0);
                                    $defaultMrp = $stock && $stock->mrp > 0 ? $stock->mrp : ($item->mrp ?: 0);
                                    $totalQty = $stock ? (float)$stock->quantity : 0;
                                @endphp

                                @if ($batches->isNotEmpty())
                                    @foreach ($batches as $bIdx => $b)
                                        @php
                                            $rowIdx++;
                                            $bSell = (float)($b['sell_price'] ?? 0) > 0 ? (float)$b['sell_price'] : $defaultSell;
                                            $bMrp  = (float)($b['mrp'] ?? 0) > 0 ? (float)$b['mrp'] : $defaultMrp;
                                            $bQty  = (float)($b['remaining_qty'] ?? 0);
                                            $bCost = (float)($b['cost_price'] ?? 0) > 0 ? (float)$b['cost_price'] : (float)$item->cost_price;
                                            $expStr = !empty($b['exp_date']) ? \Carbon\Carbon::parse($b['exp_date'])->format('d/m/Y') : '—';
                                            $isExpired = !empty($b['exp_date']) && $b['exp_date'] < now()->toDateString();
                                        @endphp
                                        <tr class="{{ $bIdx > 0 ? 'border-top-0' : '' }}">
                                            <td>
                                                @if ($bIdx === 0)
                                                    <span class="text-muted font-weight-bold">{{ $items->firstItem() + $itemIdx }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($bIdx === 0)
                                                    <div class="font-weight-bold text-dark">{{ $item->name }}</div>
                                                    <div class="small text-muted">
                                                        @if ($item->item_code)
                                                            <span class="badge badge-light border text-monospace mr-1">{{ $item->item_code }}</span>
                                                        @endif
                                                        @if ($item->ean_upc_code && $item->ean_upc_code !== $item->item_code)
                                                            <span class="mr-2"><i class="fas fa-barcode mr-1"></i>{{ $item->ean_upc_code }}</span>
                                                        @endif
                                                        @if ($item->brand)
                                                            <span class="badge badge-secondary py-0">{{ $item->brand->name }}</span>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted ml-3 small"><i class="fas fa-level-up-alt fa-rotate-90 mr-1 text-black-50"></i> Same Item</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="hidden" name="prices[{{ $rowIdx }}][item_id]" value="{{ $item->id }}">
                                                <input type="hidden" name="prices[{{ $rowIdx }}][raw_batch_no]" value="{{ $b['raw_batch_no'] ?? ($b['batch_no'] ?? '') }}">
                                                <input type="hidden" name="prices[{{ $rowIdx }}][batch_no]" value="{{ $b['batch_no'] ?? '' }}">
                                                <input type="hidden" name="prices[{{ $rowIdx }}][exp_date]" value="{{ $b['exp_date'] ?? '' }}">
                                                <input type="hidden" name="prices[{{ $rowIdx }}][old_mrp]" value="{{ $bMrp }}">

                                                <span class="badge badge-info px-2 py-1 font-weight-bold">
                                                    {{ $b['batch_no'] ?: ($b['raw_batch_no'] ?: 'Standard') }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="{{ $isExpired ? 'text-danger font-weight-bold' : 'text-muted' }}">
                                                    {{ $expStr }}
                                                </span>
                                            </td>
                                            <td class="text-right text-muted">₹ {{ number_format($bCost, 2) }}</td>
                                            <td class="text-right">
                                                <span class="badge {{ $bQty > 0 ? 'badge-success' : 'badge-secondary' }} px-2 py-1">
                                                    {{ number_format($bQty, 0) }}
                                                </span>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" 
                                                       name="prices[{{ $rowIdx }}][sell_price]" 
                                                       value="{{ $bSell }}" 
                                                       class="form-control form-control-sm font-weight-bold text-primary">
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" min="0" 
                                                       name="prices[{{ $rowIdx }}][mrp]" 
                                                       value="{{ $bMrp }}" 
                                                       class="form-control form-control-sm font-weight-bold">
                                            </td>
                                        </tr>
                                    @endforeach
                                @else
                                    @php
                                        $rowIdx++;
                                    @endphp
                                    <tr>
                                        <td>{{ $items->firstItem() + $itemIdx }}</td>
                                        <td>
                                            <div class="font-weight-bold text-dark">{{ $item->name }}</div>
                                            <div class="small text-muted">
                                                @if ($item->item_code)
                                                    <span class="badge badge-light border text-monospace mr-1">{{ $item->item_code }}</span>
                                                @endif
                                                @if ($item->ean_upc_code && $item->ean_upc_code !== $item->item_code)
                                                    <span class="mr-2"><i class="fas fa-barcode mr-1"></i>{{ $item->ean_upc_code }}</span>
                                                @endif
                                                @if ($item->brand)
                                                    <span class="badge badge-secondary py-0">{{ $item->brand->name }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <input type="hidden" name="prices[{{ $rowIdx }}][item_id]" value="{{ $item->id }}">
                                            <input type="hidden" name="prices[{{ $rowIdx }}][raw_batch_no]" value="">
                                            <input type="hidden" name="prices[{{ $rowIdx }}][batch_no]" value="">
                                            <input type="hidden" name="prices[{{ $rowIdx }}][exp_date]" value="">
                                            <input type="hidden" name="prices[{{ $rowIdx }}][old_mrp]" value="{{ $defaultMrp }}">

                                            <span class="badge badge-light border text-muted px-2 py-1">Default Batch</span>
                                        </td>
                                        <td><span class="text-muted">—</span></td>
                                        <td class="text-right text-muted">₹ {{ number_format($item->cost_price ?: 0, 2) }}</td>
                                        <td class="text-right">
                                            <span class="badge {{ $totalQty > 0 ? 'badge-success' : 'badge-secondary' }} px-2 py-1">
                                                {{ number_format($totalQty, 0) }}
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" 
                                                   name="prices[{{ $rowIdx }}][sell_price]" 
                                                   value="{{ $defaultSell }}" 
                                                   class="form-control form-control-sm font-weight-bold text-primary">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" min="0" 
                                                   name="prices[{{ $rowIdx }}][mrp]" 
                                                   value="{{ $defaultMrp }}" 
                                                   class="form-control form-control-sm font-weight-bold">
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No items found matching the selected filter.</td>
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
