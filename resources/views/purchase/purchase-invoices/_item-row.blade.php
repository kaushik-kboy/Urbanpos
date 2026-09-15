@php
    $selectedItemId = $line->item_id ?? null;
    $selectedItem = null;
    if ($selectedItemId && isset($items)) {
        $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
            ? collect($items)->firstWhere('id', $selectedItemId)
            : null;
    }
    $isExpRequired = $selectedItem && in_array($selectedItem->batch_expiry_details ?? '', ['Mandatory', 'Days', 'Month']);
    $itemCodeVal = $selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? '');

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
@endphp
<tr>
    <td class="text-center align-middle font-weight-bold pinv-sr-no">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="min-width: 110px;">
        <input type="text" class="form-control form-control-sm pinv-item-code font-weight-bold" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code / Barcode" title="Enter item code or barcode">
    </td>
    <td style="min-width: 220px;">
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm select2 pinv-item-select" required data-placeholder="Select item">
            <option value="">Select item</option>
            @foreach ($items as $item)
                @php
                    $itemId = is_object($item) ? $item->id : $item;
                    $itemName = is_object($item) ? $item->name : $item;
                    $itemCode = is_object($item) ? ($item->item_code ?? '') : '';
                    $eanCode = is_object($item) ? ($item->ean_upc_code ?? '') : '';
                    $costPrice = is_object($item) ? (float)($item->cost_price ?? 0) : 0;
                    $sellPrice = is_object($item) ? (float)($item->sell_price ?? 0) : 0;
                    $mrp = is_object($item) ? (float)($item->mrp ?? 0) : 0;
                    $gstPercent = is_object($item) ? (float)($item->gstTax?->percentage ?? 0) : 0;
                    $batchExpiry = is_object($item) ? ($item->batch_expiry_details ?? 'Not Required') : 'Not Required';
                    $shelfLife = is_object($item) ? ($item->shelf_life_days ?? '') : '';
                @endphp
                <option value="{{ $itemId }}"
                        data-code="{{ $itemCode }}"
                        data-ean="{{ $eanCode }}"
                        data-cost="{{ $costPrice }}"
                        data-sell="{{ $sellPrice }}"
                        data-mrp="{{ $mrp }}"
                        data-gst="{{ $gstPercent }}"
                        data-batch-expiry="{{ $batchExpiry }}"
                        data-shelf-life="{{ $shelfLife }}"
                        @selected(($line->item_id ?? null) == $itemId)>
                    {{ $itemName }}{{ $itemCode ? ' ['.$itemCode.']' : '' }}
                </option>
            @endforeach
        </select>
    </td>
    <td style="width: 125px;">
        <input type="date"
               name="items[{{ $index }}][exp_date]"
               value="{{ optional($line->exp_date ?? null)->format('Y-m-d') }}"
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
    <td style="width: 85px;"><input type="text" readonly class="form-control form-control-sm pinv-margin bg-light text-right font-weight-bold" value="{{ $marginVal !== null && $marginVal != 0 ? number_format($marginVal, 2).'%' : '' }}" autocomplete="off" title="Margin % = [(Selling Price incl. GST ÷ (1 + GST%/100)) − Cost] ÷ [Selling Price incl. GST ÷ (1 + GST%/100)] × 100"></td>
    <td style="width: 85px;"><input type="text" readonly class="form-control form-control-sm pinv-profit bg-light text-right font-weight-bold" value="{{ $profitVal !== null && $profitVal != 0 ? number_format($profitVal, 2).'%' : '' }}" autocomplete="off" title="Profit % = Profit Amount ÷ Cost Price × 100"></td>
    <td style="width: 80px;"><input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $discPercentVal }}" class="form-control form-control-sm pinv-disc-percent" autocomplete="off"></td>
    <td style="width: 90px;"><input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $discAmountVal }}" class="form-control form-control-sm pinv-disc-amount" autocomplete="off"></td>
    <td style="width: 75px;"><input type="number" step="0.01" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" class="form-control form-control-sm pinv-gst" autocomplete="off"></td>
    <td style="width: 95px;"><input type="number" step="0.01" readonly name="items[{{ $index }}][gst_tax_amount]" value="{{ $gstTaxAmtVal }}" class="form-control form-control-sm pinv-gst-amt bg-light text-right" autocomplete="off"></td>
    <td style="width: 105px;" class="text-right align-middle font-weight-bold text-success pinv-row-net">{{ $netAmtVal }}</td>
    <td style="width: 35px;" class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger pinv-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
