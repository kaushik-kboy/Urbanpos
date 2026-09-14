@php
    $bill = $salesBill ?? null;
    $existingItems = $bill?->items ?? collect();
@endphp

<h5 class="mb-3"><i class="fas fa-file-invoice mr-1 text-primary"></i> Bill Header</h5>
<x-select name="customer_id" label="Customer" :options="$customers" :selected="$bill->customer_id ?? ''" placeholder="Select a customer" required />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$bill->branch_id ?? ''" placeholder="Select a branch" required />
<x-field name="bill_date" label="Bill Date" type="date" :value="optional($bill->bill_date ?? now())->format('Y-m-d')" required />
<x-select name="invoice_type" label="Invoice Type" :options="['Retail Invoice' => 'Retail Invoice', 'Tax Invoice' => 'Tax Invoice', 'Exempted' => 'Exempted']" :selected="$bill->invoice_type ?? 'Retail Invoice'" required />
<x-select name="delivery_type" label="Delivery Type" :options="['Delivered' => 'Delivered', 'Home Delivery' => 'Home Delivery', 'Pickup' => 'Pickup']" :selected="$bill->delivery_type ?? 'Delivered'" required />
<x-field name="delivery_time" label="Delivery Time" type="time" :value="$bill->delivery_time ?? ''" />
<x-select name="sales_type" label="Sales Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$bill->sales_type ?? 'Local'" required />
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
                        <thead class="bg-light">
                            <tr>
                                <th>#</th>
                                <th>Expiry Date</th>
                                <th class="text-right">Available Qty</th>
                                <th class="text-right">Selling Price</th>
                                <th class="text-right">MRP</th>
                                <th class="text-center" style="width: 120px;">Action</th>
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

@push('js')
<script>
    $(document).ready(function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};
        let activeModalRow = null;

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

            // Stock warning border if selling more than available stock
            let $qtyInput = $row.find('.sb-qty');
            if (stock > 0 && qty > stock) {
                $qtyInput.addClass('border-danger text-danger').attr('title', 'Quantity (' + qty + ') exceeds available stock (' + stock + ')!');
            } else {
                $qtyInput.removeClass('border-danger text-danger').attr('title', '');
            }

            calculateTotals();
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
        }

        // Open Batch Selection Modal
        function showBatchModal($row, item, batches) {
            activeModalRow = $row;
            $('#modal-item-title').text(item.name || 'Item');
            $('#modal-item-code').text(item.item_code || item.ean_upc_code || '—');

            let $tbody = $('#modal-batches-body');
            $tbody.empty();

            batches.forEach(function (b, idx) {
                let expDisplay = b.exp_date || 'No Expiry';
                let qtyDisplay = b.qty ? parseFloat(b.qty).toFixed(3) : '0.000';
                let sellDisplay = b.sell_price ? '₹' + parseFloat(b.sell_price).toFixed(2) : '—';
                let mrpDisplay = b.mrp ? '₹' + parseFloat(b.mrp).toFixed(2) : '—';

                let tr = `
                    <tr>
                        <td class="align-middle text-center font-weight-bold">${idx + 1}</td>
                        <td class="align-middle font-weight-bold text-primary"><i class="far fa-calendar-alt mr-1"></i> ${expDisplay}</td>
                        <td class="align-middle text-right font-weight-bold">${qtyDisplay}</td>
                        <td class="align-middle text-right">${sellDisplay}</td>
                        <td class="align-middle text-right">${mrpDisplay}</td>
                        <td class="align-middle text-center">
                            <button type="button" class="btn btn-success btn-xs px-2 btn-apply-batch" 
                                data-exp="${b.exp_date || ''}" 
                                data-sell="${b.sell_price || ''}" 
                                data-mrp="${b.mrp || ''}">
                                <i class="fas fa-check mr-1"></i> Select
                            </button>
                        </td>
                    </tr>
                `;
                $tbody.append(tr);
            });

            $('#sb-batch-modal').modal('show');
        }

        // When user selects a batch from modal
        $(document).on('click', '.btn-apply-batch', function () {
            if (!activeModalRow) return;
            let exp = $(this).data('exp') || '';
            let sell = $(this).data('sell') || '';
            let mrp = $(this).data('mrp') || '';

            if (exp) activeModalRow.find('.sb-exp-date').val(exp);
            if (sell && parseFloat(sell) > 0) activeModalRow.find('.sb-sell-price').val(parseFloat(sell).toFixed(2));
            if (mrp && parseFloat(mrp) > 0) activeModalRow.find('.sb-mrp').val(parseFloat(mrp).toFixed(2));

            $('#sb-batch-modal').modal('hide');
            calculateRow(activeModalRow, 'base');
            activeModalRow.find('.sb-qty').focus();
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

                    // Sync Select2 Description
                    if ($select.find(`option[value="${item.id}"]`).length === 0) {
                        let opt = new Option(item.name + (item.item_code ? ' [' + item.item_code + ']' : ''), item.id, true, true);
                        $select.append(opt);
                    }
                    $select.val(item.id).trigger('change.select2');
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
                    if (item.gst_percent > 0) {
                        $gst.val(parseFloat(item.gst_percent).toFixed(2));
                    }

                    // =========================================================
                    // BATCH / EXPIRY SELECTION LOGIC:
                    // Single Expiry: Auto-fill expiry date!
                    // Multiple Batches: Show modal with productname, code, sell price, qty and expiry!
                    // =========================================================
                    if (batches.length === 1) {
                        // Agar single ho to expiry date automatic aani chahiye
                        let singleBatch = batches[0];
                        if (singleBatch.exp_date) {
                            $exp.val(singleBatch.exp_date);
                        }
                        if (singleBatch.sell_price > 0) {
                            $sell.val(parseFloat(singleBatch.sell_price).toFixed(2));
                        }
                        if (singleBatch.mrp > 0) {
                            $mrp.val(parseFloat(singleBatch.mrp).toFixed(2));
                        }
                        $batchWrap.addClass('d-none');
                        $row.find('.sb-qty').focus();
                    } else if (batches.length > 1) {
                        // Multiple batches exist: show button and pop up selection modal!
                        $batchWrap.removeClass('d-none');
                        showBatchModal($row, item, batches);
                    } else {
                        $batchWrap.addClass('d-none');
                        $row.find('.sb-qty').focus();
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

        $(document).on('input', '.sb-gst-percent', function () {
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

            $newRow.find('select.select2').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Select item',
                allowClear: true
            });

            $newRow.find('input').attr('autocomplete', 'off');
            rowIndex++;
            updateRowNumbers();
            calculateTotals();
            $newRow.find('.sb-item-code').focus();
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
    });
</script>
@endpush
