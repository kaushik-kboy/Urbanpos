@php
    if (is_array($line)) {
        $line = (object) $line;
    }
    $selectedItemId = $line->item_id ?? null;
    $selectedItem = null;
    if ($selectedItemId && isset($items)) {
        $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
            ? collect($items)->firstWhere('id', $selectedItemId)
            : null;
    }
    $itemCodeVal = $line->code ?? ($selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? ''));
    $qtyVal = isset($line->qty) && $line->qty != 0 ? ((float)$line->qty == (int)$line->qty ? (int)$line->qty : $line->qty) : '';
    $sellPriceVal = isset($line->sell_price) && $line->sell_price != 0 ? $line->sell_price : ($selectedItem?->sell_price > 0 ? $selectedItem->sell_price : '');
    $mrpPriceVal = isset($line->mrp) && $line->mrp != 0 ? $line->mrp : ($selectedItem?->mrp > 0 ? $selectedItem->mrp : '');
    $discPercentVal = isset($line->disc_percent) && $line->disc_percent != 0 ? ((float)$line->disc_percent == (int)$line->disc_percent ? (int)$line->disc_percent : $line->disc_percent) : '';
    $discAmountVal = isset($line->disc_amount) && $line->disc_amount != 0 ? $line->disc_amount : '';
    $rawGst = isset($line->gst_percent) && $line->gst_percent != 0 ? $line->gst_percent : ($selectedItem?->gstTax?->percentage > 0 ? $selectedItem->gstTax->percentage : '');
    $gstPercentVal = $rawGst !== '' ? ((float)$rawGst == (int)$rawGst ? (int)$rawGst : $rawGst) : '';
    $gstTaxAmtVal = isset($line->gst_tax_amount) && $line->gst_tax_amount != 0 ? number_format($line->gst_tax_amount, 2) : '';
    $netAmtVal = isset($line->net_amount) && $line->net_amount != 0 ? number_format($line->net_amount, 2) : '';
    $expDateVal = '';
    if (!empty($line->exp_date)) {
        $expDateVal = is_string($line->exp_date) ? $line->exp_date : optional($line->exp_date)->format('Y-m-d');
    }
@endphp
<tr data-stock="{{ $line->stock ?? 0 }}" data-allow-negative-stock="{{ !empty($selectedItem?->allow_negative_stock) ? '1' : '0' }}">
    <td class="text-center align-middle font-weight-bold sb-sr-no">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="min-width: 110px;">
        <input type="text" class="form-control form-control-sm sb-item-code font-weight-bold" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code / Barcode" title="Enter item code or barcode">
    </td>
    <td style="min-width: 220px;">
        <input type="text"
               class="form-control form-control-sm sb-item-desc bg-light font-weight-bold text-truncate"
               readonly
               tabindex="-1"
               value="{{ $selectedItem ? $selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '') : '' }}"
               placeholder="Product Description"
               title="Product description (auto-filled on code entry)">
        <input type="hidden"
               name="items[{{ $index }}][item_id]"
               class="sb-item-select"
               value="{{ $selectedItemId }}"
               required>
        <input type="hidden" class="sb-item-stock-val" value="{{ $line->stock ?? 0 }}">
    </td>
    <td style="width: 135px;">
        <div class="input-group input-group-sm">
            <input type="text"
                   name="items[{{ $index }}][exp_date]"
                   value="{{ $expDateVal }}"
                   readonly
                   tabindex="-1"
                   class="form-control form-control-sm sb-exp-date bg-light"
                   placeholder="YYYY-MM-DD"
                   autocomplete="off"
                   title="Expiry date (Read-only)">
            <div class="input-group-append sb-batch-btn-wrap d-none">
                <button type="button" tabindex="-1" class="btn btn-warning btn-xs sb-btn-choose-batch" title="Multiple batches available! Click to choose batch">
                    <i class="fas fa-layer-group"></i>
                </button>
            </div>
        </div>
    </td>
    <td style="width: 85px;">
        <input type="number" step="any" name="items[{{ $index }}][qty]" value="{{ $qtyVal }}" class="form-control form-control-sm sb-qty font-weight-bold text-right" required autocomplete="off" placeholder="Qty">
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $sellPriceVal }}" readonly tabindex="-1" class="form-control form-control-sm sb-sell-price text-right bg-light" required autocomplete="off" placeholder="0.00" title="Sell Price (Read-only)">
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $mrpPriceVal }}" readonly tabindex="-1" class="form-control form-control-sm sb-mrp text-right bg-light" autocomplete="off" placeholder="0.00" title="MRP (Read-only)">
    </td>
    <td style="width: 80px;">
        <input type="number" step="any" name="items[{{ $index }}][disc_percent]" value="{{ $discPercentVal }}" class="form-control form-control-sm sb-disc-percent text-right" autocomplete="off" placeholder="0%">
    </td>
    <td style="width: 95px;">
        <input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $discAmountVal }}" class="form-control form-control-sm sb-disc-amount text-right" autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 75px;">
        <input type="text" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" readonly tabindex="-1" class="form-control form-control-sm sb-gst-percent text-right bg-light" autocomplete="off" placeholder="0%" title="GST % (Read-only)">
    </td>
    <td style="width: 85px;">
        <input type="text" class="form-control form-control-sm sb-gst-tax-amount text-right bg-light font-weight-bold" readonly tabindex="-1" value="{{ $gstTaxAmtVal }}" placeholder="0.00" title="Included GST Amount">
    </td>
    <td style="width: 105px;" class="text-right align-middle font-weight-bold text-success sb-row-net">{{ $netAmtVal }}</td>
    <td style="width: 35px;" class="text-center align-middle">
        <button type="button" tabindex="-1" class="btn btn-xs btn-outline-danger sb-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
