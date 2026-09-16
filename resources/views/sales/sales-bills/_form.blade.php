@php
    $bill = $salesBill ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($bill?->items ?? ($convertedItems ?? collect()));
    $selectedCust = $bill->customer_id ?? ($sourceQuotation->customer_id ?? ($sourceOrder->customer_id ?? ($sourceDeliveryNote->customer_id ?? '')));
    $selectedBranch = $bill->branch_id ?? ($sourceQuotation->branch_id ?? ($sourceOrder->branch_id ?? ($sourceDeliveryNote->branch_id ?? '')));
    $selectedSalesType = $bill->sales_type ?? ($sourceQuotation->sales_type ?? ($sourceOrder->sales_type ?? 'Local'));
@endphp

@if(isset($sourceQuotation))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Converting from <strong>Sales Quotation #{{ $sourceQuotation->quotation_number }}</strong> (Customer: {{ $sourceQuotation->customer?->name }}).
        <input type="hidden" name="from_quotation_id" value="{{ $sourceQuotation->id }}">
    </div>
@elseif(isset($sourceOrder))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-info-circle mr-1"></i> Converting from <strong>Sales Order #{{ $sourceOrder->order_number }}</strong> (Customer: {{ $sourceOrder->customer?->name }}).
        <input type="hidden" name="from_order_id" value="{{ $sourceOrder->id }}">
    </div>
@elseif(isset($sourceDeliveryNote))
    <div class="alert alert-info py-2 mb-3 shadow-sm border-0">
        <i class="fas fa-truck mr-1"></i> Converting from <strong>Delivery Note #{{ $sourceDeliveryNote->delivery_number }}</strong> (Customer: {{ $sourceDeliveryNote->customer?->name }}). <em>Stock was already deducted upon dispatch.</em>
        <input type="hidden" name="sales_delivery_note_id" value="{{ $sourceDeliveryNote->id }}">
    </div>
@endif

<h5 class="mb-3"><i class="fas fa-file-invoice mr-1 text-primary"></i> Bill Header</h5>
<x-select name="customer_id" label="Customer" :options="$customers" :selected="$selectedCust" placeholder="Select a customer" required />
<div id="sb-customer-loyalty-badge" class="alert alert-light border py-1 px-3 d-none mb-3 shadow-sm align-items-center justify-content-between">
    <div>
        <i class="fas fa-coins text-warning mr-1"></i>
        <strong>Loyalty Points:</strong> <span id="sb-loyalty-pts" class="text-primary font-weight-bold">0.00</span> pts
        <span class="text-muted">(≈ ₹<span id="sb-loyalty-val">0.00</span>)</span>
    </div>
    <span id="sb-loyalty-notice" class="badge badge-success"></span>
</div>
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$selectedBranch" placeholder="Select a branch" required />
<x-field name="bill_date" label="Bill Date" type="date" :value="optional($bill->bill_date ?? now())->format('Y-m-d')" required />
<x-select name="invoice_type" label="Invoice Type" :options="['Retail Invoice' => 'Retail Invoice', 'Tax Invoice' => 'Tax Invoice', 'Exempted' => 'Exempted']" :selected="$bill->invoice_type ?? 'Retail Invoice'" required />
<x-select name="delivery_type" label="Delivery Type" :options="['Delivered' => 'Delivered', 'Home Delivery' => 'Home Delivery', 'Pickup' => 'Pickup']" :selected="$bill->delivery_type ?? 'Delivered'" required />
<x-field name="delivery_time" label="Delivery Time" type="time" :value="$bill->delivery_time ?? ''" />
<x-select name="sales_type" label="Sales Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$selectedSalesType" required />
<x-field name="payment_type" label="Payment Type" :value="$bill->payment_type ?? 'None'" />

<hr>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="fas fa-boxes mr-1 text-primary"></i> Items</h5>
    <span class="badge badge-info px-3 py-2" id="sb-branch-badge"><i class="fas fa-store mr-1"></i> Active Branch: Loading…</span>
</div>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="sb-items-table">
        <thead class="bg-light">
            <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th style="width: 120px;">Code / Barcode</th>
                <th style="min-width: 230px;">Item Description</th>
                <th style="width: 85px;" class="text-center" title="Available Stock in Selected Branch">Stock</th>
                <th style="width: 140px;">Exp Date</th>
                <th style="width: 85px;" class="text-right">Qty</th>
                <th style="width: 100px;" class="text-right">Sell Price</th>
                <th style="width: 100px;" class="text-right">MRP</th>
                <th style="width: 80px;" class="text-right">Disc %</th>
                <th style="width: 95px;" class="text-right">Disc Amt</th>
                <th style="width: 75px;" class="text-right">GST %</th>
                <th style="width: 105px;" class="text-right">Net Amount</th>
                <th style="width: 35px;" class="text-center"></th>
            </tr>
        </thead>
        <tbody id="sb-items-body">
            @forelse ($existingItems as $index => $line)
                @include('sales.sales-bills._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('sales.sales-bills._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="5" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary font-weight-bold" id="footer-sb-qty"></td>
                <td colspan="2" class="align-middle"></td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="footer-sb-disc"></td>
                <td class="align-middle"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="footer-sb-net"></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<button type="button" id="sb-add-row" class="btn btn-link btn-sm font-weight-bold"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<h5 class="mb-3"><i class="fas fa-calculator mr-1 text-primary"></i> Bill Totals</h5>

<div class="alert alert-light border py-2 d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted mr-2 font-weight-bold">Final Bill Total:</span>
        <strong class="text-success h4 mb-0">₹<span id="display-sb-final-total">0.00</span></strong>
    </div>
    <div id="sb-total-items-badge"><span class="badge badge-secondary px-3 py-2">0 Items</span></div>
</div>

<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$bill->round_off ?? 0" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$bill->total_extra_cess ?? 0" />
<x-field name="gst_calamity_cess" label="GST Calamity Cess" type="number" step="0.01" :value="$bill->gst_calamity_cess ?? 0" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$bill->total_weight ?? 0" />
<x-textarea name="remarks" label="Remarks" :value="$bill->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$bill->message ?? ''" />

<template id="sb-row-template">
    @include('sales.sales-bills._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

<!-- ============================================================
     ITEM SEARCH MODAL — opens on Code/Barcode field focus
     ============================================================ -->
<div class="modal fade" id="sb-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="sbItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="sbItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="isl-filter-expiry" class="form-control" placeholder="Filter expiry (YYYY-MM)…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results states -->
                <div id="isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 120px;">Code</th>
                                <th class="text-center" style="width: 130px;">Expiry (Purchase Se)</th>
                                <th class="text-right" style="width: 90px;">Qty (Stock)</th>
                                <th class="text-right" style="width: 95px;">Sell Price</th>
                                <th class="text-right" style="width: 95px;">MRP</th>
                                <th class="text-center" style="width: 80px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Multiple Batches Selection Modal -->
<div class="modal fade" id="sb-batch-modal" tabindex="-1" role="dialog" aria-labelledby="sbBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="sbBatchModalLabel">
                    <i class="fas fa-layer-group mr-1"></i> Multiple Batches Available — Select Batch
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info py-2 mb-3 small font-weight-bold">
                    Item: <span id="modal-item-title" class="text-dark font-weight-bold"></span> | 
                    Code: <span id="modal-item-code" class="text-dark font-weight-bold"></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover mb-0" id="modal-batches-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 90px;">Code</th>
                                <th class="text-center" style="width: 125px;">Expiry (Purchase Se)</th>
                                <th class="text-right" style="width: 85px;">Qty</th>
                                <th class="text-right" style="width: 95px;">Sell Price</th>
                                <th class="text-right" style="width: 95px;">MRP</th>
                                <th class="text-center" style="width: 85px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="modal-batches-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================
     TENDER / PAYMENT MODAL — shown on Save button click
     ============================================================ --}}
<div class="modal fade" id="sb-tender-modal" tabindex="-1" role="dialog" aria-labelledby="sbTenderModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success text-white py-2">
                <h5 class="modal-title" id="sbTenderModalLabel">
                    <i class="fas fa-cash-register mr-2"></i>Payment / Tender
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                {{-- Bill total display --}}
                <div class="alert alert-light border py-2 mb-3 d-flex justify-content-between align-items-center">
                    <span class="text-muted font-weight-bold">Bill Total:</span>
                    <strong class="text-success h5 mb-0">₹<span id="tender-bill-total">0.00</span></strong>
                </div>

                {{-- Payment Rows --}}
                <table class="table table-sm table-bordered mb-2" id="tender-rows-table">
                    <thead class="bg-dark text-white">
                        <tr>
                            <th>Payment Mode</th>
                            <th class="text-right" style="width: 130px;">Amount (₹)</th>
                            <th style="width: 30px;"></th>
                        </tr>
                    </thead>
                    <tbody id="tender-rows-body">
                        {{-- Rows added dynamically --}}
                    </tbody>
                </table>

                <button type="button" id="tender-add-row" class="btn btn-link btn-sm p-0">
                    <i class="fas fa-plus-circle mr-1"></i>Add Another Mode
                </button>

                <hr class="my-2">

                {{-- Summary --}}
                <div class="row small font-weight-bold">
                    <div class="col-6 text-right text-muted">Tender Amount:</div>
                    <div class="col-6 text-right text-primary" id="tender-tendered">₹0.00</div>
                    <div class="col-6 text-right text-muted">Balance:</div>
                    <div class="col-6 text-right" id="tender-balance">₹0.00</div>
                    <div class="col-6 text-right text-muted">Outstanding (after bill):</div>
                    <div class="col-6 text-right text-danger" id="tender-outstanding">₹0.00</div>
                </div>

                <div id="tender-error" class="alert alert-danger py-1 mt-2 d-none small"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-arrow-left mr-1"></i>Cancel
                </button>
                <button type="button" id="tender-confirm-btn" class="btn btn-success btn-sm">
                    <i class="fas fa-check mr-1"></i>Confirm & Save Bill
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Hidden: JSON-encoded TenderTypes for JS --}}
<script id="tender-types-data" type="application/json">
    {!! json_encode($tenderTypes->map(function($t) { return ['id' => $t->id, 'name' => $t->name, 'type' => $t->type, 'mandate_refno' => (bool)$t->mandate_refno]; })->values()) !!}
</script>


@push('js')
<script>
    $(document).ready(function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeModalRow = null;
        let activeSearchRow = null;   // which row triggered the item search modal
        let islDebounce = null;
        const ISL_URL = '{{ route("sales.sales-bills.item-list") }}';

        /* ================================================================
           ITEM SEARCH MODAL — open on Code/Barcode focus
           ================================================================ */



        // Debounced filter inputs — 400ms to avoid firing on every keystroke
        $('#isl-filter-name, #isl-filter-code, #isl-filter-expiry').on('input', function () {
            clearTimeout(islDebounce);
            islDebounce = setTimeout(fetchItemList, 400);
        });

        $('#isl-btn-clear').on('click', function () {
            $('#isl-filter-name, #isl-filter-code, #isl-filter-expiry').val('');
            fetchItemList();
        });

        // Client-side response cache to avoid redundant API calls
        let islCache = {};
        let islLastKey = null;

        function showHintState(msg) {
            $('#isl-loading').addClass('d-none');
            $('#isl-table-wrap').addClass('d-none');
            $('#isl-items-body').empty();
            let $nr = $('#isl-no-results');
            $nr.removeClass('d-none').html(
                '<i class="fas fa-keyboard fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + msg + '</p>'
            );
            $('#isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let srch   = $('#isl-filter-name').val().trim();
            let code   = $('#isl-filter-code').val().trim();
            let expiry = $('#isl-filter-expiry').val().trim();

            // No filter — show hint, skip AJAX
            if (! srch && ! code && ! expiry) {
                showHintState('Start typing to search items\u2026');
                return;
            }

            let cacheKey = branchId + '|' + srch + '|' + code + '|' + expiry;

            // Return cached result if available (same query, same branch)
            if (islCache[cacheKey]) {
                if (islLastKey !== cacheKey) {
                    islLastKey = cacheKey;
                    renderItems(islCache[cacheKey]);
                }
                return;
            }

            islLastKey = cacheKey;
            let params = { branch_id: branchId, search: srch, code: code, expiry: expiry };

            $('#isl-loading').removeClass('d-none');
            $('#isl-no-results').addClass('d-none');
            $('#isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#isl-loading').addClass('d-none');
                // Cache for 60s
                islCache[cacheKey] = res.items || [];
                setTimeout(function() { delete islCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        function renderItems(items) {
            let $tbody = $('#isl-items-body');
            $tbody.empty();

            if (items.length === 0) {
                $('#isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#isl-count-label').text('');
                return;
            }

            // Build rows in one string for faster DOM insertion
            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = it.exp_date
                    ? `<span class="badge badge-danger px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`
                    : `<span class="text-muted">—</span>`;
                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;
                let isOutOfStock = parseFloat(it.qty) <= 0;
                let qtyClass = isOutOfStock ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                let rowClass = isOutOfStock ? 'isl-item-row isl-item-disabled text-muted bg-light' : 'isl-item-row';
                let rowStyle = isOutOfStock ? 'cursor: not-allowed; opacity: 0.6;' : 'cursor: pointer;';
                let actionBtn = isOutOfStock
                    ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock - Cannot select">
                        <i class="fas fa-ban mr-1"></i>Out of Stock
                       </button>`
                    : `<button type="button" class="btn btn-success btn-xs px-2 isl-btn-select"
                        data-id="${it.id}" data-code="${it.code}">
                        <i class="fas fa-check mr-1"></i>Select
                       </button>`;

                html += `
                    <tr class="${rowClass}" style="${rowStyle}"
                        data-id="${it.id}"
                        data-code="${it.code}"
                        data-sell="${it.sell_price}"
                        data-mrp="${it.mrp}"
                        data-gst="${it.gst_percent}"
                        data-qty="${it.qty}"
                        data-exp="${it.exp_date || ''}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx+1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name} ${isOutOfStock ? '<span class="badge badge-secondary ml-1 small">Out of Stock</span>' : ''}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-right ${qtyClass}">${parseFloat(it.qty).toFixed(2)}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${it.sell_price > 0 ? '\u20b9' + parseFloat(it.sell_price).toFixed(2) : '\u2014'}</td>
                        <td class="align-middle text-right text-muted">${it.mrp > 0 ? '\u20b9' + parseFloat(it.mrp).toFixed(2) : '\u2014'}</td>
                        <td class="align-middle text-center">
                            ${actionBtn}
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            $('#isl-table-wrap').removeClass('d-none');
            $('#isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');
        }

        // Clicking a row or its Select button picks the item
        $(document).on('click', '.isl-item-row, .isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('isl-item-row') ? $(this) : $(this).closest('tr');
            if ($row.hasClass('isl-item-disabled') || parseFloat($row.data('qty')) <= 0) {
                return false;
            }
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');

            $('#sb-item-search-modal').modal('hide');

            if (! activeSearchRow || ! itemId) return;

            // Fill code field and trigger the existing lookup (which handles expiry / batch)
            activeSearchRow.find('.sb-item-code').val(itemCode || itemId);
            processItemLookup(null, activeSearchRow, itemId);
            activeSearchRow = null;
        });

        // When modal closes without selection, put focus back on code field
        $('#sb-item-search-modal').on('hidden.bs.modal', function () {
            if (activeSearchRow) {
                let $target = activeSearchRow.find('.sb-item-code');
                setTimeout(function() {
                    $target.focus();
                }, 50);
            }
        });

        // Prevent the code field focus from re-opening the modal if modal is being closed
        let islModalOpen = false;
        $('#sb-item-search-modal').on('show.bs.modal', function() { islModalOpen = true; });
        $('#sb-item-search-modal').on('hidden.bs.modal', function() {
            islModalOpen = false;
            // Brief delay so focus event from modal close doesn't retrigger
            setTimeout(function() { islModalOpen = false; }, 300);
        });


        function updateBranchBadge() {
            let branchName = $('select[name="branch_id"] option:selected').text() || 'URBAN PETS / MOTERA';
            $('#sb-branch-badge').html('<i class="fas fa-store mr-1"></i> Active Branch: <strong>' + branchName + '</strong>');
        }
        updateBranchBadge();
        $(document).on('change', 'select[name="branch_id"]', updateBranchBadge);

        function updateRowNumbers() {
            $('#sb-items-body tr').each(function (idx) {
                $(this).find('.sb-sr-no').text(idx + 1);
            });
        }

        function calculateRow($row, source) {
            let qty = parseFloat($row.find('.sb-qty').val()) || 0;
            let sellPrice = parseFloat($row.find('.sb-sell-price').val()) || 0;
            let mrp = parseFloat($row.find('.sb-mrp').val()) || 0;
            let stock = parseFloat($row.find('.sb-item-stock').val()) || 0;

            let base = qty * sellPrice;
            let $discPct = $row.find('.sb-disc-percent');
            let $discAmt = $row.find('.sb-disc-amount');
            let gst = parseFloat($row.find('.sb-gst-percent').val()) || 0;

            let discPct = parseFloat($discPct.val()) || 0;
            let discAmt = parseFloat($discAmt.val()) || 0;

            if (source === 'percent') {
                if (base > 0 && discPct > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discPct === 0) {
                    discAmt = 0;
                    $discAmt.val('');
                }
            } else if (source === 'amount') {
                if (base > 0 && discAmt > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                } else if (discAmt === 0) {
                    discPct = 0;
                    $discPct.val('');
                }
            } else {
                if (discPct > 0 && base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discAmt > 0 && base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                }
            }

            let taxable = Math.max(0, base - discAmt);
            let gstAmt = Math.round((taxable * gst / 100) * 100) / 100;
            let net = taxable + gstAmt;

            if (base > 0) {
                $row.find('.sb-row-net').text(net > 0 ? net.toFixed(2) : '');
            } else {
                $row.find('.sb-row-net').text('');
            }

            // Stock warning: check TOTAL qty across ALL rows for the same item
            let $qtyInput = $row.find('.sb-qty');
            let itemId = $row.find('.sb-item-select').val();
            if (stock > 0 && itemId) {
                let totalForItem = 0;
                $('#sb-items-body tr').each(function () {
                    if ($(this).find('.sb-item-select').val() === itemId) {
                        totalForItem += parseFloat($(this).find('.sb-qty').val()) || 0;
                    }
                });
                // Update warning on ALL rows of this item
                $('#sb-items-body tr').each(function () {
                    if ($(this).find('.sb-item-select').val() === itemId) {
                        let $q = $(this).find('.sb-qty');
                        if (totalForItem > stock) {
                            $q.addClass('border-danger text-danger')
                              .attr('title', 'Total qty (' + totalForItem + ') across all rows exceeds stock (' + stock + ')!');
                        } else {
                            $q.removeClass('border-danger text-danger').attr('title', '');
                        }
                    }
                });
            } else {
                $qtyInput.removeClass('border-danger text-danger').attr('title', '');
            }

            calculateTotals();
        }

        // Validate stock and quantities across all rows
        function validateStockErrors() {
            let itemTotals = {};
            let itemStocks = {};

            $('#sb-items-body tr').each(function () {
                let itemId = $(this).find('.sb-item-select').val();
                let qty = parseFloat($(this).find('.sb-qty').val()) || 0;
                let stockVal = $(this).find('.sb-item-stock').val();
                let stock = parseFloat(stockVal);

                if (itemId) {
                    itemTotals[itemId] = (itemTotals[itemId] || 0) + qty;
                    if (!isNaN(stock)) {
                        itemStocks[itemId] = stock;
                    }
                }
            });

            let hasStockError = false;
            let validItemCount = 0;

            $('#sb-items-body tr').each(function () {
                let $row = $(this);
                let itemId = $row.find('.sb-item-select').val();
                let $qtyInput = $row.find('.sb-qty');
                let qty = parseFloat($qtyInput.val()) || 0;

                if (itemId) {
                    let totalQty = itemTotals[itemId] || 0;
                    let stock = itemStocks[itemId] !== undefined ? itemStocks[itemId] : null;

                    if (stock !== null && stock >= 0 && totalQty > stock) {
                        $qtyInput.addClass('border-danger text-danger')
                                 .attr('title', 'Total qty (' + totalQty + ') across all rows exceeds stock (' + stock + ')!');
                        hasStockError = true;
                    } else if (qty <= 0) {
                        $qtyInput.addClass('border-danger text-danger')
                                 .attr('title', 'Quantity 0 se zyada honi chahiye.');
                        hasStockError = true;
                    } else {
                        $qtyInput.removeClass('border-danger text-danger').attr('title', '');
                        validItemCount++;
                    }
                } else {
                    $qtyInput.removeClass('border-danger text-danger').attr('title', '');
                }
            });

            return { hasStockError: hasStockError, validItemCount: validItemCount };
        }

        function calculateTotals() {
            let totalQty = 0;
            let totalDisc = 0;
            let totalNet = 0;
            let itemCount = 0;

            $('#sb-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.sb-qty').val()) || 0;
                let sellPrice = parseFloat($r.find('.sb-sell-price').val()) || 0;
                let discAmt = parseFloat($r.find('.sb-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.sb-gst-percent').val()) || 0;

                if (qty > 0 || sellPrice > 0) {
                    itemCount++;
                    let base = qty * sellPrice;
                    let taxable = Math.max(0, base - discAmt);
                    let gstAmt = Math.round((taxable * gst / 100) * 100) / 100;
                    let net = taxable + gstAmt;

                    totalQty += qty;
                    totalDisc += discAmt;
                    totalNet += net;
                }
            });

            let roundOff = parseFloat($('input[name="round_off"]').val()) || 0;
            let extraCess = parseFloat($('input[name="total_extra_cess"]').val()) || 0;
            let calCess = parseFloat($('input[name="gst_calamity_cess"]').val()) || 0;
            let finalTotal = Math.round((totalNet + roundOff + extraCess + calCess) * 100) / 100;

            $('#footer-sb-qty').text(totalQty > 0 ? totalQty.toFixed(3) : '');
            $('#footer-sb-disc').text(totalDisc > 0 ? totalDisc.toFixed(2) : '');
            $('#footer-sb-net').text(totalNet > 0 ? totalNet.toFixed(2) : '');

            $('#display-sb-final-total').text(finalTotal > 0 ? finalTotal.toFixed(2) : '0.00');
            $('#sb-total-items-badge').html('<span class="badge badge-primary px-3 py-2 font-weight-bold">' + itemCount + ' Item' + (itemCount === 1 ? '' : 's') + '</span>');

            updateSaveButtonState();
        }

        // Enable/disable Save button based on stock and items validity
        function updateSaveButtonState() {
            let res = validateStockErrors();
            let $saveBtn = $('button[type="submit"]');

            if (res.hasStockError || res.validItemCount === 0) {
                let reason = res.validItemCount === 0 ? 'Kam se kam 1 item aur proper quantity dalein.' : 'Kuch items ki qty available stock se zyada hai ya invalid hai.';
                $saveBtn.prop('disabled', true)
                        .attr('title', reason)
                        .addClass('btn-secondary').removeClass('btn-primary');
            } else {
                $saveBtn.prop('disabled', false)
                        .attr('title', '')
                        .addClass('btn-primary').removeClass('btn-secondary');
            }
        }

        // Open Batch Selection Modal
        function showBatchModal($row, item, batches) {
            activeModalRow = $row;
            $('#modal-item-title').text(item.name || 'Item');
            $('#modal-item-code').text(item.item_code || item.ean_upc_code || '—');

            let $tbody = $('#modal-batches-body');
            $tbody.empty();

            batches.forEach(function (b, idx) {
                let pName = b.productname || item.name || 'Item';
                let pCode = b.code || item.item_code || item.ean_upc_code || '—';
                let expDisplay = b.exp_date || 'No Expiry';
                let qtyNum = parseFloat(b.qty || 0);
                let isBatchOOS = qtyNum <= 0;
                let qtyDisplay = qtyNum.toFixed(3);
                let sellDisplay = b.sell_price ? '₹' + parseFloat(b.sell_price).toFixed(2) : '—';
                let mrpDisplay = b.mrp ? '₹' + parseFloat(b.mrp).toFixed(2) : '—';
                let bRowClass = isBatchOOS ? 'batch-select-row batch-disabled text-muted bg-light' : 'batch-select-row';
                let bRowStyle = isBatchOOS ? 'cursor: not-allowed; opacity: 0.65;' : 'cursor: pointer;';
                let bActionBtn = isBatchOOS
                    ? `<button type="button" class="btn btn-secondary btn-xs px-2" disabled title="Out of Stock">
                        <i class="fas fa-ban mr-1"></i>Out of Stock
                       </button>`
                    : `<button type="button" class="btn btn-success btn-xs px-2 btn-apply-batch" 
                        data-exp="${b.exp_date || ''}" 
                        data-sell="${b.sell_price || ''}" 
                        data-mrp="${b.mrp || ''}">
                        <i class="fas fa-check mr-1"></i> Select
                       </button>`;

                let tr = `
                    <tr class="${bRowClass}" style="${bRowStyle}" 
                        data-exp="${b.exp_date || ''}" 
                        data-sell="${b.sell_price || ''}" 
                        data-mrp="${b.mrp || ''}"
                        data-qty="${qtyNum}"
                        title="${isBatchOOS ? 'Batch out of stock' : 'Click to select this batch'}">
                        <td class="align-middle text-center font-weight-bold">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${pName} ${isBatchOOS ? '<span class="badge badge-secondary ml-1 small">No Stock</span>' : ''}</td>
                        <td class="align-middle text-center"><span class="badge badge-secondary px-2 py-1">${pCode}</span></td>
                        <td class="align-middle text-center font-weight-bold text-danger"><i class="far fa-calendar-alt mr-1"></i> ${expDisplay}</td>
                        <td class="align-middle text-right font-weight-bold ${isBatchOOS ? 'text-danger' : ''}">${qtyDisplay}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-center">
                            ${bActionBtn}
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });

            $('#sb-batch-modal').modal('show');
            setTimeout(function() {
                $('#modal-batches-body tr:not(.batch-disabled):first .btn-apply-batch').focus();
            }, 350);
        }

        function applyBatchToRow(exp, sell, mrp) {
            if (!activeModalRow) return;
            if (exp) {
                let cleanExp = exp.toString().substring(0, 10);
                activeModalRow.find('.sb-exp-date').val(cleanExp);
            }
            if (sell && parseFloat(sell) > 0) activeModalRow.find('.sb-sell-price').val(parseFloat(sell).toFixed(2));
            if (mrp && parseFloat(mrp) > 0) activeModalRow.find('.sb-mrp').val(parseFloat(mrp).toFixed(2));

            $('#sb-batch-modal').modal('hide');
            calculateRow(activeModalRow, 'base');
            setTimeout(() => activeModalRow.find('.sb-qty').focus().select(), 100);
        }

        // When user selects a batch from modal button or row
        $(document).on('click', '.btn-apply-batch', function (e) {
            e.stopPropagation();
            if ($(this).closest('tr').hasClass('batch-disabled')) return false;
            let exp = $(this).data('exp') || '';
            let sell = $(this).data('sell') || '';
            let mrp = $(this).data('mrp') || '';
            applyBatchToRow(exp, sell, mrp);
        });

        $(document).on('click', '.batch-select-row', function () {
            if ($(this).hasClass('batch-disabled')) return false;
            let exp = $(this).data('exp') || '';
            let sell = $(this).data('sell') || '';
            let mrp = $(this).data('mrp') || '';
            applyBatchToRow(exp, sell, mrp);
        });

        // Click on batch button in row to re-open modal
        $(document).on('click', '.sb-btn-choose-batch', function () {
            let $row = $(this).closest('tr');
            let batches = $row.data('batches') || [];
            let item = $row.data('item-data') || {};
            if (batches.length > 1) {
                showBatchModal($row, item, batches);
            }
        });

        let isSyncing = false;

        // Main Item Lookup Function
        function processItemLookup(query, $row, itemId) {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let $select = $row.find('.sb-item-select');
            let $desc = $row.find('.sb-item-desc');
            let $code = $row.find('.sb-item-code');
            let $stock = $row.find('.sb-item-stock');
            let $exp = $row.find('.sb-exp-date');
            let $sell = $row.find('.sb-sell-price');
            let $mrp = $row.find('.sb-mrp');
            let $gst = $row.find('.sb-gst-percent');
            let $batchWrap = $row.find('.sb-batch-btn-wrap');

            let params = { branch_id: branchId };
            if (itemId) {
                params.item_id = itemId;
            } else if (query) {
                params.query = query;
            } else {
                return;
            }

            $.getJSON('{{ route("sales.sales-bills.lookup-item") }}', params, function (res) {
                if (res && res.found && res.item) {
                    let item = res.item;
                    let batches = res.batches || [];

                    $row.data('item-data', item);
                    $row.data('batches', batches);

                    isSyncing = true;
                    // Sync Code
                    let codeVal = item.item_code || item.ean_upc_code || '';
                    if (codeVal) $code.val(codeVal);

                    // Sync description display & hidden item id
                    $desc.val(item.name + (item.item_code ? ' [' + item.item_code + ']' : ''));
                    $select.val(item.id);
                    isSyncing = false;

                    // Show Product Stock
                    let stockVal = item.stock ? parseFloat(item.stock).toFixed(2) : '0.00';
                    $stock.val(stockVal).attr('title', 'Available Stock: ' + stockVal);

                    // Set Sell Price, MRP, GST %
                    if (item.sell_price > 0 && (!$sell.val() || parseFloat($sell.val()) === 0)) {
                        $sell.val(parseFloat(item.sell_price).toFixed(2));
                    }
                    if (item.mrp > 0 && (!$mrp.val() || parseFloat($mrp.val()) === 0)) {
                        $mrp.val(parseFloat(item.mrp).toFixed(2));
                    }
                    if (item.gst_percent !== undefined && item.gst_percent !== null) {
                        $gst.val(parseFloat(item.gst_percent || 0).toFixed(2));
                    }

                    // =========================================================
                    // BATCH / EXPIRY SELECTION LOGIC:
                    // Single Expiry: Auto-fill expiry date!
                    // Multiple Batches: Show modal with productname, code, sell price, qty and expiry!
                    // =========================================================
                    if (batches.length === 1) {
                        // Agar single ho to expiry date automatic aani chahiye
                        let singleBatch = batches[0];
                        if (singleBatch && singleBatch.exp_date) {
                            let cleanExp = singleBatch.exp_date.toString().substring(0, 10);
                            $exp.val(cleanExp);
                        }
                        if (singleBatch && singleBatch.sell_price > 0) {
                            $sell.val(parseFloat(singleBatch.sell_price).toFixed(2));
                        }
                        if (singleBatch && singleBatch.mrp > 0) {
                            $mrp.val(parseFloat(singleBatch.mrp).toFixed(2));
                        }
                        $batchWrap.addClass('d-none');
                        calculateRow($row, 'base');
                        setTimeout(() => $row.find('.sb-qty').focus().select(), 60);
                    } else if (batches.length > 1) {
                        // Multiple batches exist: show button and pop up selection modal!
                        $batchWrap.removeClass('d-none');
                        showBatchModal($row, item, batches);
                    } else {
                        $batchWrap.addClass('d-none');
                        calculateRow($row, 'base');
                        setTimeout(() => $row.find('.sb-qty').focus().select(), 60);
                    }

                    calculateRow($row, 'base');
                } else {
                    $code.addClass('is-invalid');
                    setTimeout(() => $code.removeClass('is-invalid'), 2000);
                }
            });
        }

        // 1. Enter Code / Barcode in row
        $(document).on('change blur keydown', '.sb-item-code', function (e) {
            if (isSyncing) return;
            if (e.type === 'keydown' && e.key !== 'Enter') return;
            if (e.type === 'keydown' && e.key === 'Enter') {
                e.preventDefault();
            }
            let $input = $(this);
            let query = $.trim($input.val());
            if (!query) return;

            let $row = $input.closest('tr');
            processItemLookup(query, $row, null);
        });

        // 2. Select Item from Description Select2
        $(document).on('change', '.sb-item-select', function () {
            if (isSyncing) return;
            let $select = $(this);
            let itemId = $select.val();
            let $row = $select.closest('tr');

            if (!itemId) {
                $row.find('.sb-item-code').val('');
                $row.find('.sb-item-stock').val('');
                $row.find('.sb-exp-date').val('');
                $row.find('.sb-batch-btn-wrap').addClass('d-none');
                return;
            }

            processItemLookup(null, $row, itemId);
        });

        // 3. Real-time Calculation Listeners
        $(document).on('input', '.sb-qty, .sb-sell-price, .sb-mrp', function () {
            calculateRow($(this).closest('tr'), 'base');
        });

        $(document).on('input', '.sb-disc-percent', function () {
            calculateRow($(this).closest('tr'), 'percent');
        });

        $(document).on('input', '.sb-disc-amount', function () {
            calculateRow($(this).closest('tr'), 'amount');
        });

        $(document).on('input change blur', '.sb-gst-percent', function () {
            calculateRow($(this).closest('tr'), 'other');
        });

        $(document).on('input change', 'input[name="round_off"], input[name="total_extra_cess"], input[name="gst_calamity_cess"]', function () {
            calculateTotals();
        });

        // 4. Add Row
        $('#sb-add-row').on('click', function () {
            let html = $('#sb-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#sb-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);
            $newRow.find('input').attr('autocomplete', 'off');
            rowIndex++;
            updateRowNumbers();
            calculateTotals();
            $newRow.find('.sb-item-code').focus();
        });

        function addNewRowAndOpenModal() {
            $('#sb-add-row').trigger('click');
            let $newRow = $('#sb-items-body tr:last');
            activeSearchRow = $newRow;
            $('#isl-filter-name').val('');
            $('#isl-filter-code').val('');
            $('#isl-filter-expiry').val('');
            fetchItemList();
            $('#sb-item-search-modal').modal('show');
            $('#sb-item-search-modal').one('shown.bs.modal', function () {
                $('#isl-filter-name').focus();
            });
        }

        // Fast POS keyboard flow: Qty -> Disc % -> Disc Amt -> Auto Add Row + Open Modal
        $(document).on('keydown', '.sb-qty', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('tr').find('.sb-disc-percent').focus().select();
            }
        });

        $(document).on('keydown', '.sb-disc-percent', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).closest('tr').find('.sb-disc-amount').focus().select();
            }
        });

        $(document).on('keydown', '.sb-disc-amount', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                e.preventDefault();
                addNewRowAndOpenModal();
            }
        });

        // 5. Remove Row
        $('#sb-items-body').on('click', '.sb-remove-row', function () {
            let rows = $('#sb-items-body tr');
            if (rows.length <= 1) return;
            $(this).closest('tr').remove();
            updateRowNumbers();
            calculateTotals();
        });

        // 6. Initial Run on existing rows
        updateRowNumbers();
        $('#sb-items-body tr').each(function () {
            let $r = $(this);
            calculateRow($r, 'initial');
            let itemId = $r.find('.sb-item-select').val();
            if (itemId) {
                processItemLookup(null, $r, itemId);
            }
        });
        calculateTotals();

        /* ================================================================
           TENDER / PAYMENT MODAL — intercept form submit
           ================================================================ */
        const TENDER_TYPES = JSON.parse(document.getElementById('tender-types-data').textContent || '[]');
        let tenderBillTotal = 0;

        function buildTenderTypeOptions(selectedId) {
            let html = '<option value="">-- Select Mode --</option>';
            TENDER_TYPES.forEach(function (t) {
                let sel = (t.id == selectedId) ? 'selected' : '';
                html += `<option value="${t.id}" data-mandate="${t.mandate_refno ? 1 : 0}" ${sel}>${t.name}</option>`;
            });
            return html;
        }

        function addTenderRow(typeId, amount) {
            let rowHtml = `
                <tr class="tender-row">
                    <td>
                        <select class="form-control form-control-sm tender-type-sel">${buildTenderTypeOptions(typeId || '')}</select>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm text-right tender-amount" step="0.01" min="0" value="${amount || ''}">
                    </td>
                    <td class="text-center align-middle">
                        <button type="button" class="btn btn-link btn-sm text-danger p-0 tender-remove-row" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>`;
            $('#tender-rows-body').append(rowHtml);
            recalcTender();
        }

        function recalcTender() {
            let tendered = 0;
            $('.tender-amount').each(function () {
                tendered += parseFloat($(this).val()) || 0;
            });
            tendered = Math.round(tendered * 100) / 100;
            let balance = Math.round((tendered - tenderBillTotal) * 100) / 100;

            $('#tender-tendered').text('₹' + tendered.toFixed(2));
            $('#tender-balance').text('₹' + balance.toFixed(2));
            $('#tender-balance').removeClass('text-success text-danger text-dark')
                .addClass(balance >= 0 ? 'text-success' : 'text-danger');
            $('#tender-outstanding').text('₹' + Math.max(0, -balance).toFixed(2));
            $('#tender-error').addClass('d-none').text('');
        }

        // Open tender modal when Save button clicked
        $(document).on('click', 'button[type="submit"]', function (e) {
            let $btn = $(this);
            if ($btn.prop('disabled') || $btn.hasClass('disabled')) {
                e.preventDefault();
                return false;
            }

            let $form = $btn.closest('form');
            if (!$form.length) return;

            // Check stock validation before opening tender modal
            let res = validateStockErrors();
            if (res.hasStockError || res.validItemCount === 0) {
                e.preventDefault();
                alert(res.validItemCount === 0 ? 'Kripya kam se kam ek item ki proper quantity dalein.' : 'Kuch items ki quantity available stock se zyada hai ya invalid hai. Pehle theek karein.');
                return false;
            }

            // Basic HTML5 validity check first
            if (!$form[0].checkValidity()) {
                $form[0].reportValidity();
                return;
            }

            e.preventDefault();

            // Read current bill total from display
            tenderBillTotal = parseFloat($('#display-sb-final-total').text()) || 0;
            $('#tender-bill-total').text(tenderBillTotal.toFixed(2));

            // Reset modal
            $('#tender-rows-body').empty();
            $('#tender-error').addClass('d-none');

            // Default: Cash row pre-filled with total
            let cashType = TENDER_TYPES.find(t => t.type === 'Cash' || t.name === 'Cash');
            addTenderRow(cashType ? cashType.id : '', tenderBillTotal.toFixed(2));

            $('#sb-tender-modal').modal('show');
        });

        // Add row
        $('#tender-add-row').on('click', function () {
            addTenderRow('', '');
        });

        // Remove row
        $(document).on('click', '.tender-remove-row', function () {
            $(this).closest('tr').remove();
            recalcTender();
        });

        // Recalc on change
        $(document).on('input change', '.tender-amount, .tender-type-sel', function () {
            recalcTender();
        });

        // Confirm & Save
        $('#tender-confirm-btn').on('click', function () {
            $('#tender-error').addClass('d-none');

            let payments = [];
            let valid = true;
            let tendered = 0;

            $('.tender-row').each(function () {
                let typeId = $(this).find('.tender-type-sel').val();
                let amount = parseFloat($(this).find('.tender-amount').val()) || 0;

                if (!typeId) { valid = false; return false; }
                if (amount <= 0) { valid = false; return false; }

                payments.push({ tender_type_id: typeId, amount: amount });
                tendered += amount;
            });

            if (!valid) {
                $('#tender-error').removeClass('d-none').text('Har row mein payment mode aur amount required hai.');
                return;
            }

            tendered = Math.round(tendered * 100) / 100;
            let diff = Math.abs(tendered - Math.round(tenderBillTotal * 100) / 100);
            if (diff > 0.01) {
                $('#tender-error').removeClass('d-none')
                    .text('Payment total ₹' + tendered.toFixed(2) + ' bill total ₹' + tenderBillTotal.toFixed(2) + ' se match nahi karta.');
                return;
            }

            // Inject hidden payment inputs into form
            let $form = $('form[action*="sales-bills"]').first();
            $form.find('input[name^="payments"]').remove(); // clean old

            payments.forEach(function (p, i) {
                $form.append(`<input type="hidden" name="payments[${i}][tender_type_id]" value="${p.tender_type_id}">`);
                $form.append(`<input type="hidden" name="payments[${i}][amount]" value="${p.amount}">`);
            });

            // Use native form submit to bypass jQuery event re-interception
            let submitted = false;
            function doSubmit() {
                if (!submitted) {
                    submitted = true;
                    $form[0].submit(); // native submit — bypasses jQuery click handlers
                }
            }

            $('#sb-tender-modal').one('hidden.bs.modal', function () {
                doSubmit();
            });
            $('#sb-tender-modal').modal('hide');

            // Fallback in case modal event doesn't fire immediately
            setTimeout(doSubmit, 350);
        });

        function fetchCustomerLoyalty(customerId) {
            if (!customerId) {
                $('#sb-customer-loyalty-badge').addClass('d-none').removeClass('d-flex');
                return;
            }
            $.ajax({
                url: "{{ url('sales/sales-bills/customer-loyalty') }}/" + customerId,
                type: 'GET',
                dataType: 'json',
                success: function (res) {
                    if (res && res.enable_loyalty) {
                        $('#sb-loyalty-pts').text(Number(res.balance_points).toFixed(2));
                        $('#sb-loyalty-val').text(Number(res.rupee_value).toFixed(2));
                        $('#sb-loyalty-notice').text(res.can_redeem ? 'Eligible to Redeem' : 'Min ' + res.min_points_redeem + ' pts required');
                        $('#sb-customer-loyalty-badge').removeClass('d-none').addClass('d-flex');
                    } else {
                        $('#sb-customer-loyalty-badge').addClass('d-none').removeClass('d-flex');
                    }
                }
            });
        }

        $('select[name="customer_id"]').on('change', function () {
            fetchCustomerLoyalty($(this).val());
        });

        if ($('select[name="customer_id"]').val()) {
            fetchCustomerLoyalty($('select[name="customer_id"]').val());
        }

    });
</script>
@endpush
