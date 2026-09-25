@php
    if (is_array($line)) {
        $line = (object) $line;
    }
    $selectedItemId = $line->item_id ?? null;
    $selectedItem = null;
    // 1) Prefer the pre-loaded Eloquent relation (avoids any DB query during edit)
    if ($selectedItemId && isset($line->item) && $line->item instanceof \App\Models\Item) {
        $selectedItem = $line->item;
    }
    // 2) Fall back to scanning the $items collection passed from the create form
    if (!$selectedItem && $selectedItemId && isset($items)) {
        $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
            ? collect($items)->firstWhere('id', $selectedItemId)
            : null;
    }
    // 3) Last resort: single query (only fires when neither relation nor collection has the item)
    if ($selectedItemId && ! $selectedItem) {
        $selectedItem = \App\Models\Item::with('gstTax:id,percentage')->find($selectedItemId);
    }
    $isExpRequired = $selectedItem && in_array($selectedItem->batch_expiry_details ?? '', ['Mandatory', 'Days', 'Month']);
    // Show item_code (internal), NOT barcode/EAN
    $itemCodeVal = $line->code ?? ($selectedItem->item_code ?? '');

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
<tr style="line-height: 1;">
    <td class="text-center align-middle font-weight-bold pinv-sr-no px-1" style="width:28px;" data-col-key="sr">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    {{-- Code: show item_code only (no barcode) --}}
    <td class="px-1" style="width:80px;" data-col-key="code">
        <input type="text" class="form-control form-control-sm pinv-item-code font-weight-bold px-1" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code" title="Enter item code" style="font-size:0.78rem;">
    </td>
    {{-- Description --}}
    <td class="px-1" style="min-width:150px; max-width:190px;" data-col-key="desc">
        <input type="text"
               class="form-control form-control-sm pinv-item-desc bg-light font-weight-bold text-truncate px-1"
               readonly
               tabindex="-1"
               value="{{ $selectedItem ? ($selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '')) : '' }}"
               placeholder="Description"
               title="Product description"
               style="font-size:0.78rem;">
        <input type="hidden"
               name="items[{{ $index }}][item_id]"
               class="pinv-item-select"
               value="{{ $selectedItemId }}">
    </td>
    {{-- Exp Date --}}
    <td class="px-1" style="width:110px;" data-col-key="exp">
        <input type="date"
               name="items[{{ $index }}][exp_date]"
               value="{{ $expDateVal }}"
               class="form-control form-control-sm pinv-exp-date {{ ($isExpRequired && !$expDateVal) ? 'border-danger' : ($isExpRequired && $expDateVal ? 'border-success' : '') }}"
               autocomplete="off"
               title="{{ $isExpRequired ? 'Expiry date is mandatory for this item' : 'Expiry date (optional)' }}"
               style="font-size:0.78rem; padding: 1px 3px;">
        <small class="pinv-exp-badge text-danger font-weight-bold {{ ($isExpRequired && !$expDateVal) ? '' : 'd-none' }}" style="font-size:0.68rem;"><i class="fas fa-exclamation-circle"></i> Required</small>
    </td>
    {{-- Qty --}}
    <td class="px-1" style="width:62px;" data-col-key="qty"><input type="number" step="0.001" name="items[{{ $index }}][qty]" value="{{ $qtyVal }}" class="form-control form-control-sm pinv-qty text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- Free --}}
    <td class="px-1" style="width:52px;" data-col-key="free"><input type="number" step="0.001" name="items[{{ $index }}][free_qty]" value="{{ $freeQtyVal }}" class="form-control form-control-sm pinv-free-qty text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- Cost Price --}}
    <td class="px-1" style="width:82px;" data-col-key="cost"><input type="number" step="0.01" name="items[{{ $index }}][cost_price]" value="{{ $costPriceVal }}" class="form-control form-control-sm pinv-cost text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- Sell Price --}}
    <td class="px-1" style="width:82px;" data-col-key="sell"><input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $sellPriceVal }}" class="form-control form-control-sm pinv-sell text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- MRP --}}
    <td class="px-1" style="width:78px;" data-col-key="mrp"><input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $mrpPriceVal }}" class="form-control form-control-sm pinv-mrp text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- Margin % --}}
    <td class="px-1" style="width:62px;" data-col-key="margin"><input type="text" readonly tabindex="-1" class="form-control form-control-sm pinv-margin bg-light text-right px-1 font-weight-bold" value="{{ $marginVal !== null && $marginVal != 0 ? number_format($marginVal, 1).'%' : '' }}" autocomplete="off" title="Margin %" style="font-size:0.78rem;"></td>
    {{-- Profit % --}}
    <td class="px-1" style="width:62px;" data-col-key="profit"><input type="text" readonly tabindex="-1" class="form-control form-control-sm pinv-profit bg-light text-right px-1 font-weight-bold" value="{{ $profitVal !== null && $profitVal != 0 ? number_format($profitVal, 1).'%' : '' }}" autocomplete="off" title="Profit %" style="font-size:0.78rem;"></td>
    {{-- Disc % --}}
    <td class="px-1" style="width:55px;" data-col-key="disc_pct"><input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $discPercentVal }}" class="form-control form-control-sm pinv-disc-percent text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- Disc Amt --}}
    <td class="px-1" style="width:68px;" data-col-key="disc_amt"><input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $discAmountVal }}" class="form-control form-control-sm pinv-disc-amount text-right px-1" autocomplete="off" style="font-size:0.78rem;"></td>
    {{-- GST % --}}
    <td class="px-1" style="width:52px;" data-col-key="gst"><input type="number" step="0.01" readonly tabindex="-1" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" class="form-control form-control-sm pinv-gst bg-light text-right px-1" autocomplete="off" placeholder="0%" title="GST % (Read-only)" style="font-size:0.78rem;"></td>
    {{-- GST Tax Amt --}}
    <td class="px-1" style="width:72px;" data-col-key="gst_amt"><input type="number" step="0.01" readonly tabindex="-1" name="items[{{ $index }}][gst_tax_amount]" value="{{ $gstTaxAmtVal }}" class="form-control form-control-sm pinv-gst-amt bg-light text-right px-1" autocomplete="off" title="GST Tax Amount (Read-only)" style="font-size:0.78rem;"></td>
    {{-- Net Amount --}}
    <td class="px-1 text-right align-middle font-weight-bold text-success pinv-row-net" style="width:82px; font-size:0.82rem;" data-col-key="net">{{ $netAmtVal }}</td>
    {{-- Remove --}}
    <td class="px-1 text-center align-middle" style="width:28px;" data-col-key="action">
        <button type="button" class="btn btn-xs btn-outline-danger pinv-remove-row" style="padding:1px 4px;"><i class="fas fa-times"></i></button>
    </td>
</tr>
