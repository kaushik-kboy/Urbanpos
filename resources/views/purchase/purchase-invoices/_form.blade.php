@php
    $inv = $purchaseInvoice ?? null;
    $sourceRn = $sourceReceiptNote ?? null;
    $sourcePo = $sourceOrder ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($inv?->items ?? ($convertedItems ?? collect()));

    $selectedSupplier = old('supplier_id', $inv->supplier_id ?? ($sourceRn->supplier_id ?? ($sourcePo->supplier_id ?? '')));
    $selectedBranch = old('branch_id', $inv->branch_id ?? ($sourceRn->branch_id ?? ($sourcePo->branch_id ?? '')));
    $selectedPo = old('purchase_order_id', $inv->purchase_order_id ?? ($sourceRn->purchase_order_id ?? ($sourcePo->id ?? '')));
    $grnNumberVal = old('grn_number', $inv->grn_number ?? ($sourceRn->receipt_number ?? ($nextGrnNumber ?? '')));
    $grnDateVal = old('grn_date', optional($inv->grn_date ?? ($sourceRn->receipt_date ?? now()))->format('Y-m-d'));
    $rnIdVal = old('purchase_receipt_note_id', $inv->purchase_receipt_note_id ?? ($sourceRn->id ?? ''));
@endphp

<style>
    /* Remove spinners / up-down stepper buttons from all number inputs */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }
    input[type=number] {
        -moz-appearance: textfield !important;
        appearance: textfield !important;
    }
</style>

@if ($sourceRn)
    <div class="alert alert-info py-2 mb-3">
        <i class="fas fa-receipt mr-1"></i> Converting from Receipt Note <strong>{{ $sourceRn->receipt_number }}</strong>.
        Physical stock was already received on {{ optional($sourceRn->receipt_date)->format('d M Y') }}; saving this invoice books financial liabilities and updates item prices without duplicating inventory.
    </div>
@elseif ($sourcePo)
    <div class="alert alert-info py-2 mb-3">
        <i class="fas fa-file-invoice mr-1"></i> Converting directly from Purchase Order <strong>{{ $sourcePo->po_number }}</strong>.
        Items, quantities, and costs have been loaded automatically. Stock will be added to inventory upon saving this invoice.
    </div>
@endif

<input type="hidden" name="purchase_receipt_note_id" value="{{ $rnIdVal }}">

<div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0 text-muted font-weight-bold text-uppercase small"><i class="fas fa-file-invoice text-primary mr-1"></i> Invoice Details</h5>
    <x-form-layout-customizer
        form-key="purchase_invoices.header"
        container-id="pinv-header-fields-grid"
        title="Customize Purchase Invoice Header"
    />
</div>

<div class="row g-2 form-fields-grid" id="pinv-header-fields-grid">
    <div class="field-wrapper col-md-6" data-field="invoice_date" data-label="Invoice Date" data-default-order="1" data-core="1">
        <x-field name="invoice_date" label="Invoice Date" type="date" :value="optional($inv->invoice_date ?? now())->format('Y-m-d')" max="{{ date('Y-m-d') }}" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="supplier_id" data-label="Supplier" data-default-order="2" data-core="1">
        <x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$selectedSupplier" placeholder="Select a Supplier" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="branch_id" data-label="Branch" data-default-order="3" data-core="1">
        <x-select name="branch_id" label="Branch" :options="$branches" :selected="$selectedBranch" placeholder="Select a Branch" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="purchase_order_id" data-label="Purchase Order" data-default-order="4">
        <x-select name="purchase_order_id" label="Purchase Order" :options="$purchaseOrders" :selected="$selectedPo" placeholder="Select PO" />
    </div>

    <div class="field-wrapper col-md-6" data-field="purchase_type" data-label="Purchase Type" data-default-order="5" data-core="1">
        <x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$inv->purchase_type ?? 'Local'" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="c_form" data-label="C-Form" data-default-order="6">
        <x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$inv->c_form ?? 'No Forms'" required />
    </div>

    <div class="field-wrapper col-md-6" data-field="grn_number" data-label="GRN Number" data-default-order="7">
        <x-field name="grn_number" label="GRN Number" :value="$grnNumberVal" readonly />
    </div>

    <div class="field-wrapper col-md-6" data-field="grn_date" data-label="GRN Date" data-default-order="8">
        <x-field name="grn_date" label="GRN Date" type="date" :value="$grnDateVal" max="{{ date('Y-m-d') }}" />
    </div>

    <div class="field-wrapper col-md-6" data-field="supplier_inv_no" data-label="Inv No (Supplier)" data-default-order="9">
        <x-field name="supplier_inv_no" label="Inv No (Supplier)" :value="$inv->supplier_inv_no ?? ''" style="text-transform: uppercase;" oninput="this.value = this.value.toUpperCase();" placeholder="e.g. INV-2026-001" required data-check-url="{{ route('purchase.purchase-invoices.check-supplier-inv') }}" data-invoice-id="{{ $inv?->id ?? '' }}" />
        <div class="form-group row mt-n2 mb-2" id="supplier-inv-feedback-container" style="display: none;">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <div id="supplier-inv-feedback" class="small font-weight-bold text-danger"></div>
            </div>
        </div>
    </div>

    <div class="field-wrapper col-md-6" data-field="supplier_inv_date" data-label="Inv Date (Supplier)" data-default-order="10">
        <x-field name="supplier_inv_date" label="Inv Date (Supplier)" type="date" :value="optional($inv->supplier_inv_date ?? now())->format('Y-m-d')" max="{{ date('Y-m-d') }}" />
    </div>

    <div class="field-wrapper col-md-6" data-field="supplier_inv_amount" data-label="Inv Amount (Supplier)" data-default-order="11" data-core="1">
        <x-field name="supplier_inv_amount" label="Inv Amount (Supplier)" type="number" step="0.01" :value="isset($inv->supplier_inv_amount) && $inv->supplier_inv_amount != 0 ? $inv->supplier_inv_amount : ''" required />
        <div class="form-group row mt-n2 mb-2" id="supplier-inv-amount-match-container">
            <div class="col-sm-3"></div>
            <div class="col-sm-6">
                <div id="supplier-inv-amount-match-status" class="small font-weight-bold"></div>
            </div>
        </div>
    </div>
</div>

<hr>
<h5 class="mb-3">Items</h5>

<div class="table-responsive">
    <table class="table table-sm table-bordered" id="pinv-items-table">
        <thead>
            <tr>
                <th style="width: 35px;" class="text-center">#</th>
                <th style="width: 110px;">Code</th>
                <th style="min-width: 220px;">Description</th>
                <th style="width: 125px;">Exp Date</th>
                <th style="width: 85px;">Qty</th>
                <th style="width: 80px;">Free</th>
                <th style="width: 95px;">Cost Price</th>
                <th style="width: 95px;">Sell Price</th>
                <th style="width: 95px;">MRP</th>
                <th style="width: 85px;" title="Margin % = [(Selling Price incl. GST ÷ (1 + GST%/100)) − Cost] ÷ [Selling Price incl. GST ÷ (1 + GST%/100)] × 100">Margin %</th>
                <th style="width: 85px;" title="Profit % = Profit Amount ÷ Cost Price × 100">Profit %</th>
                <th style="width: 80px;">Disc %</th>
                <th style="width: 90px;">Disc Amt</th>
                <th style="width: 75px;">GST %</th>
                <th style="width: 95px;">GST Tax Amt</th>
                <th style="width: 105px;" class="text-right">Net Amount</th>
                <th style="width: 35px;"></th>
            </tr>
        </thead>
        <tbody id="pinv-items-body">
            @forelse ($existingItems as $index => $line)
                @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => $index, 'line' => $line])
            @empty
                @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => 0, 'line' => null])
            @endforelse
        </tbody>
        <tfoot class="bg-light font-weight-bold">
            <tr>
                <td colspan="4" class="text-right align-middle">Totals:</td>
                <td class="text-right align-middle text-primary font-weight-bold" id="footer-total-qty"></td>
                <td class="align-middle"></td>
                <td class="text-right align-middle font-weight-bold" id="footer-total-cost"></td>
                <td colspan="4" class="text-right align-middle">Total Discount:</td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="footer-total-disc"></td>
                <td class="text-right align-middle small text-muted">GST:</td>
                <td class="text-right align-middle font-weight-bold text-dark" id="footer-total-gst"></td>
                <td class="text-right align-middle text-success font-weight-bold" id="footer-grand-net"></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<button type="button" id="pinv-add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<h5 class="mb-3">Totals</h5>
<div class="alert alert-light border py-2 d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted mr-2 font-weight-bold">Calculated Final Amount:</span>
        <strong class="text-primary h5 mb-0">₹<span id="display-final-total">0.00</span></strong>
    </div>
    <div id="final-amount-match-badge"></div>
</div>

<x-field name="freight" label="Freight" type="number" step="0.01" :value="isset($inv->freight) && $inv->freight != 0 ? $inv->freight : ''" />
<x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="isset($inv->scheme_item_disc_amt) && $inv->scheme_item_disc_amt != 0 ? $inv->scheme_item_disc_amt : ''" />
<x-field name="scheme_item_disc_percent" label="Scheme ItemDisc%" type="number" step="0.01" :value="isset($inv->scheme_item_disc_percent) && $inv->scheme_item_disc_percent != 0 ? $inv->scheme_item_disc_percent : ''" />
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="isset($inv->round_off) && $inv->round_off != 0 ? $inv->round_off : ''" />
<x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="isset($inv->other_disc_amt) && $inv->other_disc_amt != 0 ? $inv->other_disc_amt : ''" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="isset($inv->total_extra_cess) && $inv->total_extra_cess != 0 ? $inv->total_extra_cess : ''" />
<x-field name="tcs_amount" label="TCS Amt" type="number" step="0.01" :value="isset($inv->tcs_amount) && $inv->tcs_amount != 0 ? $inv->tcs_amount : ''" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="isset($inv->total_weight) && $inv->total_weight != 0 ? $inv->total_weight : ''" />
<x-textarea name="remarks" label="Remarks" :value="$inv->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$inv->message ?? ''" />

<x-custom-fields-renderer :module="'PurchaseInvoice'" :model="$inv ?? null" :cardStyle="true" />

<template id="pinv-row-template">
    @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

<!-- ============================================================
     ITEM SEARCH MODAL — opens on Code/Barcode field focus
     ============================================================ -->
<div class="modal fade" id="pinv-item-search-modal" tabindex="-1" role="dialog" aria-labelledby="pinvItemSearchLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title" id="pinvItemSearchLabel">
                    <i class="fas fa-search mr-2"></i>Select Item
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <!-- Filters -->
                <div class="row mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" id="pinv-isl-filter-name" class="form-control" placeholder="Search product name, code or barcode…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                            </div>
                            <input type="text" id="pinv-isl-filter-code" class="form-control" placeholder="Filter by code…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" id="pinv-isl-filter-expiry" class="form-control" placeholder="Filter expiry…" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <button type="button" id="pinv-isl-btn-clear" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i>Clear
                        </button>
                    </div>
                </div>

                <!-- Loading / No-results / Hint states -->
                <div id="pinv-isl-loading" class="text-center py-4 d-none">
                    <i class="fas fa-circle-notch fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Loading items…</p>
                </div>
                <div id="pinv-isl-no-results" class="text-center py-4 d-none">
                    <i class="fas fa-inbox fa-2x text-muted"></i>
                    <p class="mt-2 text-muted">No items found.</p>
                </div>

                <!-- Items Table -->
                <div class="table-responsive" id="pinv-isl-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0" id="pinv-isl-items-table">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th class="text-center" style="width: 40px;">#</th>
                                <th>Product Name</th>
                                <th class="text-center" style="width: 120px;">Code</th>
                                <th class="text-right" style="width: 95px;">Cost Price</th>
                                <th class="text-right" style="width: 95px;">Sell Price</th>
                                <th class="text-right" style="width: 90px;">MRP</th>
                                <th class="text-right" style="width: 85px;">Stock</th>
                                <th class="text-center" style="width: 120px;">Expiry / Batch</th>
                                <th class="text-center" style="width: 80px;">Select</th>
                            </tr>
                        </thead>
                        <tbody id="pinv-isl-items-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
                <small class="text-muted mt-2 d-block" id="pinv-isl-count-label"></small>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
    $(document).ready(function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeSearchRow = null;   // which row triggered the item search modal
        let pendingFocusExpRow = null; // row to focus on Exp Date after modal hide
        let islDebounce = null;
        let islCache = {};
        let islLastKey = null;
        let islModalOpen = false;
        const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';

        const supplierPurchaseTypes = @json(\App\Models\Supplier::pluck('purchase_type', 'id'));

        // Auto-set purchase_type based on supplier's purchase_type
        $('#supplier_id').on('change', function () {
            let sId = $(this).val();
            if (sId && supplierPurchaseTypes[sId]) {
                let pType = supplierPurchaseTypes[sId];
                if (pType === 'Local' || pType === 'Interstate') {
                    $('#purchase_type').val(pType).trigger('change');
                }
            }
            validateSupplierInvNo();
        });

        // Real-time Supplier Invoice Number validation & duplication check
        let suppInvDebounce = null;
        function validateSupplierInvNo() {
            let $input = $('#supplier_inv_no');
            let $feedbackContainer = $('#supplier-inv-feedback-container');
            let $feedback = $('#supplier-inv-feedback');
            let invNo = $.trim($input.val()).toUpperCase();
            let suppId = $('#supplier_id').val();
            let ignoreId = $input.data('invoice-id') || '';
            let checkUrl = $input.data('check-url');

            if (!invNo) {
                $input.addClass('is-invalid border-danger').removeClass('is-valid');
                $feedback.text('Supplier Invoice Number is required before moving forward.').show();
                $feedbackContainer.show();
                return false;
            }

            if (!suppId) {
                $input.removeClass('is-invalid is-valid border-danger');
                $feedbackContainer.hide();
                return true;
            }

            $.getJSON(checkUrl, { supplier_id: suppId, supplier_inv_no: invNo, ignore_id: ignoreId }, function (res) {
                if (res.is_duplicate) {
                    $input.addClass('is-invalid border-danger').removeClass('is-valid');
                    let msg = res.message || `Supplier Invoice Number '${invNo}' is already recorded for this supplier.`;
                    $feedback.text(msg).show();
                    $feedbackContainer.show();
                    alert(msg);
                } else {
                    $input.removeClass('is-invalid border-danger').addClass('is-valid');
                    $feedback.text('').hide();
                    $feedbackContainer.hide();
                }
            });
            return true;
        }

        $('#supplier_inv_no').on('blur change', function () {
            validateSupplierInvNo();
        });

        $('#supplier_inv_no').on('input', function () {
            clearTimeout(suppInvDebounce);
            suppInvDebounce = setTimeout(validateSupplierInvNo, 400);
        });

        function focusExpDateField($row) {
            if (!$row || !$row.length) return;
            let $exp = $row.find('.pinv-exp-date');
            if ($exp.length) {
                $exp.trigger('focus').focus().trigger('click');
            }
        }

        /* ================================================================
           ITEM SEARCH MODAL — open on Code/Barcode focus
           ================================================================ */

        // Debounced filter inputs — 400ms to avoid firing on every keystroke
        $('#pinv-isl-filter-name, #pinv-isl-filter-code, #pinv-isl-filter-expiry').on('input', function () {
            clearTimeout(islDebounce);
            islDebounce = setTimeout(fetchItemList, 400);
        });

        $('#pinv-isl-btn-clear').on('click', function () {
            $('#pinv-isl-filter-name, #pinv-isl-filter-code, #pinv-isl-filter-expiry').val('');
            fetchItemList();
        });

        function showHintState(msg) {
            $('#pinv-isl-loading').addClass('d-none');
            $('#pinv-isl-table-wrap').addClass('d-none');
            $('#pinv-isl-items-body').empty();
            $('#pinv-isl-no-results').removeClass('d-none').html(
                '<i class="fas fa-search fa-2x text-muted"></i>' +
                '<p class="mt-2 text-muted">' + (msg || 'Type at least 1 character to search…') + '</p>'
            );
            $('#pinv-isl-count-label').text('');
        }

        function fetchItemList() {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let srch     = $.trim($('#pinv-isl-filter-name').val());
            let code     = $.trim($('#pinv-isl-filter-code').val());
            let expiry   = $.trim($('#pinv-isl-filter-expiry').val());

            // If no filter at all, show hint without hitting database
            if (!srch && !code && !expiry) {
                showHintState('Type product name, code or barcode to search…');
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

            $('#pinv-isl-loading').removeClass('d-none');
            $('#pinv-isl-no-results').addClass('d-none');
            $('#pinv-isl-table-wrap').addClass('d-none');

            $.getJSON(ISL_URL, params, function (res) {
                $('#pinv-isl-loading').addClass('d-none');
                // Cache for 60s
                islCache[cacheKey] = res.items || [];
                setTimeout(function() { delete islCache[cacheKey]; }, 60000);
                renderItems(res.items || []);
            }).fail(function () {
                $('#pinv-isl-loading').addClass('d-none');
                showHintState('Error loading items. Please try again.');
            });
        }

        let islSelectedIdx = -1;

        function updateModalHighlight() {
            let $rows = $('#pinv-isl-items-body tr.pinv-isl-item-row');
            $rows.removeClass('table-primary');
            if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                let $target = $rows.eq(islSelectedIdx);
                $target.addClass('table-primary');
                let container = $('#pinv-isl-table-wrap')[0];
                let rowEl = $target[0];
                if (container && rowEl) {
                    let cTop = container.scrollTop;
                    let cBottom = cTop + container.clientHeight;
                    let rTop = rowEl.offsetTop;
                    let rBottom = rTop + rowEl.clientHeight;
                    if (rTop < cTop) container.scrollTop = rTop;
                    else if (rBottom > cBottom) container.scrollTop = rBottom - container.clientHeight;
                }
            }
        }

        $('#pinv-isl-filter-name, #pinv-isl-filter-code, #pinv-isl-filter-expiry').on('keydown', function (e) {
            let $rows = $('#pinv-isl-items-body tr.pinv-isl-item-row');
            if ($rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                islSelectedIdx = Math.min(islSelectedIdx + 1, $rows.length - 1);
                updateModalHighlight();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                islSelectedIdx = Math.max(islSelectedIdx - 1, 0);
                updateModalHighlight();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (islSelectedIdx >= 0 && islSelectedIdx < $rows.length) {
                    $rows.eq(islSelectedIdx).trigger('click');
                } else if ($rows.length === 1) {
                    $rows.eq(0).trigger('click');
                }
            }
        });

        function renderItems(items) {
            let $tbody = $('#pinv-isl-items-body');
            $tbody.empty();

            if (items.length === 0) {
                $('#pinv-isl-no-results').removeClass('d-none').html(
                    '<i class="fas fa-inbox fa-2x text-muted"></i>' +
                    '<p class="mt-2 text-muted">No items found.</p>'
                );
                $('#pinv-isl-count-label').text('');
                islSelectedIdx = -1;
                return;
            }

            let html = '';
            items.forEach(function (it, idx) {
                let expBadge = '<span class="text-muted">—</span>';
                if (it.exp_date) {
                    expBadge = `<span class="badge badge-info px-2 py-1"><i class="far fa-calendar-alt mr-1"></i>${it.exp_date}</span>`;
                } else if (['Mandatory', 'Days', 'Month'].includes(it.batch_expiry_details)) {
                    expBadge = `<span class="badge badge-warning px-2 py-1"><i class="fas fa-exclamation-circle mr-1"></i>${it.batch_expiry_details}</span>`;
                }

                let codeBadge = it.code
                    ? `<span class="badge badge-secondary px-2 py-1">${it.code}</span>`
                    : `<span class="text-muted">—</span>`;

                let qtyClass = it.qty <= 0 ? 'text-muted' : 'text-primary font-weight-bold';
                let costDisplay = it.cost_price > 0 ? '₹' + parseFloat(it.cost_price).toFixed(2) : '—';
                let sellDisplay = it.sell_price > 0 ? '₹' + parseFloat(it.sell_price).toFixed(2) : '—';
                let mrpDisplay  = it.mrp > 0 ? '₹' + parseFloat(it.mrp).toFixed(2) : '—';

                html += `
                    <tr class="pinv-isl-item-row ${idx === 0 ? 'table-primary' : ''}" style="cursor:pointer;"
                        data-id="${it.id}"
                        data-code="${it.code}">
                        <td class="align-middle text-center font-weight-bold text-muted">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-dark">${it.name}</td>
                        <td class="align-middle text-center">${codeBadge}</td>
                        <td class="align-middle text-right font-weight-bold text-primary">${costDisplay}</td>
                        <td class="align-middle text-right font-weight-bold text-success">${sellDisplay}</td>
                        <td class="align-middle text-right text-muted">${mrpDisplay}</td>
                        <td class="align-middle text-right ${qtyClass}">${parseFloat(it.qty).toFixed(2)}</td>
                        <td class="align-middle text-center">${expBadge}</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 pinv-isl-btn-select"
                                data-id="${it.id}" data-code="${it.code}">
                                <i class="fas fa-check mr-1"></i>Select
                            </button>
                        </td>
                    </tr>`;
            });
            $tbody.html(html);
            $('#pinv-isl-table-wrap').removeClass('d-none');
            $('#pinv-isl-count-label').text(items.length + (items.length === 100 ? '+ (showing top 100)' : '') + ' item(s) found');
            islSelectedIdx = items.length > 0 ? 0 : -1;
        }

        let islModalClosing = false;
        let itemSelectedInModal = false;
        let cancellingRow = null;

        // Clicking a row or its Select button picks the item
        $(document).on('click', '.pinv-isl-item-row, .pinv-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('pinv-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');

            let $targetRow = activeSearchRow;
            if (! $targetRow || ! itemId) return;

            itemSelectedInModal = true;
            cancellingRow = null;
            pendingFocusExpRow = $targetRow;

            $targetRow.find('.pinv-item-select').val(itemId);
            if (itemCode) {
                $targetRow.find('.pinv-item-code').val(itemCode);
            }

            processPurchaseItemLookup($targetRow, itemId);
            $('#pinv-item-search-modal').modal('hide');
        });

        $('#pinv-item-search-modal').on('show.bs.modal', function() {
            islModalOpen = true;
            islModalClosing = false;
            itemSelectedInModal = false;
            cancellingRow = null;
        });

        $('#pinv-item-search-modal').on('hide.bs.modal', function() {
            islModalOpen = false;
            islModalClosing = true;
            if (!itemSelectedInModal && activeSearchRow && activeSearchRow.length) {
                let selectedId = activeSearchRow.find('.pinv-item-select').val();
                if (!selectedId) {
                    cancellingRow = activeSearchRow;
                }
            }
        });

        $('#pinv-item-search-modal').on('hidden.bs.modal', function() {
            islModalOpen = false;
            islModalClosing = true;
            setTimeout(function() { islModalClosing = false; }, 350);

            if (!itemSelectedInModal && cancellingRow && cancellingRow.length) {
                let totalRows = $('#pinv-items-body tr').length;
                if (totalRows > 1) {
                    cancellingRow.remove();
                    updateRowNumbers();
                    calculateTotals();
                } else {
                    cancellingRow.find('.pinv-item-code').val('');
                    cancellingRow.find('.pinv-item-desc').val('');
                }
                cancellingRow = null;
                activeSearchRow = null;
                setTimeout(function() {
                    let $freight = $('#freight');
                    if ($freight.length) {
                        $freight.focus().select();
                    } else {
                        $('#pinv-add-row').focus();
                    }
                }, 60);
                return;
            }

            itemSelectedInModal = false;
            cancellingRow = null;
            activeSearchRow = null;

            if (pendingFocusExpRow && pendingFocusExpRow.length) {
                let $target = pendingFocusExpRow;
                pendingFocusExpRow = null;
                setTimeout(function () {
                    focusExpDateField($target);
                }, 100);
            }
        });

        // Open modal on Code/Barcode field CLICK or FOCUS; do not reopen if item already selected
        $(document).off('click focus', '.pinv-item-code').on('click focus', '.pinv-item-code', function (e) {
            if (islModalOpen || islModalClosing) return;
            let $row = $(this).closest('tr');
            if (e.type === 'focus' && $row.find('.pinv-item-select').val()) return;

            activeSearchRow = $row;
            let prefill = $.trim($(this).val());
            $('#pinv-isl-filter-name').val(prefill);
            $('#pinv-isl-filter-code').val('');
            $('#pinv-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#pinv-item-search-modal').modal('show');
            $('#pinv-item-search-modal').one('shown.bs.modal', function () {
                $('#pinv-isl-filter-name').focus().select();
                if (prefill) fetchItemList();
            });
        });

        // Disable browser autocomplete dropdown on all number and text inputs in form
        $('#pinv-items-table input, form input').attr('autocomplete', 'off');

        function updateRowNumbers() {
            $('#pinv-items-body tr').each(function (idx) {
                $(this).find('.pinv-sr-no').text(idx + 1);
            });
        }

        function calculateRow($row, source) {
            let qtyStr = $row.find('.pinv-qty').val();
            let costStr = $row.find('.pinv-cost').val();
            let sellStr = $row.find('.pinv-sell').val();
            let mrpStr = $row.find('.pinv-mrp').val();
            let gstStr = $row.find('.pinv-gst').val();

            let qty = parseFloat(qtyStr) || 0;
            let cost = parseFloat(costStr) || 0;
            let sell = parseFloat(sellStr) || 0;
            let mrp = parseFloat(mrpStr) || 0;
            let gst = parseFloat(gstStr) || 0;
            let base = qty * cost;

            // Margin % = [(Selling Price incl. GST ÷ (1 + GST%/100)) − Cost] ÷ [Selling Price incl. GST ÷ (1 + GST%/100)] × 100
            // Profit % = Profit Amount ÷ Cost Price × 100
            let baseSell = sell > 0 ? sell : mrp;
            let sellExclGst = (baseSell > 0) ? (baseSell / (1 + (gst / 100))) : 0;
            let profitAmount = (sellExclGst > 0 && cost > 0) ? (sellExclGst - cost) : null;
            let marginPct = (sellExclGst > 0 && profitAmount !== null) ? ((profitAmount / sellExclGst) * 100) : null;
            let profitPct = (cost > 0 && profitAmount !== null) ? ((profitAmount / cost) * 100) : null;
            $row.find('.pinv-margin').val(marginPct !== null ? marginPct.toFixed(2) + '%' : '');
            $row.find('.pinv-profit').val(profitPct !== null ? profitPct.toFixed(2) + '%' : '');

            // Validation 1: Sell Price must be strictly greater than Cost Price
            let $sellInput = $row.find('.pinv-sell');
            $sellInput.removeClass('border-danger text-danger border-warning text-warning').attr('title', '');
            if (cost > 0 && sell > 0 && sell <= cost) {
                $sellInput.addClass('border-danger text-danger')
                          .attr('title', 'Sell Price (₹' + sell.toFixed(2) + ') must be greater than Cost Price (₹' + cost.toFixed(2) + ')!');
            } else if (mrp > 0 && sell > 0 && sell > mrp) {
                // Validation 2: Sell Price must be <= MRP
                $sellInput.addClass('border-warning text-warning')
                          .attr('title', 'Sell Price (₹' + sell.toFixed(2) + ') must not exceed MRP (₹' + mrp.toFixed(2) + ')!');
            }

            // Real-time inline field validation (Task 11)
            let $qtyInput = $row.find('.pinv-qty');
            let $costInput = $row.find('.pinv-cost');
            let itemId = $row.find('.pinv-item-select').val();
            if (itemId) {
                if (qty <= 0) {
                    $qtyInput.addClass('border-danger text-danger is-invalid').attr('title', 'Quantity must be greater than 0');
                } else {
                    $qtyInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }

                if (cost <= 0) {
                    $costInput.addClass('border-danger text-danger is-invalid').attr('title', 'Cost price must be greater than 0');
                } else {
                    $costInput.removeClass('border-danger text-danger is-invalid').attr('title', '');
                }
            }

            let $discPct = $row.find('.pinv-disc-percent');
            let $discAmt = $row.find('.pinv-disc-amount');

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
                // Qty or Cost changed
                if (discPct > 0 && base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt > 0 ? discAmt.toFixed(2) : '');
                } else if (discAmt > 0 && base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct > 0 ? discPct.toFixed(2) : '');
                }
            }

            calculateTotals();
        }

        function calculateTotals() {
            let schemeDisc = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let otherDisc = parseFloat($('input[name="other_disc_amt"]').val()) || 0;
            let totalHeaderDiscount = schemeDisc + otherDisc;

            // 1. Collect line data & calculate basic cost after item discount
            let rowsData = [];
            let totalBaseAfterItemDisc = 0;

            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let freeQty = parseFloat($r.find('.pinv-free-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.pinv-gst').val()) || 0;

                let base = qty * cost;
                let baseAfterDisc = Math.max(0, base - discAmt);
                totalBaseAfterItemDisc += baseAfterDisc;

                rowsData.push({
                    $row: $r,
                    qty: qty,
                    freeQty: freeQty,
                    cost: cost,
                    base: base,
                    discAmt: discAmt,
                    baseAfterDisc: baseAfterDisc,
                    gst: gst
                });
            });

            // 2. Allocate header discount (Scheme ItemDiscAmt + OtherDiscAmt) on basic cost without GST
            let remainingDiscount = totalHeaderDiscount;
            let totalQty = 0;
            let totalCost = 0;
            let totalDiscAmt = 0;
            let totalGstAmt = 0;
            let totalNetAmt = 0;
            let hasAny = false;

            for (let i = 0; i < rowsData.length; i++) {
                let d = rowsData[i];
                let extraDeduction = 0;

                if (totalBaseAfterItemDisc > 0 && totalHeaderDiscount > 0) {
                    if (i === rowsData.length - 1) {
                        extraDeduction = Math.round(remainingDiscount * 100) / 100;
                    } else {
                        extraDeduction = Math.round(((d.baseAfterDisc / totalBaseAfterItemDisc) * totalHeaderDiscount) * 100) / 100;
                        remainingDiscount -= extraDeduction;
                    }
                }
                extraDeduction = Math.max(0, extraDeduction);

                let taxable = Math.max(0, d.baseAfterDisc - extraDeduction);
                let taxAmt = Math.round((taxable * d.gst / 100) * 100) / 100;
                let net = taxable + taxAmt;

                if (d.base > 0) {
                    d.$row.find('.pinv-gst-amt').val(taxAmt > 0 ? taxAmt.toFixed(2) : '');
                    d.$row.find('.pinv-row-net').text(net > 0 ? net.toFixed(2) : '');
                    hasAny = true;
                } else {
                    d.$row.find('.pinv-gst-amt').val('');
                    d.$row.find('.pinv-row-net').text('');
                }

                totalQty += (d.qty + d.freeQty);
                totalCost += d.base;
                totalDiscAmt += (d.discAmt + extraDeduction); // item disc + proportional header disc
                totalGstAmt += taxAmt;
                totalNetAmt += net;
            }

            // 3. Update footer totals
            if (hasAny) {
                $('#footer-total-qty').text(totalQty > 0 ? totalQty.toFixed(3) : '');
                $('#footer-total-cost').text(totalCost > 0 ? totalCost.toFixed(2) : '');
                $('#footer-total-disc').text(totalDiscAmt > 0 ? totalDiscAmt.toFixed(2) : '');
                $('#footer-total-gst').text(totalGstAmt > 0 ? totalGstAmt.toFixed(2) : '');
                $('#footer-grand-net').text(totalNetAmt > 0 ? totalNetAmt.toFixed(2) : '');
            } else {
                $('#footer-total-qty').text('');
                $('#footer-total-cost').text('');
                $('#footer-total-disc').text('');
                $('#footer-total-gst').text('');
                $('#footer-grand-net').text('');
            }

            // 4. Update Final Amount & check match
            let freight = parseFloat($('input[name="freight"]').val()) || 0;
            let roundOff = parseFloat($('input[name="round_off"]').val()) || 0;
            let tcsAmt = parseFloat($('input[name="tcs_amount"]').val()) || 0;
            let finalTotal = Math.round((totalNetAmt + freight + roundOff + tcsAmt) * 100) / 100;

            $('#display-final-total').text(finalTotal.toFixed(2));
            checkAmountMatch(finalTotal);
        }

        function getLiveFinalTotal() {
            let schemeDisc = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let otherDisc = parseFloat($('input[name="other_disc_amt"]').val()) || 0;
            let totalHeaderDiscount = schemeDisc + otherDisc;

            let rowsData = [];
            let totalBaseAfterItemDisc = 0;

            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.pinv-gst').val()) || 0;

                let base = qty * cost;
                let baseAfterDisc = Math.max(0, base - discAmt);
                totalBaseAfterItemDisc += baseAfterDisc;

                rowsData.push({
                    baseAfterDisc: baseAfterDisc,
                    gst: gst
                });
            });

            let remainingDiscount = totalHeaderDiscount;
            let totalNetAmt = 0;

            for (let i = 0; i < rowsData.length; i++) {
                let d = rowsData[i];
                let extraDeduction = 0;

                if (totalBaseAfterItemDisc > 0 && totalHeaderDiscount > 0) {
                    if (i === rowsData.length - 1) {
                        extraDeduction = Math.round(remainingDiscount * 100) / 100;
                    } else {
                        extraDeduction = Math.round(((d.baseAfterDisc / totalBaseAfterItemDisc) * totalHeaderDiscount) * 100) / 100;
                        remainingDiscount -= extraDeduction;
                    }
                }
                extraDeduction = Math.max(0, extraDeduction);

                let taxable = Math.max(0, d.baseAfterDisc - extraDeduction);
                let taxAmt = Math.round((taxable * d.gst / 100) * 100) / 100;
                totalNetAmt += (taxable + taxAmt);
            }

            let freight = parseFloat($('input[name="freight"]').val()) || 0;
            let roundOff = parseFloat($('input[name="round_off"]').val()) || 0;
            let tcsAmt = parseFloat($('input[name="tcs_amount"]').val()) || 0;

            return Math.round((totalNetAmt + freight + roundOff + tcsAmt) * 100) / 100;
        }

        function checkAmountMatch(finalTotal) {
            if (finalTotal === undefined) {
                finalTotal = getLiveFinalTotal();
            }
            $('#display-final-total').text(finalTotal.toFixed(2));

            let $invAmtInput = $('input[name="supplier_inv_amount"]');
            let invAmtVal = $invAmtInput.val();
            let $statusDiv = $('#supplier-inv-amount-match-status');
            let $badgeDiv = $('#final-amount-match-badge');

            if (!invAmtVal && finalTotal === 0) {
                $statusDiv.html('');
                $badgeDiv.html('');
                $invAmtInput.removeClass('is-valid is-invalid');
                return;
            }

            let invAmt = parseFloat(invAmtVal) || 0;
            let diff = Math.round((invAmt - finalTotal) * 100) / 100;

            if (invAmt > 0 && Math.abs(diff) <= 0.01) {
                $statusDiv.html('<span class="text-success"><i class="fas fa-check-circle"></i> Inv Amount (Supplier) matches Final Amount (₹' + finalTotal.toFixed(2) + ')</span>');
                $badgeDiv.html('<span class="badge badge-success px-3 py-2 font-weight-bold"><i class="fas fa-check-circle"></i> Amounts Matched (₹' + finalTotal.toFixed(2) + ')</span>');
                $invAmtInput.removeClass('is-invalid').addClass('is-valid');
            } else {
                let diffText = (diff > 0 ? '+' : '') + diff.toFixed(2);
                let msg = 'Diff: ₹' + diffText + ' (Supplier Inv: ₹' + invAmt.toFixed(2) + ' vs Final: ₹' + finalTotal.toFixed(2) + ')';
                $statusDiv.html('<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ' + msg + ' — Both amounts must match to save</span>');
                $badgeDiv.html('<span class="badge badge-danger px-3 py-2 font-weight-bold"><i class="fas fa-exclamation-triangle"></i> ' + msg + '</span>');
                if (invAmtVal) {
                    $invAmtInput.removeClass('is-valid').addClass('is-invalid');
                }
            }
        }

        function focusExpDateField($row) {
            if (!$row || !$row.length) return;
            let $exp = $row.find('.pinv-exp-date');
            if ($exp.length) {
                $exp[0].focus();
                setTimeout(function () {
                    $exp[0].focus();
                }, 60);
                setTimeout(function () {
                    $exp[0].focus();
                }, 180);
            }
        }

        function updateExpiryRequirement($row, batchExpiry, shelfLife) {
            let $expInput = $row.find('.pinv-exp-date');
            let $expBadge = $row.find('.pinv-exp-badge');

            if (batchExpiry === undefined || batchExpiry === null) {
                let $sel = $row.find('.pinv-item-select');
                batchExpiry = $sel.attr('data-batch-expiry') || 'Not Required';
                shelfLife = parseInt($sel.attr('data-shelf-life') || 0);
            }

            if (batchExpiry === 'Mandatory' || batchExpiry === 'Days' || batchExpiry === 'Month') {
                $expInput.prop('required', true).addClass('border-danger');
                $expBadge.removeClass('d-none').html('<i class="fas fa-exclamation-circle"></i> ' + (batchExpiry === 'Mandatory' ? 'Required' : batchExpiry));
                $expInput.attr('title', 'Expiry date is mandatory for this item (' + batchExpiry + ')');

                // Auto-fill expiry date from shelf life if date is empty
                if ((batchExpiry === 'Days' || batchExpiry === 'Month') && shelfLife > 0 && !$expInput.val()) {
                    let invDateVal = $('input[name="invoice_date"]').val();
                    let base = invDateVal ? new Date(invDateVal) : new Date();
                    if (!isNaN(base.getTime())) {
                        if (batchExpiry === 'Days') {
                            base.setDate(base.getDate() + shelfLife);
                        } else if (batchExpiry === 'Month') {
                            base.setMonth(base.getMonth() + shelfLife);
                        }
                        let yyyy = base.getFullYear();
                        let mm = String(base.getMonth() + 1).padStart(2, '0');
                        let dd = String(base.getDate()).padStart(2, '0');
                        $expInput.val(`${yyyy}-${mm}-${dd}`);
                    }
                }
            } else {
                // Not Required or Optional: no validation needed!
                $expInput.prop('required', false).removeClass('border-danger');
                $expBadge.addClass('d-none');
                $expInput.attr('title', 'Expiry date (optional)');
            }
        }

        function processPurchaseItemLookup($row, itemId, query) {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let $select = $row.find('.pinv-item-select');
            let $desc = $row.find('.pinv-item-desc');
            let $code = $row.find('.pinv-item-code');

            let params = { branch_id: branchId };
            if (itemId) {
                params.item_id = itemId;
            } else if (query) {
                params.query = query;
            } else {
                return;
            }

            $.getJSON('{{ route("purchase.purchase-invoices.lookup-item") }}', params, function (data) {
                if (data && data.id) {
                    let codeVal = data.item_code || data.ean_upc_code || '';
                    if (codeVal) {
                        $code.val(codeVal);
                    }

                    $select.val(data.id);
                    $select.attr('data-batch-expiry', data.batch_expiry_details || 'Not Required');
                    $select.attr('data-shelf-life', data.shelf_life_days || 0);
                    $desc.val(data.name + (data.item_code ? ' [' + data.item_code + ']' : ''));

                    // Set pricing fields
                    if (data.cost_price > 0) {
                        $row.find('.pinv-cost').val(parseFloat(data.cost_price).toFixed(2));
                    }
                    if (data.sell_price > 0) {
                        $row.find('.pinv-sell').val(parseFloat(data.sell_price).toFixed(2));
                    }
                    if (data.mrp > 0) {
                        $row.find('.pinv-mrp').val(parseFloat(data.mrp).toFixed(2));
                    }
                    if (data.gst_percent >= 0) {
                        $row.find('.pinv-gst').val(parseFloat(data.gst_percent).toFixed(2));
                    }

                    // Update expiry rules
                    updateExpiryRequirement($row, data.batch_expiry_details, data.shelf_life_days);

                    // If expiry is already known (or computed), populate it
                    if (data.exp_date && !$row.find('.pinv-exp-date').val()) {
                        $row.find('.pinv-exp-date').val(data.exp_date);
                    }

                    calculateRow($row, 'base');

                    // Next field focus: Always direct trigger Exp-date
                    focusExpDateField($row);
                } else {
                    $code.addClass('is-invalid');
                    setTimeout(function () { $code.removeClass('is-invalid'); }, 2500);
                }
            });
        }

        // 1. Code Input: When entering code, automatically get Description & all other values
        $(document).on('change blur keydown', '.pinv-item-code', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter') {
                return;
            }
            if (e.type === 'keydown' && e.key === 'Enter') {
                e.preventDefault();
            }

            let $input = $(this);
            let $row = $input.closest('tr');
            let query = $.trim($input.val());
            if (!query) return;

            processPurchaseItemLookup($row, null, query);
        });

        // 2. Item Selection: Auto-populate Code, Cost, Sell, MRP, GST, Margin %, Profit %, and apply Batch/Expiry rule
        $(document).on('change', '.pinv-item-select', function () {
            let $select = $(this);
            let $row = $select.closest('tr');
            let itemId = $select.val();

            if (!itemId) {
                $row.find('.pinv-item-code').val('');
                updateExpiryRequirement($row, 'Not Required', 0);
                return;
            }

            let $opt = $select.find('option:selected');
            let itemCode = $opt.attr('data-code') || $opt.data('code') || $opt.attr('data-ean') || $opt.data('ean') || '';
            if (itemCode) {
                $row.find('.pinv-item-code').val(itemCode);
            }

            let cost = parseFloat($opt.attr('data-cost') !== undefined ? $opt.attr('data-cost') : $opt.data('cost'));
            let sell = parseFloat($opt.attr('data-sell') !== undefined ? $opt.attr('data-sell') : $opt.data('sell'));
            let mrp = parseFloat($opt.attr('data-mrp') !== undefined ? $opt.attr('data-mrp') : $opt.data('mrp'));
            let gst = parseFloat($opt.attr('data-gst') !== undefined ? $opt.attr('data-gst') : $opt.data('gst'));
            let batchExpiry = $opt.attr('data-batch-expiry') || $opt.data('batch-expiry');
            let shelfLife = parseInt($opt.attr('data-shelf-life') || $opt.data('shelf-life') || 0);

            updateExpiryRequirement($row, batchExpiry, shelfLife);

            if (!isNaN(cost) || !isNaN(sell) || !isNaN(mrp) || !isNaN(gst)) {
                $row.find('.pinv-cost').val(!isNaN(cost) && cost > 0 ? cost.toFixed(2) : '');
                $row.find('.pinv-sell').val(!isNaN(sell) && sell > 0 ? sell.toFixed(2) : '');
                $row.find('.pinv-mrp').val(!isNaN(mrp) && mrp > 0 ? mrp.toFixed(2) : '');
                $row.find('.pinv-gst').val(!isNaN(gst) && gst >= 0 ? gst.toFixed(2) : '');

                calculateRow($row);
            } else {
                // Fallback: Fetch from API endpoint if data attributes missing
                $.getJSON('{{ url("purchase/purchase-invoices/item-details") }}/' + itemId, function (data) {
                    if (data) {
                        if (data.item_code || data.ean_upc_code) {
                            $row.find('.pinv-item-code').val(data.item_code || data.ean_upc_code);
                        }
                        updateExpiryRequirement($row, data.batch_expiry_details, data.shelf_life_days);
                        $row.find('.pinv-cost').val(data.cost_price > 0 ? Number(data.cost_price).toFixed(2) : '');
                        $row.find('.pinv-sell').val(data.sell_price > 0 ? Number(data.sell_price).toFixed(2) : '');
                        $row.find('.pinv-mrp').val(data.mrp > 0 ? Number(data.mrp).toFixed(2) : '');
                        $row.find('.pinv-gst').val(Number(data.gst_percent || 0) >= 0 ? Number(data.gst_percent).toFixed(2) : '');

                        calculateRow($row);
                        focusExpDateField($row);
                    }
                });
            }
        });

        // Advance to Qty when Enter is pressed on Exp Date
        $(document).on('keydown', '.pinv-exp-date', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                $(this).closest('tr').find('.pinv-qty').focus().select();
            }
        });

        // 3. Real-time Calculation Listeners
        $(document).on('input', '.pinv-qty, .pinv-cost, .pinv-sell, .pinv-mrp', function () {
            calculateRow($(this).closest('tr'), 'base');
        });

        $(document).on('input', '.pinv-disc-percent', function () {
            calculateRow($(this).closest('tr'), 'percent');
        });

        $(document).on('input', '.pinv-disc-amount', function () {
            calculateRow($(this).closest('tr'), 'amount');
        });

        $(document).on('input change blur', '.pinv-gst, .pinv-free-qty', function () {
            calculateRow($(this).closest('tr'), 'other');
        });

        function getPinvTotalBaseCost() {
            let total = 0;
            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let base = qty * cost;
                total += Math.max(0, base - discAmt);
            });
            return total;
        }

        let schemeSyncing = false;

        $(document).on('input', 'input[name="scheme_item_disc_amt"]', function () {
            if (schemeSyncing) return;
            schemeSyncing = true;
            let amt = parseFloat($(this).val()) || 0;
            let base = getPinvTotalBaseCost();
            if (base > 0 && amt > 0) {
                let pct = (amt / base) * 100;
                $('input[name="scheme_item_disc_percent"]').val(pct.toFixed(2));
            } else if (amt === 0) {
                $('input[name="scheme_item_disc_percent"]').val('');
            }
            schemeSyncing = false;
            calculateTotals();
        });

        $(document).on('input', 'input[name="scheme_item_disc_percent"]', function () {
            if (schemeSyncing) return;
            schemeSyncing = true;
            let pct = parseFloat($(this).val()) || 0;
            let base = getPinvTotalBaseCost();
            if (base > 0 && pct > 0) {
                let amt = (base * pct) / 100;
                $('input[name="scheme_item_disc_amt"]').val(amt.toFixed(2));
            } else if (pct === 0) {
                $('input[name="scheme_item_disc_amt"]').val('');
            }
            schemeSyncing = false;
            calculateTotals();
        });

        function syncSchemePercentFromAmount() {
            if (schemeSyncing) return;
            let amt = parseFloat($('input[name="scheme_item_disc_amt"]').val()) || 0;
            let base = getPinvTotalBaseCost();
            if (base > 0 && amt > 0) {
                let pct = (amt / base) * 100;
                $('input[name="scheme_item_disc_percent"]').val(pct.toFixed(2));
            }
        }

        $(document).on('input change', 'input[name="supplier_inv_amount"], input[name="freight"], input[name="round_off"], input[name="other_disc_amt"], input[name="tcs_amount"]', function () {
            calculateTotals();
        });

        // 4. Form Submit Guard
        $('form').on('submit', function (e) {
            // Rule: Check Sell Price > Cost Price on all item rows
            let priceError = null;
            $('#pinv-items-body tr').each(function (idx) {
                let $r = $(this);
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let sell = parseFloat($r.find('.pinv-sell').val()) || 0;
                let itemName = $r.find('.pinv-item-desc').val() || ('Row #' + (idx + 1));
                if (cost > 0 && sell <= cost) {
                    priceError = {
                        row: idx + 1,
                        item: itemName,
                        cost: cost,
                        sell: sell,
                        $input: $r.find('.pinv-sell')
                    };
                    return false; // break loop
                }
            });

            if (priceError) {
                e.preventDefault();
                alert("Row #" + priceError.row + " (" + priceError.item + "):\nSell Price (₹" + priceError.sell.toFixed(2) + ") must be greater than Cost Price (₹" + priceError.cost.toFixed(2) + ")!");
                priceError.$input.focus().addClass('border-danger text-danger');
                return false;
            }

            // Rule: Sell Price must be <= MRP
            let mrpError = null;
            $('#pinv-items-body tr').each(function (idx) {
                let $r = $(this);
                let sell = parseFloat($r.find('.pinv-sell').val()) || 0;
                let mrp = parseFloat($r.find('.pinv-mrp').val()) || 0;
                let itemName = $r.find('.pinv-item-desc').val() || ('Row #' + (idx + 1));
                if (mrp > 0 && sell > mrp) {
                    mrpError = {
                        row: idx + 1,
                        item: itemName,
                        sell: sell,
                        mrp: mrp,
                        $input: $r.find('.pinv-sell')
                    };
                    return false; // break loop
                }
            });

            if (mrpError) {
                e.preventDefault();
                alert("Row #" + mrpError.row + " (" + mrpError.item + "):\nSell Price (₹" + mrpError.sell.toFixed(2) + ") must not exceed MRP (₹" + mrpError.mrp.toFixed(2) + ")!");
                mrpError.$input.focus().addClass('border-warning text-warning');
                return false;
            }

            let invAmt = parseFloat($('input[name="supplier_inv_amount"]').val()) || 0;
            let finalTotal = getLiveFinalTotal();
            let diff = Math.round((invAmt - finalTotal) * 100) / 100;

            if (invAmt > 0 && Math.abs(diff) > 0.01) {
                e.preventDefault();
                let diffMsg = (diff > 0 ? '+' : '') + diff.toFixed(2);
                alert("Supplier Invoice Amount [₹" + invAmt.toFixed(2) + "] must match the Final Amount [₹" + finalTotal.toFixed(2) + "] before saving!\n\nDifference: ₹" + diffMsg);
                $('input[name="supplier_inv_amount"]').focus().addClass('is-invalid');
                checkAmountMatch();
                return false;
            }

            // Show submit loading indicator
            let $btn = $(this).find('button[type="submit"]');
            if ($btn.length) {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Saving...');
            }
        });

        function initPinvItemSelect2($el) {
            // Item description is now a clean readonly text input
        }

        function addPinvRowAndOpenSearchModal() {
            let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#pinv-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);
            initPinvItemSelect2($newRow.find('.pinv-item-select'));
            $newRow.find('input').attr('autocomplete', 'off');
            updateExpiryRequirement($newRow, 'Not Required', 0);
            rowIndex++;
            updateRowNumbers();
            calculateTotals();

            // Immediately trigger the item search modal for the newly added row
            activeSearchRow = $newRow;
            $('#pinv-isl-filter-name').val('');
            $('#pinv-isl-filter-code').val('');
            $('#pinv-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#pinv-item-search-modal').modal('show');
            $('#pinv-item-search-modal').one('shown.bs.modal', function () {
                $('#pinv-isl-filter-name').focus();
            });
        }

        // On Disc Amount field: Tab or Enter creates new row and opens item search popup; if user cancels without selecting an item, that row is automatically deleted and focus moves to Freight!
        $(document).off('keydown', '.pinv-disc-amount').on('keydown', '.pinv-disc-amount', function (e) {
            if ((e.key === 'Tab' && !e.shiftKey) || e.key === 'Enter') {
                e.preventDefault();
                let $currentRow = $(this).closest('tr');
                let $nextRow = $currentRow.next('tr');
                if ($nextRow.length) {
                    $nextRow.find('.pinv-item-code').focus();
                } else {
                    addPinvRowAndOpenSearchModal();
                }
            }
        });

        // 5. Add Row
        $('#pinv-add-row').on('click', function () {
            let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#pinv-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);

            // Initialize Select2 on the newly added row's dropdown
            initPinvItemSelect2($newRow.find('.pinv-item-select'));

            // Ensure autocomplete is off on new row inputs
            $newRow.find('input').attr('autocomplete', 'off');

            updateExpiryRequirement($newRow, 'Not Required', 0);
            rowIndex++;
            updateRowNumbers();
            calculateTotals();
        });

        // 6. Remove Row
        $('#pinv-items-body').on('click', '.pinv-remove-row', function () {
            let rows = $('#pinv-items-body tr');
            if (rows.length <= 1) return;
            $(this).closest('tr').remove();
            updateRowNumbers();
            calculateTotals();
        });

        // 7. Initial Run on existing rows
        initPinvItemSelect2($('.pinv-item-select'));
        updateRowNumbers();
        $('#pinv-items-body tr').each(function () {
            let $r = $(this);
            calculateRow($r, 'initial');
            updateExpiryRequirement($r);
        });
        syncSchemePercentFromAmount();
        checkAmountMatch();

        // Prevent future dates on invoice_date, grn_date, supplier_inv_date
        $('#invoice_date, #grn_date, #supplier_inv_date').on('change', function () {
            const today = new Date().toISOString().split('T')[0];
            if (this.value && this.value > today) {
                alert('Future date is not allowed for ' + ($(this).closest('.form-group').find('label').text().trim().replace('*', '').trim() || 'Date') + '!');
                this.value = today;
            }
        });

        // Form Submit Guard (Task 11)
        $('form').on('submit', function (e) {
            let supp = $('select[name="supplier_id"]').val();
            let $suppContainer = $('select[name="supplier_id"]').next('.select2-container').find('.select2-selection');
            if (!supp) {
                e.preventDefault();
                $suppContainer.addClass('border-danger');
                alert('Please select a Supplier for this purchase invoice.');
                $('select[name="supplier_id"]').select2('open');
                return false;
            } else {
                $suppContainer.removeClass('border-danger');
            }

            let $suppInvInput = $('#supplier_inv_no');
            let suppInvVal = $.trim($suppInvInput.val());
            if (!suppInvVal) {
                e.preventDefault();
                $suppInvInput.addClass('is-invalid border-danger');
                $('#supplier-inv-feedback').text('Supplier Invoice Number is required before saving.').show();
                $('#supplier-inv-feedback-container').show();
                alert('Supplier Invoice Number is required before saving.');
                $suppInvInput.focus();
                return false;
            }
            if ($suppInvInput.hasClass('is-invalid')) {
                e.preventDefault();
                alert('Please resolve the Supplier Invoice Number error before saving.');
                $suppInvInput.focus();
                return false;
            }

            let hasError = false;
            let validRows = 0;
            $('#pinv-items-body tr').each(function (idx) {
                let id = $(this).find('.pinv-item-select').val();
                let $q = $(this).find('.pinv-qty');
                let q = parseFloat($q.val()) || 0;
                let $cost = $(this).find('.pinv-cost');
                let cost = parseFloat($cost.val()) || 0;

                if (id) {
                    if (q <= 0) {
                        $q.addClass('is-invalid border-danger');
                        alert(`Row #${idx + 1}: Quantity must be greater than 0.`);
                        $q.focus();
                        hasError = true;
                        return false;
                    }
                    if (cost <= 0) {
                        $cost.addClass('is-invalid border-danger');
                        alert(`Row #${idx + 1}: Cost price must be greater than 0.`);
                        $cost.focus();
                        hasError = true;
                        return false;
                    }
                    validRows++;
                }
            });

            if (hasError) {
                e.preventDefault();
                return false;
            }

            if (validRows === 0) {
                e.preventDefault();
                alert('Please add at least one valid item with quantity > 0.');
                return false;
            }
        });

        // Form Reset Button Handler
        $(document).on('click', '.btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset this form? All unsaved inputs will be lost.')) {
                window.location.reload();
            }
        });

        // Global autofocus on the first field of the form
        setTimeout(function () {
            let $first = $('#invoice_date');
            if ($first.length) {
                $first.focus();
            }
        }, 150);
    });
</script>
@endpush
