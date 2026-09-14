@extends('adminlte::page')

@section('title', 'Stock Update Listing')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="font-weight-bold text-dark mb-1">
                <i class="fas fa-boxes-alt text-primary mr-2"></i> Stock Update Listing
            </h1>
            <p class="text-muted mb-0 small">Item-wise stock adjustment register & physical audit inventory count</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-outline-success btn-sm mr-1 shadow-sm">
                <i class="fas fa-file-excel mr-1"></i> Export CSV
            </a>
            <button type="button" class="btn btn-outline-secondary btn-sm mr-1 shadow-sm" onclick="window.print()">
                <i class="fas fa-print mr-1"></i> Print
            </button>
            <a href="{{ route('inventory.stock-updates.create') }}" class="btn btn-primary btn-sm shadow-sm font-weight-bold">
                <i class="fas fa-plus-circle mr-1"></i> Add Stock Update
            </a>
        </div>
    </div>
@stop

@section('content')
    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle mr-2"></i> {{ session('status') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- KPI Summary Stats --}}
    <div class="row mb-3">
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box shadow-sm mb-0 bg-white border">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-list-ol"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Total Line Items</span>
                    <span class="info-box-number font-weight-bold text-dark">{{ number_format($totalCount) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box shadow-sm mb-0 bg-white border">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Total Physical Qty</span>
                    <span class="info-box-number font-weight-bold text-primary">{{ number_format($totalPhysicalQty, 3) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box shadow-sm mb-0 bg-white border">
                <span class="info-box-icon bg-secondary elevation-1"><i class="fas fa-warehouse"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Total System Stock (At Entry)</span>
                    <span class="info-box-number font-weight-bold text-secondary">{{ number_format($totalSystemQty, 3) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-2">
            <div class="info-box shadow-sm mb-0 bg-white border">
                <span class="info-box-icon {{ $totalDeltaQty < 0 ? 'bg-danger' : ($totalDeltaQty > 0 ? 'bg-success' : 'bg-secondary') }} elevation-1">
                    <i class="fas {{ $totalDeltaQty < 0 ? 'fa-arrow-down' : ($totalDeltaQty > 0 ? 'fa-arrow-up' : 'fa-check') }}"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted small">Net Difference (Adj)</span>
                    <span class="info-box-number font-weight-bold {{ $totalDeltaQty < 0 ? 'text-danger' : ($totalDeltaQty > 0 ? 'text-success' : 'text-muted') }}">
                        {{ ($totalDeltaQty > 0 ? '+' : '') . number_format($totalDeltaQty, 3) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="card card-outline card-primary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h3 class="card-title font-weight-bold text-secondary" style="font-size: 0.95rem;">
                <i class="fas fa-filter text-primary mr-1"></i> Filter Options
            </h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body py-3">
            <form action="{{ route('inventory.stock-updates.index') }}" method="GET" id="stock-update-filter-form">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Search Item / Update No:</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="search" class="form-control" placeholder="Code, Name, Barcode, Update No..." value="{{ request('search') }}">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Location / Branch:</label>
                        <select name="branch_id" class="form-control form-control-sm">
                            <option value="">-- All Locations / Branches --</option>
                            @foreach ($branches as $id => $name)
                                <option value="{{ $id }}" {{ (string)request('branch_id') === (string)$id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Stock Difference:</label>
                        <select name="diff_type" class="form-control form-control-sm">
                            <option value="">All Differences</option>
                            <option value="shortage" {{ request('diff_type') === 'shortage' ? 'selected' : '' }}>Shortage Only (-)</option>
                            <option value="excess" {{ request('diff_type') === 'excess' ? 'selected' : '' }}>Excess Only (+)</option>
                            <option value="exact" {{ request('diff_type') === 'exact' ? 'selected' : '' }}>Zero Diff (0)</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Status:</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">All Statuses</option>
                            <option value="Approved" {{ request('status') === 'Approved' ? 'selected' : '' }}>Approved</option>
                            <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">Per Page:</label>
                        <select name="per_page" class="form-control form-control-sm" onchange="$('#stock-update-filter-form').submit();">
                            <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25 Items</option>
                            <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50 Items</option>
                            <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100 Items</option>
                            <option value="200" {{ request('per_page') == 200 ? 'selected' : '' }}>200 Items</option>
                            <option value="500" {{ request('per_page') == 500 ? 'selected' : '' }}>500 Items</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">From Entry Date:</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <label class="small font-weight-bold text-muted mb-1">To Entry Date:</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                    <div class="col-md-6 col-sm-12 d-flex align-items-end mb-2">
                        <button type="submit" class="btn btn-primary btn-sm px-4 mr-2 shadow-sm font-weight-bold">
                            <i class="fas fa-filter mr-1"></i> Apply Filter
                        </button>
                        <a href="{{ route('inventory.stock-updates.index') }}" class="btn btn-outline-secondary btn-sm px-3 shadow-sm mr-auto">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </a>
                        @if(request()->anyFilled(['search', 'branch_id', 'diff_type', 'status', 'date_from', 'date_to']))
                            <span class="badge badge-warning px-2 py-1 align-self-center text-dark">
                                <i class="fas fa-info-circle mr-1"></i> Filter Active
                            </span>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Listing Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-striped table-bordered mb-0">
                    <thead class="thead-dark text-nowrap">
                        <tr class="text-center align-middle" style="font-size: 0.88rem;">
                            <th style="width: 50px;">S.No</th>
                            <th style="width: 95px;" class="text-center">Code</th>
                            <th class="text-left" style="min-width: 250px;">Description</th>
                            <th style="width: 105px;" class="text-center">Exp Dt</th>
                            <th style="width: 100px;" class="text-right">Qty (Counted)</th>
                            <th style="width: 135px;" class="text-right">System Stock (At Entry)</th>
                            <th style="width: 95px;" class="text-right">Diff Qty</th>
                            <th style="width: 125px;" class="text-right bg-info text-white">Live Current Stock</th>
                            <th style="width: 105px;" class="text-right">Sell Price</th>
                            <th style="width: 105px;" class="text-right">MRP</th>
                            <th style="width: 110px;" class="text-center">Update No</th>
                            <th style="width: 100px;" class="text-center">Date</th>
                            <th style="min-width: 150px;" class="text-left">Location</th>
                            <th style="width: 65px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.88rem;">
                        @forelse ($stockUpdateItems as $index => $line)
                            @php
                                $sNo = ($stockUpdateItems->currentPage() - 1) * $stockUpdateItems->perPage() + $index + 1;
                                $item = $line->item;
                                $code = $item?->item_code ?: ($item?->ean_upc_code ?: '-');
                                $voucher = $line->stockUpdate;
                                $diff = (float) $line->delta_qty;
                            @endphp
                            <tr>
                                <td class="text-center align-middle font-weight-bold text-muted">{{ $sNo }}</td>
                                <td class="text-center align-middle">
                                    <span class="badge badge-light border px-2 py-1 text-primary font-weight-bold">
                                        {{ $code }}
                                    </span>
                                </td>
                                <td class="align-middle font-weight-bold text-dark">
                                    {{ $item?->name ?? 'Unknown Item' }}
                                    @if ($item?->alias)
                                        <small class="text-muted d-block font-weight-normal">Alias: {{ $item->alias }}</small>
                                    @endif
                                </td>
                                <td class="text-center align-middle text-nowrap">
                                    @if ($line->exp_date)
                                        <span class="text-dark">{{ $line->exp_date->format('d-m-Y') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-right align-middle font-weight-bold text-primary">
                                    {{ (float)$line->physical_qty == round($line->physical_qty) ? number_format($line->physical_qty, 0) : number_format($line->physical_qty, 3) }}
                                </td>
                                <td class="text-right align-middle text-secondary font-weight-bold">
                                    {{ (float)$line->system_qty_at_entry == round($line->system_qty_at_entry) ? number_format($line->system_qty_at_entry, 0) : number_format($line->system_qty_at_entry, 3) }}
                                </td>
                                <td class="text-right align-middle font-weight-bold">
                                    @if ($diff < 0)
                                        <span class="badge badge-danger px-2 py-1" title="Shortage of {{ abs($diff) }}">
                                             <i class="fas fa-arrow-down mr-1"></i> {{ (float)$diff == round($diff) ? number_format($diff, 0) : number_format($diff, 3) }}
                                        </span>
                                    @elseif ($diff > 0)
                                        <span class="badge badge-success px-2 py-1" title="Excess of {{ $diff }}">
                                            <i class="fas fa-arrow-up mr-1"></i> +{{ (float)$diff == round($diff) ? number_format($diff, 0) : number_format($diff, 3) }}
                                        </span>
                                    @else
                                        <span class="badge badge-secondary px-2 py-1">0</span>
                                    @endif
                                </td>
                                <td class="text-right align-middle font-weight-bold bg-light">
                                    <span class="badge badge-info px-2 py-1" style="font-size: 0.85rem;" title="Live stock currently in branch">
                                        {{ isset($line->live_current_stock) ? ((float)$line->live_current_stock == round($line->live_current_stock) ? number_format($line->live_current_stock, 0) : number_format($line->live_current_stock, 3)) : '0' }}
                                    </span>
                                </td>
                                <td class="text-right align-middle font-weight-bold text-dark">
                                    ₹{{ number_format($line->sell_price, 2) }}
                                </td>
                                <td class="text-right align-middle font-weight-bold text-muted">
                                    ₹{{ number_format($line->mrp, 2) }}
                                </td>
                                <td class="text-center align-middle text-nowrap">
                                    <span class="badge badge-light border text-info px-2 py-1 font-weight-bold">
                                        {{ $voucher?->update_number ?? '-' }}
                                    </span>
                                </td>
                                <td class="text-center align-middle text-nowrap">
                                    {{ $voucher?->entry_date ? $voucher->entry_date->format('d-m-Y') : '-' }}
                                </td>
                                <td class="align-middle text-muted small text-truncate" style="max-width: 180px;" title="{{ $voucher?->branch?->name }}">
                                    <i class="fas fa-store-alt text-secondary mr-1"></i>
                                    {{ $voucher?->branch?->name ?? 'N/A' }}
                                </td>
                                <td class="text-center align-middle text-nowrap">
                                    @if ($voucher)
                                        <a href="{{ route('inventory.stock-updates.edit', $voucher) }}" class="btn btn-xs btn-outline-secondary" title="Edit Entry">
                                             <i class="fas fa-pen"></i>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted py-5">
                                    <i class="fas fa-boxes fa-3x text-secondary mb-2 d-block"></i>
                                    No stock update items found matching your filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($stockUpdateItems->hasPages())
            <div class="card-footer py-2 bg-light d-flex justify-content-between align-items-center flex-wrap">
                <span class="text-muted small">
                    Showing <strong>{{ $stockUpdateItems->firstItem() }}</strong> to <strong>{{ $stockUpdateItems->lastItem() }}</strong> of <strong>{{ number_format($stockUpdateItems->total()) }}</strong> items
                </span>
                <div class="mt-2 mt-md-0">
                    {{ $stockUpdateItems->links('pagination::bootstrap-4') }}
                </div>
            </div>
        @endif
    </div>
@stop

@push('css')
<style>
    .table thead th {
        vertical-align: middle;
        letter-spacing: 0.02em;
    }
    .table tbody tr:hover {
        background-color: #f5f9ff !important;
    }
</style>
@endpush
