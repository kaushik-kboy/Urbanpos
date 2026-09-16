@extends('adminlte::page')

@section('title', 'Item EAN/UPC Entry')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="font-weight-bold text-dark mb-0">
                <i class="fas fa-barcode text-primary mr-2"></i>Item EAN / UPC Entry (Multiple Barcodes)
            </h1>
            <small class="text-muted">Manage, scan, and map item barcodes for fast POS billing and inventory lookups.</small>
        </div>
        <a href="{{ route('master.items.index') }}" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="fas fa-boxes mr-1"></i> Item Master
        </a>
    </div>
@stop

@section('content')
    {{-- Metric Stat Cards --}}
    <div class="row mb-3">
        <div class="col-md-4 col-sm-6">
            <div class="info-box bg-light shadow-sm border mb-2">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Registered Items</span>
                    <span class="info-box-number h4 mb-0 text-info font-weight-bold">{{ number_format($totalCount) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box bg-light shadow-sm border mb-2">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-barcode"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">With EAN / UPC Barcode</span>
                    <span class="info-box-number h4 mb-0 text-success font-weight-bold">{{ number_format($withEanCount) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="info-box bg-light shadow-sm border mb-2">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-exclamation-triangle text-white"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Missing Barcode (Needs Mapping)</span>
                    <span class="info-box-number h4 mb-0 text-warning font-weight-bold">{{ number_format($missingEanCount) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-header bg-light py-2">
            <h5 class="card-title font-weight-bold text-dark mb-0">
                <i class="fas fa-filter mr-1 text-secondary"></i> Search & Filter
            </h5>
        </div>
        <div class="card-body py-3">
            <form method="GET" action="{{ route('master.aux', 'item-ean-upc-entry') }}" class="form-row align-items-end">
                <div class="col-md-5 col-sm-12 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Search Product Name, Code, Alias, or Barcode</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, item code, barcode..." value="{{ $search }}" autocomplete="off">
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Barcode Status</label>
                    <select name="ean_filter" class="form-control">
                        <option value="all" @selected($eanFilter === 'all')>All Items ({{ number_format($totalCount) }})</option>
                        <option value="with_ean" @selected($eanFilter === 'with_ean')>Has Barcode ({{ number_format($withEanCount) }})</option>
                        <option value="missing_ean" @selected($eanFilter === 'missing_ean')>Missing Barcode ({{ number_format($missingEanCount) }})</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-6 mb-2">
                    <label class="small font-weight-bold text-muted mb-1">Item Status</label>
                    <select name="status" class="form-control">
                        <option value="all" @selected($statusFilter === 'all')>All</option>
                        <option value="active" @selected($statusFilter === 'active')>Active Only</option>
                        <option value="inactive" @selected($statusFilter === 'inactive')>Inactive Only</option>
                    </select>
                </div>

                <div class="col-md-2 col-sm-12 mb-2 d-flex">
                    <button type="submit" class="btn btn-primary flex-fill mr-1 shadow-sm">
                        <i class="fas fa-filter mr-1"></i> Apply
                    </button>
                    <a href="{{ route('master.aux', 'item-ean-upc-entry') }}" class="btn btn-outline-secondary" title="Reset Filters">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Main Data Card --}}
    <div class="card card-primary card-outline shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title font-weight-bold mb-0 text-dark">
                <i class="fas fa-list-ul mr-1 text-primary"></i> Item Barcode Directory
                <span class="badge badge-info ml-2">{{ $items->total() }} items matching filter</span>
            </h5>
            <div>
                <small class="text-muted"><i class="fas fa-info-circle mr-1"></i> Edit the EAN / UPC code in any row and click <i class="fas fa-check text-success"></i> or press <strong>Enter</strong> to instantly save.</small>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-bordered mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th style="width: 120px;" class="text-center">Item Code</th>
                            <th>Product Description</th>
                            <th style="width: 150px;">Category / Dept</th>
                            <th style="width: 110px;" class="text-right">Sell Price</th>
                            <th style="width: 110px;" class="text-right">MRP</th>
                            <th style="min-width: 250px;">EAN / UPC Barcode (Scannable)</th>
                            <th style="width: 90px;" class="text-center">Status</th>
                            <th style="width: 80px;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $idx => $item)
                            <tr id="item-row-{{ $item->id }}" data-item-id="{{ $item->id }}">
                                <td class="text-center align-middle font-weight-bold text-muted">
                                    {{ $items->firstItem() + $idx }}
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge badge-light border text-monospace font-weight-bold px-2 py-1 text-primary">
                                        {{ $item->item_code ?? '—' }}
                                    </span>
                                </td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-dark">{{ $item->name }}</div>
                                    @if ($item->alias)
                                        <small class="text-muted d-block"><i class="fas fa-tag mr-1"></i>Alias: {{ $item->alias }}</small>
                                    @endif
                                </td>
                                <td class="align-middle small">
                                    <div><strong>Dept:</strong> {{ $item->departmentValue?->name ?? '—' }}</div>
                                    <div class="text-muted"><strong>Cat:</strong> {{ $item->categoryValue?->name ?? '—' }}</div>
                                </td>
                                <td class="text-right align-middle font-weight-bold text-success">
                                    ₹{{ number_format($item->sell_price, 2) }}
                                </td>
                                <td class="text-right align-middle text-muted">
                                    ₹{{ number_format($item->mrp, 2) }}
                                </td>
                                <td class="align-middle">
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                        </div>
                                        <input type="text"
                                               class="form-control font-weight-bold text-monospace ean-input"
                                               value="{{ $item->ean_upc_code }}"
                                               placeholder="Scan or enter barcode..."
                                               autocomplete="off"
                                               data-original="{{ $item->ean_upc_code }}">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-success btn-save-ean" title="Save Barcode">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="feedback-msg small mt-1 d-none font-weight-bold"></div>
                                </td>
                                <td class="text-center align-middle">
                                    @if ($item->status)
                                        <span class="badge badge-success px-2 py-1">Active</span>
                                    @else
                                        <span class="badge badge-secondary px-2 py-1">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-center align-middle">
                                    <a href="{{ route('master.items.edit', $item) }}" class="btn btn-xs btn-outline-info" title="Edit in Item Master">
                                        <i class="fas fa-pen"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-barcode fa-3x text-secondary mb-3 d-block opacity-50"></i>
                                    <h5>No items found matching your filters.</h5>
                                    <p class="small mb-0">Try clearing the search query or changing the barcode status filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($items->hasPages())
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                <span class="small text-muted">Showing {{ $items->firstItem() }} to {{ $items->lastItem() }} of {{ number_format($items->total()) }} items</span>
                <div>{{ $items->links() }}</div>
            </div>
        @endif
    </div>
@stop

@push('js')
<script>
    $(document).ready(function () {
        const UPDATE_URL = '{{ route("master.aux.item-ean-upc.update") }}';
        const CSRF_TOKEN = '{{ csrf_token() }}';

        function saveBarcode($row) {
            let itemId = $row.data('item-id');
            let $input = $row.find('.ean-input');
            let $btn = $row.find('.btn-save-ean');
            let $msg = $row.find('.feedback-msg');
            let newBarcode = $.trim($input.val());
            let original = $input.data('original') || '';

            $btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i>');
            $msg.removeClass('text-success text-danger d-none').text('');

            $.ajax({
                url: UPDATE_URL,
                type: 'POST',
                data: {
                    _token: CSRF_TOKEN,
                    item_id: itemId,
                    ean_upc_code: newBarcode
                },
                dataType: 'json',
                success: function (res) {
                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                    $input.data('original', res.ean_upc_code || '');
                    $input.val(res.ean_upc_code || '');
                    $input.addClass('is-valid');
                    setTimeout(() => $input.removeClass('is-valid'), 2500);

                    $msg.addClass('text-success').text('✓ Saved!').removeClass('d-none');
                    setTimeout(() => $msg.fadeOut(500, function() { $(this).addClass('d-none').show(); }), 2500);
                },
                error: function (xhr) {
                    $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                    $input.addClass('is-invalid');
                    setTimeout(() => $input.removeClass('is-invalid'), 3000);

                    let errText = 'Failed to save.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errText = xhr.responseJSON.message;
                    }
                    $msg.addClass('text-danger').text(errText).removeClass('d-none');
                }
            });
        }

        // Save on click
        $(document).on('click', '.btn-save-ean', function () {
            let $row = $(this).closest('tr');
            saveBarcode($row);
        });

        // Save on Enter key in input
        $(document).on('keydown', '.ean-input', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                let $row = $(this).closest('tr');
                saveBarcode($row);
            }
        });
    });
</script>
@endpush
