@extends('adminlte::page')

@section('title', 'Damage Stock Entry')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="m-0 text-dark"><i class="fas fa-boxes-alt mr-2 text-danger"></i> Damage Stock Entry</h1>
            <small class="text-muted">Stock adjustments written off at cost by location</small>
        </div>
        <div>
            <a href="{{ route('inventory.damage-stocks.create') }}" class="btn btn-danger shadow-sm">
                <i class="fas fa-plus mr-1"></i> New Damage Stock
            </a>
        </div>
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

    {{-- Summary Statistics Cards --}}
    <div class="row mb-3">
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-light shadow-sm border">
                <span class="info-box-icon bg-info elevation-1"><i class="fas fa-file-invoice"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Entries</span>
                    <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.4rem;">
                        {{ number_format($totalEntries) }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-light shadow-sm border">
                <span class="info-box-icon bg-warning elevation-1"><i class="fas fa-cubes"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Wastage Qty</span>
                    <span class="info-box-number text-dark font-weight-bold" style="font-size: 1.4rem;">
                        {{ number_format($totalQty, 3) }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6 col-12">
            <div class="info-box bg-light shadow-sm border">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-rupee-sign"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Cost Value</span>
                    <span class="info-box-number text-danger font-weight-bold" style="font-size: 1.4rem;">
                        ₹{{ number_format($totalCost, 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Filter Card --}}
    <div class="card card-outline card-secondary shadow-sm mb-3">
        <div class="card-body py-2">
            <form action="{{ route('inventory.damage-stocks.index') }}" method="GET" class="form-inline d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex flex-wrap align-items-center mb-1">
                    {{-- Location / Branch Filter --}}
                    <div class="input-group mr-2 mb-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light"><i class="fas fa-map-marker-alt text-danger mr-1"></i> Location</span>
                        </div>
                        <select name="branch_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- All Locations --</option>
                            @foreach ($branches as $bId => $bName)
                                <option value="{{ $bId }}" @selected(request('branch_id') == $bId)>{{ $bName }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Wastage Type Filter --}}
                    <div class="input-group mr-2 mb-1">
                        <div class="input-group-prepend">
                            <span class="input-group-text bg-light"><i class="fas fa-tag text-info mr-1"></i> Type</span>
                        </div>
                        <select name="wastage_type" class="form-control" onchange="this.form.submit()">
                            <option value="">-- All Types --</option>
                            <option value="Wastage" @selected(request('wastage_type') === 'Wastage')>Wastage</option>
                            <option value="Damage" @selected(request('wastage_type') === 'Damage')>Damage</option>
                            <option value="Theft" @selected(request('wastage_type') === 'Theft')>Theft</option>
                        </select>
                    </div>

                    {{-- Search Input --}}
                    <div class="input-group mr-2 mb-1">
                        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Damage No or Remarks...">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-secondary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mb-1">
                    @if (request()->hasAny(['branch_id', 'wastage_type', 'q']))
                        <a href="{{ route('inventory.damage-stocks.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-undo mr-1"></i> Reset Filters
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Main Entries Table Card --}}
    <div class="card card-outline card-danger shadow-sm">
        <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
            <h6 class="m-0 font-weight-bold text-dark">
                <i class="fas fa-list mr-1 text-danger"></i> Damage Stock Entries
                @if (request('branch_id') && isset($branches[request('branch_id')]))
                    <span class="badge badge-danger ml-1">{{ $branches[request('branch_id')] }}</span>
                @endif
                @if (request('wastage_type'))
                    <span class="badge badge-info ml-1">{{ request('wastage_type') }}</span>
                @endif
            </h6>
            <div class="card-tools">
                <span class="text-muted small">Showing {{ $damageStocks->firstItem() ?? 0 }} - {{ $damageStocks->lastItem() ?? 0 }} of {{ $damageStocks->total() }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0" id="damage-table">
                    <thead class="thead-light">
                        <tr class="text-nowrap">
                            <th style="width: 55px;" class="text-center">S.No</th>
                            <th style="width: 130px;">Damage No</th>
                            <th style="width: 110px;">Date</th>
                            <th>Location / Branch</th>
                            <th style="width: 110px;" class="text-right">Total Qty</th>
                            <th style="width: 140px;" class="text-right">Total Cost [₹]</th>
                            <th style="width: 120px;" class="text-center">Wastage Type</th>
                            <th>Remarks</th>
                            <th style="width: 140px;" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($damageStocks as $index => $entry)
                            @php
                                $sNo = ($damageStocks->currentPage() - 1) * $damageStocks->perPage() + $index + 1;
                                $typeBadge = match($entry->wastage_type) {
                                    'Wastage' => 'badge-warning text-dark',
                                    'Damage' => 'badge-danger',
                                    'Theft' => 'badge-dark',
                                    default => 'badge-secondary',
                                };
                                $detailUrl = route('inventory.damage-stocks.show', $entry);
                            @endphp
                            <tr class="damage-row-clickable" data-url="{{ $detailUrl }}" title="Click to view details">
                                <td class="text-center align-middle font-weight-bold text-muted">{{ $sNo }}</td>
                                <td class="align-middle font-weight-bold text-dark">
                                    <span class="badge badge-light border px-2 py-1 text-primary">
                                        <i class="fas fa-eye mr-1 text-info"></i> {{ $entry->damage_number }}
                                    </span>
                                </td>
                                <td class="align-middle text-nowrap">{{ $entry->entry_date ? $entry->entry_date->format('d-m-Y') : '-' }}</td>
                                <td class="align-middle font-weight-bold">
                                    <i class="fas fa-store-alt text-secondary mr-1"></i>
                                    {{ $entry->branch?->name ?? 'N/A' }}
                                </td>
                                <td class="text-right align-middle font-weight-bold text-primary">
                                    {{ number_format($entry->total_qty, 3) }}
                                </td>
                                <td class="text-right align-middle font-weight-bold text-danger">
                                    ₹{{ number_format($entry->total_cost, 2) }}
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge {{ $typeBadge }} px-2 py-1">{{ $entry->wastage_type }}</span>
                                </td>
                                <td class="align-middle text-muted small text-truncate" style="max-width: 250px;" title="{{ $entry->remarks }}">
                                    {{ $entry->remarks ?: '-' }}
                                </td>
                                <td class="text-center align-middle text-nowrap action-buttons" onclick="event.stopPropagation();">
                                    <button type="button" class="btn btn-xs btn-outline-info mr-1 btn-open-detail" data-url="{{ $detailUrl }}" title="View Details Modal">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                    <a href="{{ route('inventory.damage-stocks.edit', $entry) }}" class="btn btn-xs btn-outline-secondary mr-1" title="Edit Entry">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form action="{{ route('inventory.damage-stocks.destroy', $entry) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete damage stock {{ $entry->damage_number }}? Stock will be restored.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-outline-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="fas fa-boxes-alt fa-3x text-secondary mb-2 d-block"></i>
                                    No damage stock entries found. Click <strong>"New Damage Stock"</strong> to record written-off inventory.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($damageStocks->hasPages())
            <div class="card-footer py-2 bg-light d-flex justify-content-between align-items-center">
                <span class="text-muted small">Showing page {{ $damageStocks->currentPage() }} of {{ $damageStocks->lastPage() }}</span>
                <div>{{ $damageStocks->links('pagination::bootstrap-4') }}</div>
            </div>
        @endif
    </div>

    {{-- Interactive Damage Stock Details Modal --}}
    <div class="modal fade" id="damage-detail-modal" tabindex="-1" role="dialog" aria-labelledby="damageDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title font-weight-bold" id="damageDetailModalLabel">
                        <i class="fas fa-file-invoice mr-2"></i> Damage Stock Details: <span id="modal-dmg-number" class="badge badge-light text-danger ml-1">-</span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-3">
                    <div id="modal-loading" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-danger mb-2"></i>
                        <p class="text-muted">Loading damage stock details...</p>
                    </div>

                    <div id="modal-content-body" style="display: none;">
                        {{-- Top Header Summary --}}
                        <div class="card card-body bg-light py-2 px-3 mb-3 border">
                            <div class="row align-items-center">
                                <div class="col-md-3 col-sm-6 mb-1">
                                    <small class="text-muted d-block">Location / Branch:</small>
                                    <strong id="modal-dmg-branch" class="text-dark"><i class="fas fa-store-alt text-secondary mr-1"></i> -</strong>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-1">
                                    <small class="text-muted d-block">Entry Date:</small>
                                    <strong id="modal-dmg-date" class="text-dark"><i class="fas fa-calendar-alt text-secondary mr-1"></i> -</strong>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-1">
                                    <small class="text-muted d-block">Wastage Classification:</small>
                                    <span id="modal-dmg-type" class="badge badge-danger px-2 py-1 font-weight-bold">-</span>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-1 text-md-right">
                                    <small class="text-muted d-block">Total Written-off Cost:</small>
                                    <strong id="modal-dmg-cost" class="text-danger font-weight-bold" style="font-size: 1.35rem;">₹0.00</strong>
                                </div>
                            </div>
                            <div class="row mt-2 pt-2 border-top text-muted small" id="modal-dmg-notes-row">
                                <div class="col-md-6">
                                    <strong>Remarks:</strong> <span id="modal-dmg-remarks">-</span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Message / Notes:</strong> <span id="modal-dmg-message">-</span>
                                </div>
                            </div>
                        </div>

                        {{-- Line Items Table --}}
                        <h6 class="font-weight-bold text-dark mb-2">
                            <i class="fas fa-boxes text-danger mr-1"></i> Written-off Items (<span id="modal-dmg-items-count">0</span>)
                        </h6>
                        <div class="table-responsive border rounded" style="max-height: 380px; overflow-y: auto;">
                            <table class="table table-sm table-bordered table-striped mb-0">
                                <thead class="thead-light text-nowrap">
                                    <tr class="text-center">
                                        <th style="width: 45px;">S.No</th>
                                        <th style="width: 140px;">Item Code</th>
                                        <th class="text-left">Item Description</th>
                                        <th style="width: 110px;">Exp Dt</th>
                                        <th style="width: 90px;" class="text-right">Qty</th>
                                        <th style="width: 110px;" class="text-right">Cost Price</th>
                                        <th style="width: 110px;" class="text-right">Sell Price</th>
                                        <th style="width: 110px;" class="text-right">MRP</th>
                                        <th style="width: 70px;" class="text-center">GST%</th>
                                        <th style="width: 110px;" class="text-right">GST TaxAmt</th>
                                        <th style="width: 125px;" class="text-right">Net Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-items-tbody">
                                </tbody>
                                <tfoot class="bg-light font-weight-bold">
                                    <tr>
                                        <td colspan="4" class="text-right align-middle">Totals:</td>
                                        <td class="text-right align-middle text-primary" id="modal-footer-qty">0.000</td>
                                        <td colspan="4"></td>
                                        <td class="text-right align-middle text-secondary" id="modal-footer-tax">₹0.00</td>
                                        <td class="text-right align-middle text-danger font-weight-bold" id="modal-footer-net" style="font-size: 1.1rem;">₹0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2 bg-light d-flex justify-content-between">
                    <div>
                        <a href="#" id="modal-edit-link" class="btn btn-warning btn-sm mr-1">
                            <i class="fas fa-pen mr-1"></i> Edit Entry
                        </a>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="fas fa-print mr-1"></i> Print Voucher
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
<style>
    .damage-row-clickable {
        cursor: pointer;
        transition: background-color 0.15s ease-in-out;
    }
    .damage-row-clickable:hover {
        background-color: #fff3f3 !important;
    }
</style>
@endpush

@push('js')
<script>
    $(function () {
        function openDamageModal(url) {
            if (!url) return;

            const $modal = $('#damage-detail-modal');
            $('#modal-loading').show();
            $('#modal-content-body').hide();
            $modal.modal('show');

            $.ajax({
                url: url,
                type: 'GET',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (data) {
                    $('#modal-dmg-number').text(data.damage_number);
                    $('#modal-dmg-branch').html('<i class="fas fa-store-alt text-secondary mr-1"></i> ' + escapeHtml(data.branch_name));
                    $('#modal-dmg-date').html('<i class="fas fa-calendar-alt text-secondary mr-1"></i> ' + escapeHtml(data.entry_date));

                    const badgeClass = data.wastage_type === 'Wastage' ? 'badge-warning text-dark' : (data.wastage_type === 'Damage' ? 'badge-danger' : 'badge-dark');
                    $('#modal-dmg-type').attr('class', 'badge ' + badgeClass + ' px-2 py-1 font-weight-bold').text(data.wastage_type);

                    $('#modal-dmg-cost').text('₹' + data.total_cost);
                    $('#modal-dmg-remarks').text(data.remarks || '-');
                    $('#modal-dmg-message').text(data.message || '-');
                    $('#modal-edit-link').attr('href', data.edit_url);

                    const items = data.items || [];
                    $('#modal-dmg-items-count').text(items.length);

                    let rowsHtml = '';
                    let sumTax = 0;
                    items.forEach(function (item) {
                        rowsHtml += `
                            <tr>
                                <td class="text-center align-middle font-weight-bold text-muted">${item.sno}</td>
                                <td class="text-center align-middle font-weight-bold">
                                    <span class="badge badge-light border px-2 py-1">${escapeHtml(item.code)}</span>
                                </td>
                                <td class="align-middle font-weight-bold text-dark">${escapeHtml(item.name)}</td>
                                <td class="text-center align-middle text-muted">${escapeHtml(item.exp_date)}</td>
                                <td class="text-right align-middle font-weight-bold text-primary">${item.qty}</td>
                                <td class="text-right align-middle">₹${item.cost_price}</td>
                                <td class="text-right align-middle text-muted">₹${item.sell_price}</td>
                                <td class="text-right align-middle text-dark">₹${item.mrp}</td>
                                <td class="text-center align-middle"><span class="badge badge-info">${item.gst_percent}%</span></td>
                                <td class="text-right align-middle text-secondary">₹${item.gst_tax_amount}</td>
                                <td class="text-right align-middle font-weight-bold text-danger">₹${item.net_amount}</td>
                            </tr>
                        `;
                    });

                    $('#modal-items-tbody').html(rowsHtml);
                    $('#modal-footer-qty').text(data.total_qty);
                    $('#modal-footer-net').text('₹' + data.total_cost);

                    $('#modal-loading').hide();
                    $('#modal-content-body').fadeIn(150);
                },
                error: function () {
                    $('#modal-loading').html(`
                        <div class="text-danger py-4">
                            <i class="fas fa-exclamation-triangle fa-3x mb-2 d-block"></i>
                            Failed to load details. Please try again or open the show page directly.
                        </div>
                    `);
                }
            });
        }

        function escapeHtml(str) {
            return (str || '').toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Row click opens modal
        $('#damage-table').on('click', 'tr.damage-row-clickable', function (e) {
            if ($(e.target).closest('.action-buttons, button, a, form').length) {
                return;
            }
            const url = $(this).data('url');
            openDamageModal(url);
        });

        // "View" button click opens modal
        $('#damage-table').on('click', '.btn-open-detail', function (e) {
            e.stopPropagation();
            const url = $(this).data('url');
            openDamageModal(url);
        });
    });
</script>
@endpush
