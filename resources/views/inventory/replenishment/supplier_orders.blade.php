@extends('adminlte::page')

@section('title', 'Supplier PO Recommendations')

@push('css')
<link rel="stylesheet" href="{{ asset('vendor/select2/css/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
<style>
body, .content-wrapper {
    color: #0f172a !important;
}

.page-heading {
    color: #0f172a !important;
    font-weight: 800 !important;
    letter-spacing: -0.025em;
    font-size: 1.6rem;
}
.page-subheading {
    color: #1e293b !important;
    font-weight: 600;
    font-size: 0.85rem;
}

.modern-filter-toolbar {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    border-radius: 12px;
    padding: 0.85rem 1.25rem;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.05);
    margin-bottom: 1.25rem;
}
.filter-field-label {
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #0f172a;
    margin-bottom: 0.3rem;
    display: block;
}
.modern-control {
    border-radius: 8px !important;
    border: 1.5px solid #cbd5e1 !important;
    font-size: 0.85rem !important;
    font-weight: 600 !important;
    color: #0f172a !important;
}
.modern-control:focus {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15) !important;
}

/* Modern Select2 Styling */
.select2-container--bootstrap4 .select2-selection--single {
    height: 35px !important;
    border-radius: 8px !important;
    border: 1.5px solid #cbd5e1 !important;
    font-size: 0.83rem !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    background-color: #ffffff !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    line-height: 32px !important;
    color: #0f172a !important;
    padding-left: 0.5rem !important;
    font-weight: 700 !important;
}
.select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
    height: 32px !important;
}
.select2-container--bootstrap4.select2-container--focus .select2-selection--single {
    border-color: #0f172a !important;
    box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.15) !important;
}
.select2-dropdown {
    border: 1.5px solid #0f172a !important;
    border-radius: 8px !important;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.15) !important;
}
.select2-results__option {
    font-weight: 600 !important;
    font-size: 0.83rem !important;
    color: #0f172a !important;
}
.select2-results__option--highlighted {
    background-color: #0f172a !important;
    color: #ffffff !important;
}

.modern-vendor-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.04);
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}
.modern-vendor-card:hover {
    transform: translateY(-2px);
    border-color: #0f172a;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.08);
}
.modern-vendor-header {
    background: #ffffff;
    border-bottom: 1.5px solid #e2e8f0;
    padding: 1rem 1.25rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-vendor-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}

.btn-action-dark {
    background: #0f172a;
    border: 1.5px solid #0f172a;
    color: #ffffff !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-action-dark:hover {
    background: #1e293b;
    border-color: #1e293b;
    box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.2);
}
.btn-action-outline {
    background: #ffffff;
    border: 1.5px solid #0f172a;
    color: #0f172a !important;
    font-weight: 700;
    border-radius: 8px;
    padding: 0.45rem 1rem;
    font-size: 0.85rem;
    transition: all 0.15s ease;
}
.btn-action-outline:hover {
    background: #0f172a;
    color: #ffffff !important;
}
</style>
@endpush

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
        <div>
            <h1 class="page-heading m-0">
                <i class="fas fa-truck text-primary mr-2"></i> Supplier-wise Order Recommendations
            </h1>
            <div class="page-subheading mt-1">
                Items needing reorder grouped by vendor with estimated procurement value
            </div>
        </div>
        <div>
            <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-action-outline shadow-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Replenishment Studio
            </a>
        </div>
    </div>
@stop

@section('content')
    {{-- Supplier Search & Filter Toolbar --}}
    @if(count($supplierOrders) > 0)
    <div class="modern-filter-toolbar">
        <div class="row g-2 align-items-center">
            <div class="col-md-4 col-12 mb-2 mb-md-0">
                <label class="filter-field-label"><i class="fas fa-search mr-1 text-primary"></i> Filter by Supplier Name</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="filterSupplierSearch" class="form-control modern-control" placeholder="Type supplier name to filter..." value="{{ request('search', request('supplier_name', '')) }}" autocomplete="off">
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary font-weight-bold d-none" type="button" id="btnClearSupplierSearch" title="Clear filter">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-12 mb-2 mb-md-0">
                <label class="filter-field-label"><i class="fas fa-building mr-1 text-success"></i> Select Supplier Dropdown</label>
                <select id="filterSupplierSelect" class="form-control form-control-sm select2">
                    <option value="all">All Suppliers (Show All)</option>
                    @foreach($supplierOrders as $group)
                        <option value="{{ $group->supplier_id }}" {{ (string)request('supplier_id') === (string)$group->supplier_id ? 'selected' : '' }}>
                            {{ $group->supplier_name }} ({{ $group->items_count }} items)
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4 col-12 text-md-right text-left d-flex align-items-end justify-content-md-end justify-content-between mt-2 mt-md-0">
                <button type="button" class="btn btn-primary btn-sm font-weight-bold mr-1" id="btnApplySupplierFilter">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold mr-2" id="btnResetSupplierFilter">
                    <i class="fas fa-undo mr-1"></i> Reset
                </button>
                <span id="supplierVisibleCountBadge" class="badge badge-dark px-3 py-2 font-weight-bold" style="border-radius: 8px; font-size: 0.85rem;">
                    {{ count($supplierOrders) }} Suppliers
                </span>
            </div>
        </div>
    </div>
    @endif

    <div class="row" id="supplierCardsRow">
        @forelse($supplierOrders as $group)
            <div class="col-md-6 col-lg-4 mb-3 vendor-card-col" 
                 data-supplier-id="{{ $group->supplier_id }}" 
                 data-supplier-name="{{ strtolower($group->supplier_name) }}">
                <div class="modern-vendor-card">
                    <div class="modern-vendor-header">
                        <h3 class="modern-vendor-title">
                            <i class="fas fa-building text-primary mr-1"></i> {{ $group->supplier_name }}
                        </h3>
                        <span class="badge badge-dark px-2 py-1 font-weight-bold" style="border-radius: 6px;">{{ $group->items_count }} Items</span>
                    </div>
                    <div class="p-3 d-flex flex-column justify-content-between flex-grow-1">
                        <div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="font-weight-bold text-dark text-xs uppercase">Total Units to Order:</span>
                                <span class="font-weight-bold text-dark">{{ number_format($group->total_units) }} units</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3">
                                <span class="font-weight-bold text-dark text-xs uppercase">Estimated Order Total:</span>
                                <span class="font-weight-bold text-primary" style="font-size: 1.2rem;">₹ {{ number_format($group->estimated_value, 2) }}</span>
                            </div>

                            <div class="p-2 rounded bg-light border mb-3">
                                <span class="font-weight-bold text-dark text-xs d-block mb-1 text-uppercase">Top Items in this Order:</span>
                                <ul class="list-unstyled mb-0" style="font-size: 0.82rem;">
                                    @foreach($group->items->take(4) as $metric)
                                        <li class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="font-weight-bold text-dark text-truncate mr-2" style="max-width: 190px;">{{ $metric->item->name ?? 'SKU #' . $metric->item_id }}</span>
                                            <span class="badge badge-danger font-weight-bold px-2 py-1" style="border-radius: 4px;">{{ $metric->suggested_qty }} Qty</span>
                                        </li>
                                    @endforeach
                                    @if($group->items_count > 4)
                                        <li class="font-weight-bold text-dark text-center pt-2" style="font-size: 0.75rem;">
                                            + {{ $group->items_count - 4 }} more items
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </div>

                        <a href="{{ route('inventory.replenishment.index', ['supplier_id' => $group->supplier_id]) }}" class="btn btn-action-dark btn-block shadow-sm">
                            <i class="fas fa-file-invoice mr-1 text-primary"></i> Review & Create Order
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="modern-vendor-card text-center py-5">
                    <div class="card-body">
                        <i class="fas fa-clipboard-check text-success mb-3" style="font-size: 3rem;"></i><br>
                        <h4 class="font-weight-bold text-dark">No Purchase Orders Currently Required</h4>
                        <p class="font-weight-bold text-dark text-sm">All store inventory levels are currently above reorder thresholds.</p>
                        <a href="{{ route('inventory.replenishment.index') }}" class="btn btn-action-dark font-weight-bold">
                            Go to Stock Analysis Studio
                        </a>
                    </div>
                </div>
            </div>
        @endforelse

        {{-- No Search Match Container --}}
        <div class="col-12 d-none" id="noSupplierMatchCard">
            <div class="modern-vendor-card text-center py-5">
                <div class="card-body">
                    <i class="fas fa-search text-muted mb-3" style="font-size: 3rem;"></i><br>
                    <h4 class="font-weight-bold text-dark">No Matching Suppliers Found</h4>
                    <p class="font-weight-bold text-muted text-sm">No supplier recommendation matches your search query.</p>
                    <button type="button" class="btn btn-action-outline font-weight-bold" id="btnResetSupplierFilter">
                        <i class="fas fa-redo mr-1"></i> Reset Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Supplier Pagination Footer --}}
    @if(count($supplierOrders) > 0)
    <div class="row mt-3 mb-4" id="supplierPaginationRow">
        <div class="col-12">
            <div class="p-3 bg-white border border-dark rounded d-flex justify-content-between align-items-center flex-wrap shadow-sm">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <span class="font-weight-bold text-dark text-sm mr-3" id="supplierPaginationInfo">Showing 0 of 0</span>
                    <div class="d-flex align-items-center">
                        <label class="small font-weight-bold mr-1 mb-0 text-muted">Per Page:</label>
                        <select id="supplierPerPage" class="form-control form-control-sm" style="width: 75px; height: 31px; border-radius: 6px; font-weight: 700;">
                            <option value="9" selected>9</option>
                            <option value="18">18</option>
                            <option value="36">36</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                </div>
                <ul class="pagination pagination-sm m-0" id="supplierPaginationControls"></ul>
            </div>
        </div>
    </div>
    @endif
@stop

@push('js')
<script src="{{ asset('vendor/select2/js/select2.min.js') }}"></script>
<script>
$(document).ready(function() {
    const totalSuppliersCount = {{ count($supplierOrders) }};
    let supplierCurrentPage = 1;
    let supplierPerPage = 9;
    let matchingSupplierCards = [];

    $('#filterSupplierSelect').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    function applySupplierFilter(resetPage = true) {
        if (resetPage) {
            supplierCurrentPage = 1;
        }
        const query = ($('#filterSupplierSearch').val() || '').toLowerCase().trim();
        const selectedId = $('#filterSupplierSelect').val();

        if (query.length > 0) {
            $('#btnClearSupplierSearch').removeClass('d-none');
        } else {
            $('#btnClearSupplierSearch').addClass('d-none');
        }

        matchingSupplierCards = [];
        $('.vendor-card-col').each(function() {
            const $card = $(this);
            const cardSupName = String($card.data('supplier-name') || '');
            const cardSupId = String($card.data('supplier-id') || '');

            const matchesSearch = (!query || cardSupName.indexOf(query) !== -1);
            const matchesSelect = (selectedId === 'all' || cardSupId === selectedId);

            if (matchesSearch && matchesSelect) {
                matchingSupplierCards.push($card);
            } else {
                $card.addClass('d-none');
            }
        });

        const totalMatching = matchingSupplierCards.length;
        $('#supplierVisibleCountBadge').text(totalMatching + ' of ' + totalSuppliersCount + ' Suppliers');

        if (totalMatching === 0 && totalSuppliersCount > 0) {
            $('#noSupplierMatchCard').removeClass('d-none');
            $('#supplierPaginationRow').addClass('d-none');
        } else {
            $('#noSupplierMatchCard').addClass('d-none');
            $('#supplierPaginationRow').removeClass('d-none');
        }

        renderSupplierPagination();
    }

    function renderSupplierPagination(page = null) {
        if (page !== null) {
            supplierCurrentPage = page;
        }
        const total = matchingSupplierCards.length;
        let perPage = supplierPerPage === 'all' ? (total > 0 ? total : 9) : parseInt(supplierPerPage, 10);
        if (!perPage || perPage <= 0) perPage = 9;

        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if (supplierCurrentPage > totalPages) {
            supplierCurrentPage = totalPages;
        }
        if (supplierCurrentPage < 1) {
            supplierCurrentPage = 1;
        }

        const startIndex = (supplierCurrentPage - 1) * perPage;
        const endIndex = supplierPerPage === 'all' ? total : Math.min(startIndex + perPage, total);

        matchingSupplierCards.forEach(function($card, idx) {
            if (idx >= startIndex && idx < endIndex) {
                $card.removeClass('d-none');
            } else {
                $card.addClass('d-none');
            }
        });

        if (total === 0) {
            $('#supplierPaginationInfo').text('Showing 0 of 0');
        } else {
            $('#supplierPaginationInfo').text(`Showing page ${supplierCurrentPage} of ${totalPages} (${total} total suppliers)`);
        }

        let html = '';
        if (totalPages > 1) {
            html += `<li class="page-item ${supplierCurrentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToSupplierPage(${supplierCurrentPage - 1})">&laquo;</a>
                     </li>`;

            let start = Math.max(1, supplierCurrentPage - 2);
            let end = Math.min(totalPages, supplierCurrentPage + 2);

            if (start > 1) {
                html += `<li class="page-item"><a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToSupplierPage(1)">1</a></li>`;
                if (start > 2) {
                    html += `<li class="page-item disabled"><span class="page-link font-weight-bold text-dark">...</span></li>`;
                }
            }

            for (let i = start; i <= end; i++) {
                html += `<li class="page-item ${i === supplierCurrentPage ? 'active' : ''}">
                            <a class="page-link font-weight-bold ${i === supplierCurrentPage ? 'bg-dark text-white border-dark' : 'text-dark'}" href="javascript:void(0)" onclick="goToSupplierPage(${i})">${i}</a>
                         </li>`;
            }

            if (end < totalPages) {
                if (end < totalPages - 1) {
                    html += `<li class="page-item disabled"><span class="page-link font-weight-bold text-dark">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToSupplierPage(${totalPages})">${totalPages}</a></li>`;
            }

            html += `<li class="page-item ${supplierCurrentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link font-weight-bold text-dark" href="javascript:void(0)" onclick="goToSupplierPage(${supplierCurrentPage + 1})">&raquo;</a>
                     </li>`;
        }

        $('#supplierPaginationControls').html(html);
    }

    window.goToSupplierPage = function(page) {
        renderSupplierPagination(page);
        const gridOffset = $('#supplierCardsRow').offset();
        if (gridOffset && $(window).scrollTop() > gridOffset.top - 100) {
            $('html, body').animate({ scrollTop: gridOffset.top - 100 }, 200);
        }
    };

    $('#supplierPerPage').on('change', function() {
        supplierPerPage = $(this).val();
        supplierCurrentPage = 1;
        renderSupplierPagination(1);
    });

    $('#btnApplySupplierFilter').on('click', function() { applySupplierFilter(true); });

    $('#filterSupplierSearch').on('input keyup', function() { applySupplierFilter(true); });
    $('#filterSupplierSearch').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            applySupplierFilter(true);
        }
    });

    $('#filterSupplierSelect').on('change.select2 change', function() {
        applySupplierFilter(true);
    });

    $('#btnClearSupplierSearch').on('click', function() {
        $('#filterSupplierSearch').val('').focus();
        applySupplierFilter(true);
    });

    $('#btnResetSupplierFilter').on('click', function() {
        $('#filterSupplierSearch').val('');
        $('#filterSupplierSelect').val('all').trigger('change.select2');
        applySupplierFilter(true);
    });

    // Run initial filter if values were preloaded from query parameters
    applySupplierFilter(true);
});
</script>
@endpush
