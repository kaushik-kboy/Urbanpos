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
    $itemCodeVal = $selectedItemId ?: ($selectedItem ? $selectedItem->id : (data_get($line, 'code') ?? ''));
    $selectedItemName = $selectedItem ? ($selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '')) : '';
    $qtyVal = isset($line->qty) && $line->qty != 0 ? $line->qty : '';
    $sellPriceVal = isset($line->sell_price) && $line->sell_price != 0 ? $line->sell_price : ($selectedItem?->sell_price > 0 ? $selectedItem->sell_price : '');
    $mrpPriceVal = isset($line->mrp) && $line->mrp != 0 ? $line->mrp : ($selectedItem?->mrp > 0 ? $selectedItem->mrp : '');
    $discPercentVal = isset($line->disc_percent) && $line->disc_percent != 0 ? $line->disc_percent : '';
    $discAmountVal = isset($line->disc_amount) && $line->disc_amount != 0 ? $line->disc_amount : '';
    $gstPercentVal = isset($line->gst_percent) && $line->gst_percent != 0 ? $line->gst_percent : ($selectedItem?->gstTax?->percentage > 0 ? $selectedItem->gstTax->percentage : '');
    $netAmtVal = isset($line->net_amount) && $line->net_amount != 0 ? number_format($line->net_amount, 2) : '';
@endphp
<tr>
    <td class="text-center align-middle font-weight-bold sq-sr-no">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="width: 130px;">
        <input type="text"
               class="form-control form-control-sm sq-item-code font-weight-bold"
               value="{{ $itemCodeVal }}"
               autocomplete="off"
               placeholder="Code / Barcode"
               title="Click or Tab to search item">
    </td>
    <td style="min-width: 220px;">
        <input type="text"
               class="form-control form-control-sm sq-item-desc bg-light font-weight-bold text-truncate"
               readonly
               tabindex="-1"
               value="{{ $selectedItemName }}"
               placeholder="Product Description (auto-filled)"
               title="Product description">
        <input type="hidden"
               name="items[{{ $index }}][item_id]"
               class="sq-item-select"
               value="{{ $selectedItemId }}"
               required>
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.001" name="items[{{ $index }}][qty]" value="{{ $qtyVal }}" class="form-control form-control-sm sq-qty font-weight-bold text-right" required autocomplete="off" placeholder="Qty">
    </td>
    <td style="width: 120px;">
        <input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $sellPriceVal }}" class="form-control form-control-sm sq-sell-price text-right" required autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 110px;">
        <input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $mrpPriceVal }}" class="form-control form-control-sm sq-mrp text-right" autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 90px;">
        <input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $discPercentVal }}" class="form-control form-control-sm sq-disc-percent text-right" autocomplete="off" placeholder="0%">
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $discAmountVal }}" class="form-control form-control-sm sq-disc-amount text-right" autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 85px;">
        <input type="number" step="0.01" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" readonly tabindex="-1" class="form-control form-control-sm sq-gst-percent text-right bg-light" autocomplete="off" placeholder="0%" title="GST % (Read-only)">
    </td>
    <td style="width: 120px;" class="text-right align-middle font-weight-bold text-success sq-row-net">{{ $netAmtVal }}</td>
    <td style="width: 35px;" class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger sq-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
