@php
    $inv = $purchaseInvoice ?? null;
    $existingItems = $inv?->items ?? collect();
@endphp

<x-field name="invoice_date" label="Invoice Date" type="date" :value="optional($inv->invoice_date ?? now())->format('Y-m-d')" required />
<x-select name="supplier_id" label="Supplier" :options="$suppliers" :selected="$inv->supplier_id ?? ''" placeholder="Select a Supplier" required />
<x-select name="branch_id" label="Branch" :options="$branches" :selected="$inv->branch_id ?? ''" placeholder="Select a Branch" required />
<x-select name="purchase_order_id" label="Purchase Order" :options="$purchaseOrders" :selected="$inv->purchase_order_id ?? ''" placeholder="Select PO" />
<x-select name="purchase_type" label="Purchase Type" :options="['Local' => 'Local', 'Interstate' => 'Interstate']" :selected="$inv->purchase_type ?? 'Local'" required />
<x-select name="c_form" label="C-Form" :options="['Against C-Form' => 'Against C-Form', 'No Forms' => 'No Forms']" :selected="$inv->c_form ?? 'No Forms'" required />
<x-field name="grn_number" label="GRN Number" :value="$inv->grn_number ?? ''" />
<x-field name="grn_date" label="GRN Date" type="date" :value="optional($inv->grn_date ?? null)->format('Y-m-d')" />
<x-field name="supplier_inv_no" label="Inv No (Supplier)" :value="$inv->supplier_inv_no ?? ''" />
<x-field name="supplier_inv_date" label="Inv Date (Supplier)" type="date" :value="optional($inv->supplier_inv_date ?? null)->format('Y-m-d')" />
<x-field name="supplier_inv_amount" label="Inv Amount (Supplier)" type="number" step="0.01" :value="$inv->supplier_inv_amount ?? ''" />

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
                <th style="width: 85px;" title="Margin % = ((Sell - Cost) / Sell) * 100">Margin %</th>
                <th style="width: 85px;" title="Profit % = ((Sell - Cost) / Cost) * 100">Profit %</th>
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
                <td class="text-right align-middle text-primary font-weight-bold" id="footer-total-qty">0.000</td>
                <td class="align-middle"></td>
                <td class="text-right align-middle font-weight-bold" id="footer-total-cost">0.00</td>
                <td colspan="4" class="text-right align-middle">Total Discount:</td>
                <td colspan="2" class="text-right align-middle text-danger font-weight-bold" id="footer-total-disc">0.00</td>
                <td class="text-right align-middle small text-muted">GST:</td>
                <td class="text-right align-middle font-weight-bold text-dark" id="footer-total-gst">0.00</td>
                <td class="text-right align-middle text-success font-weight-bold" id="footer-grand-net">0.00</td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<button type="button" id="pinv-add-row" class="btn btn-link btn-sm"><i class="fas fa-plus-circle"></i> Add Row</button>

<hr>
<h5 class="mb-3">Totals</h5>
<x-field name="freight" label="Freight" type="number" step="0.01" :value="$inv->freight ?? 0" />
<x-field name="round_off" label="Round off Amount" type="number" step="0.01" :value="$inv->round_off ?? 0" />
<x-field name="scheme_item_disc_amt" label="Scheme ItemDiscAmt" type="number" step="0.01" :value="$inv->scheme_item_disc_amt ?? 0" />
<x-field name="other_disc_amt" label="OtherDiscAmt" type="number" step="0.01" :value="$inv->other_disc_amt ?? 0" />
<x-field name="total_extra_cess" label="Total Extra Cess" type="number" step="0.01" :value="$inv->total_extra_cess ?? 0" />
<x-field name="tcs_amount" label="TCS Amt" type="number" step="0.01" :value="$inv->tcs_amount ?? 0" />
<x-field name="total_weight" label="Total Weight" type="number" step="0.01" :value="$inv->total_weight ?? 0" />
<x-textarea name="remarks" label="Remarks" :value="$inv->remarks ?? ''" />
<x-textarea name="message" label="Message" :value="$inv->message ?? ''" />

<template id="pinv-row-template">
    @include('purchase.purchase-invoices._item-row', ['items' => $items, 'index' => '__INDEX__', 'line' => null])
</template>

@push('js')
<script>
    $(document).ready(function () {
        let rowIndex = {{ $existingItems->count() ?: 1 }};

        function updateRowNumbers() {
            $('#pinv-items-body tr').each(function (idx) {
                $(this).find('.pinv-sr-no').text(idx + 1);
            });
        }

        function calculateRow($row, source) {
            let qty = parseFloat($row.find('.pinv-qty').val()) || 0;
            let cost = parseFloat($row.find('.pinv-cost').val()) || 0;
            let sell = parseFloat($row.find('.pinv-sell').val()) || 0;
            let mrp = parseFloat($row.find('.pinv-mrp').val()) || 0;
            let base = qty * cost;

            // Compute Margin % and Profit %
            let baseSell = sell > 0 ? sell : mrp;
            let marginPct = (baseSell > 0 && cost > 0) ? ((baseSell - cost) / baseSell) * 100 : 0;
            let profitPct = (cost > 0 && baseSell > 0) ? ((baseSell - cost) / cost) * 100 : 0;
            $row.find('.pinv-margin').val(marginPct !== 0 ? marginPct.toFixed(2) + '%' : '0.00%');
            $row.find('.pinv-profit').val(profitPct !== 0 ? profitPct.toFixed(2) + '%' : '0.00%');

            let $discPct = $row.find('.pinv-disc-percent');
            let $discAmt = $row.find('.pinv-disc-amount');
            let gst = parseFloat($row.find('.pinv-gst').val()) || 0;

            let discPct = parseFloat($discPct.val()) || 0;
            let discAmt = parseFloat($discAmt.val()) || 0;

            if (source === 'percent') {
                if (base > 0 && discPct > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt.toFixed(2));
                } else if (discPct === 0) {
                    discAmt = 0;
                    $discAmt.val('0.00');
                }
            } else if (source === 'amount') {
                if (base > 0 && discAmt > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct.toFixed(2));
                } else if (discAmt === 0) {
                    discPct = 0;
                    $discPct.val('0.00');
                }
            } else {
                // Qty or Cost changed
                if (discPct > 0 && base > 0) {
                    discAmt = Math.round((base * discPct / 100) * 100) / 100;
                    $discAmt.val(discAmt.toFixed(2));
                } else if (discAmt > 0 && base > 0) {
                    discPct = Math.round(((discAmt / base) * 100) * 100) / 100;
                    $discPct.val(discPct.toFixed(2));
                }
            }

            let taxable = Math.max(0, base - discAmt);
            let taxAmt = Math.round((taxable * gst / 100) * 100) / 100;
            let net = taxable + taxAmt;

            $row.find('.pinv-gst-amt').val(taxAmt.toFixed(2));
            $row.find('.pinv-row-net').text(net.toFixed(2));
            calculateTotals();
        }

        function calculateTotals() {
            let totalQty = 0;
            let totalCost = 0;
            let totalDiscAmt = 0;
            let totalGstAmt = 0;
            let totalNetAmt = 0;

            $('#pinv-items-body tr').each(function () {
                let $r = $(this);
                let qty = parseFloat($r.find('.pinv-qty').val()) || 0;
                let freeQty = parseFloat($r.find('.pinv-free-qty').val()) || 0;
                let cost = parseFloat($r.find('.pinv-cost').val()) || 0;
                let discAmt = parseFloat($r.find('.pinv-disc-amount').val()) || 0;
                let gst = parseFloat($r.find('.pinv-gst').val()) || 0;

                let base = qty * cost;
                let taxable = Math.max(0, base - discAmt);
                let gstAmt = Math.round((taxable * gst / 100) * 100) / 100;
                let net = taxable + gstAmt;

                totalQty += (qty + freeQty);
                totalCost += base;
                totalDiscAmt += discAmt;
                totalGstAmt += gstAmt;
                totalNetAmt += net;
            });

            $('#footer-total-qty').text(totalQty.toFixed(3));
            $('#footer-total-cost').text(totalCost.toFixed(2));
            $('#footer-total-disc').text(totalDiscAmt.toFixed(2));
            $('#footer-total-gst').text(totalGstAmt.toFixed(2));
            $('#footer-grand-net').text(totalNetAmt.toFixed(2));
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
                // Lookup via AJAX
                $.getJSON('{{ route("purchase.purchase-invoices.lookup-item") }}', { query: query }, function (data) {
                    if (data && data.id) {
                        if ($select.find(`option[value="${data.id}"]`).length === 0) {
                            let opt = new Option(data.name + (data.item_code ? ' [' + data.item_code + ']' : ''), data.id, true, true);
                            $(opt).attr('data-code', data.item_code || '');
                            $(opt).attr('data-ean', data.ean_upc_code || '');
                            $(opt).attr('data-cost', data.cost_price || 0);
                            $(opt).attr('data-sell', data.sell_price || 0);
                            $(opt).attr('data-mrp', data.mrp || 0);
                            $(opt).attr('data-gst', data.gst_percent || 0);
                            $(opt).attr('data-batch-expiry', data.batch_expiry_details || 'Not Required');
                            $(opt).attr('data-shelf-life', data.shelf_life_days || '');
                            $select.append(opt);
                        }
                        $select.val(data.id).trigger('change');
                    } else {
                        $input.addClass('is-invalid');
                        setTimeout(function () { $input.removeClass('is-invalid'); }, 2500);
                    }
                });
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
            let itemCode = $opt.data('code') || $opt.data('ean') || '';
            if (itemCode) {
                $row.find('.pinv-item-code').val(itemCode);
            }

            let cost = parseFloat($opt.data('cost'));
            let sell = parseFloat($opt.data('sell'));
            let mrp = parseFloat($opt.data('mrp'));
            let gst = parseFloat($opt.data('gst'));
            let batchExpiry = $opt.data('batch-expiry');
            let shelfLife = parseInt($opt.data('shelf-life') || 0);

            updateExpiryRequirement($row, batchExpiry, shelfLife);

            if (!isNaN(cost) || !isNaN(sell) || !isNaN(mrp) || !isNaN(gst)) {
                $row.find('.pinv-cost').val(!isNaN(cost) && cost > 0 ? cost.toFixed(2) : '');
                $row.find('.pinv-sell').val(!isNaN(sell) && sell > 0 ? sell.toFixed(2) : '');
                $row.find('.pinv-mrp').val(!isNaN(mrp) && mrp > 0 ? mrp.toFixed(2) : '');
                $row.find('.pinv-gst').val(!isNaN(gst) ? gst.toFixed(2) : '0.00');

                if (!$row.find('.pinv-qty').val()) {
                    $row.find('.pinv-qty').val('1');
                }

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
                        $row.find('.pinv-gst').val(Number(data.gst_percent || 0).toFixed(2));

                        if (!$row.find('.pinv-qty').val()) {
                            $row.find('.pinv-qty').val('1');
                        }

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

        $(document).on('input', '.pinv-gst, .pinv-free-qty', function () {
            calculateRow($(this).closest('tr'), 'other');
        });

        // 4. Add Row
        $('#pinv-add-row').on('click', function () {
            let html = $('#pinv-row-template').html().replaceAll('__INDEX__', rowIndex);
            let $tbody = $('#pinv-items-body');
            let $newRow = $(html);

            $tbody.append($newRow);

            // Initialize Select2 on the newly added row's dropdown
            $newRow.find('select.select2').select2({
                theme: 'bootstrap4',
                width: '100%',
                placeholder: 'Select item',
                allowClear: true
            });

            updateExpiryRequirement($newRow, 'Not Required', 0);
            rowIndex++;
            updateRowNumbers();
            calculateTotals();
        });

        // 5. Remove Row
        $('#pinv-items-body').on('click', '.pinv-remove-row', function () {
            let rows = $('#pinv-items-body tr');
            if (rows.length <= 1) return;
            $(this).closest('tr').remove();
            updateRowNumbers();
            calculateTotals();
        });

        // 6. Initial Run on existing rows
        updateRowNumbers();
        $('#pinv-items-body tr').each(function () {
            let $r = $(this);
            calculateRow($r, 'initial');
            updateExpiryRequirement($r);
        });
    });
</script>
@endpush
