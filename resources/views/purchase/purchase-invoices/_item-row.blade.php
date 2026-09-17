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
    if ($selectedItemId && ! $selectedItem) {
        $selectedItem = \App\Models\Item::with('gstTax:id,percentage')->find($selectedItemId);
    }
    $isExpRequired = $selectedItem && in_array($selectedItem->batch_expiry_details ?? '', ['Mandatory', 'Days', 'Month']);
    $itemCodeVal = $line->code ?? ($selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? ''));

    $costVal = isset($line->cost_price) && $line->cost_price != 0 ? (float)$line->cost_price : (float)($selectedItem->cost_price ?? 0);
    $sellVal = isset($line->sell_price) && $line->sell_price != 0 ? (float)$line->sell_price : (float)($selectedItem->sell_price ?? 0);
    $mrpVal = isset($line->mrp) && $line->mrp != 0 ? (float)$line->mrp : (float)($selectedItem->mrp ?? 0);
    $gstVal = isset($line->gst_percent) && $line->gst_percent != 0 ? (float)$line->gst_percent : (float)($selectedItem?->gstTax?->percentage ?? 0);

    $baseSellVal = $sellVal > 0 ? $sellVal : $mrpVal;
    $sellExclGstVal = ($baseSellVal > 0) ? ($baseSellVal / (1 + ($gstVal / 100))) : 0;
    $marginVal = ($sellExclGstVal > 0 && $costVal > 0) ? round((($sellExclGstVal - $costVal) / $sellExclGstVal) * 100, 2) : null;
    $profitVal = ($costVal > 0 && $sellExclGstVal > 0) ? round((($sellExclGstVal - $costVal) / $costVal) * 100, 2) : null;

    $qtyVal = isset($line->qty) && $line->qty != 0 ? $line->qty : '';
    $freeQtyVal = isset($line->free_qty) && $line->free_qty != 0 ? $line->free_qty : '';
    $costPriceVal = isset($line->cost_price) && $line->cost_price != 0 ? $line->cost_price : ($selectedItem?->cost_price > 0 ? $selectedItem->cost_price : '');
    $sellPriceVal = isset($line->sell_price) && $line->sell_price != 0 ? $line->sell_price : ($selectedItem?->sell_price > 0 ? $selectedItem->sell_price : '');
    $mrpPriceVal = isset($line->mrp) && $line->mrp != 0 ? $line->mrp : ($selectedItem?->mrp > 0 ? $selectedItem->mrp : '');
    $discPercentVal = isset($line->disc_percent) && $line->disc_percent != 0 ? $line->disc_percent : '';
    $discAmountVal = isset($line->disc_amount) && $line->disc_amount != 0 ? $line->disc_amount : '';
    $gstPercentVal = isset($line->gst_percent) && $line->gst_percent != 0 ? $line->gst_percent : ($selectedItem?->gstTax?->percentage > 0 ? $selectedItem->gstTax->percentage : '');
    $gstTaxAmtVal = isset($line->gst_tax_amount) && $line->gst_tax_amount != 0 ? number_format($line->gst_tax_amount, 2, '.', '') : '';
    $netAmtVal = isset($line->net_amount) && $line->net_amount != 0 ? number_format($line->net_amount, 2) : '';
    $expDateVal = '';
    if (!empty($line->exp_date)) {
        $expDateVal = is_string($line->exp_date) ? $line->exp_date : optional($line->exp_date)->format('Y-m-d');
    }
@endphp
<tr>
    <td class="text-center align-middle font-weight-bold pinv-sr-no">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="min-width: 110px;">
        <input type="text" class="form-control form-control-sm pinv-item-code font-weight-bold" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code / Barcode" title="Enter item code or barcode">
    </td>
    <td style="min-width: 220px;">
        <input type="text"
               class="form-control form-control-sm pinv-item-desc bg-light font-weight-bold text-truncate"
               readonly
               tabindex="-1"
               value="{{ $selectedItem ? ($selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '')) : '' }}"
               placeholder="Product Description"
               title="Product description">
        <input type="hidden"
               name="items[{{ $index }}][item_id]"
               class="pinv-item-select"
               value="{{ $selectedItemId }}"
               required>
    </td>
    <td style="width: 125px;">
        <input type="date"
               name="items[{{ $index }}][exp_date]"
               value="{{ $expDateVal }}"
               class="form-control form-control-sm pinv-exp-date {{ $isExpRequired ? 'border-danger' : '' }}"
               @if($isExpRequired) required @endif
               autocomplete="off"
               title="{{ $isExpRequired ? 'Expiry date is mandatory for this item' : 'Expiry date (optional)' }}">
        <small class="pinv-exp-badge text-danger font-weight-bold {{ $isExpRequired ? '' : 'd-none' }}"><i class="fas fa-exclamation-circle"></i> Required</small>
    </td>
    <td style="width: 85px;"><input type="number" step="0.001" name="items[{{ $index }}][qty]" value="{{ $qtyVal }}" class="form-control form-control-sm pinv-qty" required autocomplete="off"></td>
    <td style="width: 80px;"><input type="number" step="0.001" name="items[{{ $index }}][free_qty]" value="{{ $freeQtyVal }}" class="form-control form-control-sm pinv-free-qty" autocomplete="off"></td>
    <td style="width: 95px;"><input type="number" step="0.01" name="items[{{ $index }}][cost_price]" value="{{ $costPriceVal }}" class="form-control form-control-sm pinv-cost" required autocomplete="off"></td>
    <td style="width: 95px;"><input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $sellPriceVal }}" class="form-control form-control-sm pinv-sell" autocomplete="off"></td>
    <td style="width: 95px;"><input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $mrpPriceVal }}" class="form-control form-control-sm pinv-mrp" autocomplete="off"></td>
    <td style="width: 85px;"><input type="text" readonly tabindex="-1" class="form-control form-control-sm pinv-margin bg-light text-right font-weight-bold" value="{{ $marginVal !== null && $marginVal != 0 ? number_format($marginVal, 2).'%' : '' }}" autocomplete="off" title="Margin % = [(Selling Price incl. GST ÷ (1 + GST%/100)) − Cost] ÷ [Selling Price incl. GST ÷ (1 + GST%/100)] × 100"></td>
    <td style="width: 85px;"><input type="text" readonly tabindex="-1" class="form-control form-control-sm pinv-profit bg-light text-right font-weight-bold" value="{{ $profitVal !== null && $profitVal != 0 ? number_format($profitVal, 2).'%' : '' }}" autocomplete="off" title="Profit % = Profit Amount ÷ Cost Price × 100"></td>
    <td style="width: 80px;"><input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $discPercentVal }}" class="form-control form-control-sm pinv-disc-percent" autocomplete="off"></td>
    <td style="width: 90px;"><input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $discAmountVal }}" class="form-control form-control-sm pinv-disc-amount" autocomplete="off"></td>
    <td style="width: 75px;"><input type="number" step="0.01" readonly tabindex="-1" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" class="form-control form-control-sm pinv-gst bg-light text-right" autocomplete="off" placeholder="0%" title="GST % (Read-only)"></td>
    <td style="width: 95px;"><input type="number" step="0.01" readonly tabindex="-1" name="items[{{ $index }}][gst_tax_amount]" value="{{ $gstTaxAmtVal }}" class="form-control form-control-sm pinv-gst-amt bg-light text-right" autocomplete="off" title="GST Tax Amount (Read-only)"></td>
    <td style="width: 105px;" class="text-right align-middle font-weight-bold text-success pinv-row-net">{{ $netAmtVal }}</td>
    <td style="width: 35px;" class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger pinv-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
