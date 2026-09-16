@php
    $inv = $purchaseInvoice ?? null;
    $sourceRn = $sourceReceiptNote ?? null;
    $oldItems = old('items');
    $existingItems = !empty($oldItems) ? collect($oldItems) : ($inv?->items ?? ($convertedItems ?? collect()));

    $selectedSupplier = old('supplier_id', $inv->supplier_id ?? ($sourceRn->supplier_id ?? ''));
    $selectedBranch = old('branch_id', $inv->branch_id ?? ($sourceRn->branch_id ?? ''));
    $selectedPo = old('purchase_order_id', $inv->purchase_order_id ?? ($sourceRn->purchase_order_id ?? ''));
    $grnNumberVal = old('grn_number', $inv->grn_number ?? ($sourceRn->receipt_number ?? ''));
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
@endif

<input type="hidden" name="purchase_receipt_note_id" value="{{ $rnIdVal }}">

<x-field name="invoice_date" label="Invoice Date" type="date" :value="optional($inv->invoice_date ?? now())->format('Y-m-d')" required />
<x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$selectedSupplier" placeholder="Select a Supplier" required />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$selectedBranch" placeholder="Select a Branch" required />
<x-select name="purchase_order_id" label="Purchase Order" :options="$purchaseOrders" :selected="$selectedPo" placeholder="Select PO" />
<x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$inv->purchase_type ?? 'Local'" required />
<x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$inv->c_form ?? 'No Forms'" required />
<x-field name="grn_number" label="GRN Number" :value="$grnNumberVal" />
<x-field name="grn_date" label="GRN Date" type="date" :value="$grnDateVal" />
<x-field name="supplier_inv_no" label="Inv No (Supplier)" :value="$inv->supplier_inv_no ?? ''" />
<x-field name="supplier_inv_date" label="Inv Date (Supplier)" type="date" :value="optional($inv->supplier_inv_date ?? now())->format('Y-m-d')" />
<x-field name="supplier_inv_amount" label="Inv Amount (Supplier)" type="number" step="0.01" :value="isset($inv->supplier_inv_amount) && $inv->supplier_inv_amount != 0 ? $inv->supplier_inv_amount : ''" required />
<div class="form-group row mt-n2 mb-2" id="supplier-inv-amount-match-container">
    <div class="col-sm-3"></div>
    <div class="col-sm-6">
        <div id="supplier-inv-amount-match-status" class="small font-weight-bold"></div>
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
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="isset($inv->round_off) && $inv->round_off != 0 ? $inv->round_off : ''" />
<x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="isset($inv->scheme_item_disc_amt) && $inv->scheme_item_disc_amt != 0 ? $inv->scheme_item_disc_amt : ''" />
<x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="isset($inv->other_disc_amt) && $inv->other_disc_amt != 0 ? $inv->other_disc_amt : ''" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="isset($inv->total_extra_cess) && $inv->total_extra_cess != 0 ? $inv->total_extra_cess : ''" />
<x-field name="tcs_amount" label="TCS Amt" type="number" step="0.01" :value="isset($inv->tcs_amount) && $inv->tcs_amount != 0 ? $inv->tcs_amount : ''" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="isset($inv->total_weight) && $inv->total_weight != 0 ? $inv->total_weight : ''" />
<x-textarea name="remarks" label="Remarks" :value="$inv->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$inv->message ?? ''" />

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
        let islDebounce = null;
        let islCache = {};
        let islLastKey = null;
        let islModalOpen = false;
        const ISL_URL = '{{ route("purchase.purchase-invoices.item-list") }}';

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

        // Clicking a row or its Select button picks the item
        $(document).on('click', '.pinv-isl-item-row, .pinv-isl-btn-select', function (e) {
            e.stopPropagation();
            let $row = $(this).hasClass('pinv-isl-item-row') ? $(this) : $(this).closest('tr');
            let itemId   = $row.data('id');
            let itemCode = $row.data('code');

            $('#pinv-item-search-modal').modal('hide');

            if (! activeSearchRow || ! itemId) return;

            activeSearchRow.find('.pinv-item-code').val(itemCode || itemId);
            processPurchaseItemLookup(activeSearchRow, itemId);
            activeSearchRow = null;
        });

        // Modal open/close guards
        $('#pinv-item-search-modal').on('show.bs.modal', function() { islModalOpen = true; });
        $('#pinv-item-search-modal').on('hidden.bs.modal', function() {
            islModalOpen = false;
            setTimeout(function() { islModalOpen = false; }, 300);
        });

        // Open modal on Code/Barcode field focus
        $(document).off('focus', '.pinv-item-code').on('focus', '.pinv-item-code', function () {
            if (islModalOpen) return;
            activeSearchRow = $(this).closest('tr');
            let prefill = $.trim($(this).val());
            $('#pinv-isl-filter-name').val(prefill);
            $('#pinv-isl-filter-code').val('');
            $('#pinv-isl-filter-expiry').val('');
            fetchItemList();
            islModalOpen = true;
            $('#pinv-item-search-modal').modal('show');
            $('#pinv-item-search-modal').one('shown.bs.modal', function () {
                $('#pinv-isl-filter-name').focus();
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
                          .attr('title', 'Sell Price (₹' + sell.toFixed(2) + ') Cost Price (₹' + cost.toFixed(2) + ') se zyada honi chahiye!');
            } else if (mrp > 0 && sell > 0 && sell > mrp) {
                // Validation 2: Sell Price must be <= MRP
                $sellInput.addClass('border-warning text-warning')
                          .attr('title', 'Sell Price (₹' + sell.toFixed(2) + ') MRP (₹' + mrp.toFixed(2) + ') se zyada nahi hona chahiye!');
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
                $statusDiv.html('<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ' + msg + ' — Dono same hona chahiye to hi save hoga</span>');
                $badgeDiv.html('<span class="badge badge-danger px-3 py-2 font-weight-bold"><i class="fas fa-exclamation-triangle"></i> ' + msg + '</span>');
                if (invAmtVal) {
                    $invAmtInput.removeClass('is-valid').addClass('is-invalid');
                }
            }
        }

        function updateExpiryRequirement($row, batchExpiry, shelfLife) {
            let $expInput = $row.find('.pinv-exp-date');
            let $expBadge = $row.find('.pinv-exp-badge');

            if (batchExpiry === undefined || batchExpiry === null) {
                let $opt = $row.find('.pinv-item-select option:selected');
                batchExpiry = $opt.data('batch-expiry') || 'Not Required';
                shelfLife = parseInt($opt.data('shelf-life') || 0);
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
                // Not Required or Optional: validation nahi lagega!
                $expInput.prop('required', false).removeClass('border-danger');
                $expBadge.addClass('d-none');
                $expInput.attr('title', 'Expiry date (optional)');
            }
        }

        function processPurchaseItemLookup($row, itemId, query) {
            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
            let $select = $row.find('.pinv-item-select');
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

                    if ($select.find(`option[value="${data.id}"]`).length === 0) {
                        let opt = new Option(data.name + (data.item_code ? ' [' + data.item_code + ']' : ''), data.id, true, true);
                        let $optEl = $(opt);
                        $optEl.attr('data-code', data.item_code || '').data('code', data.item_code || '');
                        $optEl.attr('data-ean', data.ean_upc_code || '').data('ean', data.ean_upc_code || '');
                        $optEl.attr('data-cost', data.cost_price || 0).data('cost', data.cost_price || 0);
                        $optEl.attr('data-sell', data.sell_price || 0).data('sell', data.sell_price || 0);
                        $optEl.attr('data-mrp', data.mrp || 0).data('mrp', data.mrp || 0);
                        $optEl.attr('data-gst', data.gst_percent || 0).data('gst', data.gst_percent || 0);
                        $optEl.attr('data-batch-expiry', data.batch_expiry_details || 'Not Required').data('batch-expiry', data.batch_expiry_details || 'Not Required');
                        $optEl.attr('data-shelf-life', data.shelf_life_days || '').data('shelf-life', data.shelf_life_days || '');
                        $select.append(opt);
                    }
                    $select.val(data.id).trigger('change.select2');

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

                    // Next field focus
                    let isExpRequired = ['Mandatory', 'Days', 'Month'].includes(data.batch_expiry_details);
                    if (isExpRequired && !$row.find('.pinv-exp-date').val()) {
                        $row.find('.pinv-exp-date').focus();
                    } else {
                        $row.find('.pinv-qty').focus();
                    }
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

            let $select = $row.find('.pinv-item-select');
            let currentSelected = $select.find('option:selected');
            let currentCode = currentSelected.data('code');
            let currentEan = currentSelected.data('ean');

            if ((currentCode && String(currentCode).toLowerCase() === query.toLowerCase()) ||
                (currentEan && String(currentEan).toLowerCase() === query.toLowerCase())) {
                return;
            }

            let matchedId = null;
            $select.find('option').each(function () {
                let optCode = $(this).data('code');
                let optEan = $(this).data('ean');
                if ((optCode && String(optCode).toLowerCase() === query.toLowerCase()) ||
                    (optEan && String(optEan).toLowerCase() === query.toLowerCase())) {
                    matchedId = $(this).val();
                    return false;
                }
            });

            if (matchedId) {
                $select.val(matchedId).trigger('change');
            } else {
                processPurchaseItemLookup($row, null, query);
            }
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
                    }
                });
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

        $(document).on('input change', 'input[name="supplier_inv_amount"], input[name="freight"], input[name="round_off"], input[name="scheme_item_disc_amt"], input[name="other_disc_amt"], input[name="tcs_amount"]', function () {
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
                let itemName = $r.find('.pinv-item-select option:selected').text().trim() || ('Row #' + (idx + 1));
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
                alert("Row #" + priceError.row + " (" + priceError.item + "):\nSell Price (\u20b9" + priceError.sell.toFixed(2) + ") Cost Price (\u20b9" + priceError.cost.toFixed(2) + ") se zyada hona chahiye!");
                priceError.$input.focus().addClass('border-danger text-danger');
                return false;
            }

            // Rule: Sell Price must be <= MRP
            let mrpError = null;
            $('#pinv-items-body tr').each(function (idx) {
                let $r = $(this);
                let sell = parseFloat($r.find('.pinv-sell').val()) || 0;
                let mrp = parseFloat($r.find('.pinv-mrp').val()) || 0;
                let itemName = $r.find('.pinv-item-select option:selected').text().trim() || ('Row #' + (idx + 1));
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
                alert("Row #" + mrpError.row + " (" + mrpError.item + "):\nSell Price (₹" + mrpError.sell.toFixed(2) + ") MRP (₹" + mrpError.mrp.toFixed(2) + ") se zyada nahi hona chahiye!");
                mrpError.$input.focus().addClass('border-warning text-warning');
                return false;
            }

            let invAmt = parseFloat($('input[name="supplier_inv_amount"]').val()) || 0;
            let finalTotal = getLiveFinalTotal();
            let diff = Math.round((invAmt - finalTotal) * 100) / 100;

            if (invAmt > 0 && Math.abs(diff) > 0.01) {
                e.preventDefault();
                let diffMsg = (diff > 0 ? '+' : '') + diff.toFixed(2);
                alert("Inv Amount (Supplier) [₹" + invAmt.toFixed(2) + "] and Final Amount [₹" + finalTotal.toFixed(2) + "] same ho to hi save hoga!\n\nDifference: ₹" + diffMsg);
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
            $el.each(function () {
                let $s = $(this);
                $s.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: 'Select item',
                    allowClear: true,
                    ajax: {
                        url: '{{ route("purchase.purchase-invoices.item-list") }}',
                        dataType: 'json',
                        delay: 250,
                        data: function (params) {
                            let branchId = $('select[name="branch_id"]').val() || localStorage.getItem('urbanpos_active_branch_id') || 3;
                            return {
                                search: params.term || '',
                                branch_id: branchId
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: (data.items || []).map(function (it) {
                                    return {
                                        id: it.id,
                                        text: it.name + (it.code ? ' [' + it.code + ']' : ''),
                                        item: it
                                    };
                                })
                            };
                        },
                        cache: true
                    }
                }).on('select2:select', function (e) {
                    let it = e.params?.data?.item;
                    if (it) {
                        let $opt = $(this).find('option:selected');
                        $opt.attr('data-code', it.code || '').data('code', it.code || '');
                        $opt.attr('data-cost', it.cost_price || 0).data('cost', it.cost_price || 0);
                        $opt.attr('data-sell', it.sell_price || 0).data('sell', it.sell_price || 0);
                        $opt.attr('data-mrp', it.mrp || 0).data('mrp', it.mrp || 0);
                        $opt.attr('data-gst', it.gst_percent || 0).data('gst', it.gst_percent || 0);
                        $opt.attr('data-batch-expiry', it.batch_expiry_details || 'Not Required').data('batch-expiry', it.batch_expiry_details || 'Not Required');
                        $opt.attr('data-shelf-life', it.shelf_life_days || '').data('shelf-life', it.shelf_life_days || '');
                    }
                });
            });
        }

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
        checkAmountMatch();

        // Form Reset Button Handler
        $(document).on('click', '.btn-reset-form', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset this form? All unsaved inputs will be lost.')) {
                window.location.reload();
            }
        });
    });
</script>
@endpush
