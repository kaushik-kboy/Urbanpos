@php
    $selectedItemId = $line->item_id ?? null;
    $selectedItem = null;
    if ($selectedItemId && isset($items)) {
        $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
            ? collect($items)->firstWhere('id', $selectedItemId)
            : null;
    }
    $itemCodeVal = $selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? '');
    $qtyVal = isset($line->qty) && $line->qty != 0 ? $line->qty : '';
    $sellPriceVal = isset($line->sell_price) && $line->sell_price != 0 ? $line->sell_price : ($selectedItem?->sell_price > 0 ? $selectedItem->sell_price : '');
    $mrpPriceVal = isset($line->mrp) && $line->mrp != 0 ? $line->mrp : ($selectedItem?->mrp > 0 ? $selectedItem->mrp : '');
    $discPercentVal = isset($line->disc_percent) && $line->disc_percent != 0 ? $line->disc_percent : '';
    $discAmountVal = isset($line->disc_amount) && $line->disc_amount != 0 ? $line->disc_amount : '';
    $gstPercentVal = isset($line->gst_percent) && $line->gst_percent != 0 ? $line->gst_percent : ($selectedItem?->gstTax?->percentage > 0 ? $selectedItem->gstTax->percentage : '');
    $netAmtVal = isset($line->net_amount) && $line->net_amount != 0 ? number_format($line->net_amount, 2) : '';
@endphp
<tr>
    <td class="text-center align-middle font-weight-bold sb-sr-no">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="min-width: 110px;">
        <input type="text" class="form-control form-control-sm sb-item-code font-weight-bold" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code / Barcode" title="Enter item code or barcode">
    </td>
    <td style="min-width: 220px;">
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm select2 sb-item-select" required data-placeholder="Select item">
            <option value="">Select item</option>
            @foreach ($items as $item)
                @php
                    $itemId = is_object($item) ? $item->id : $item;
                    $itemName = is_object($item) ? $item->name : $item;
                    $itemCode = is_object($item) ? ($item->item_code ?? '') : '';
                    $eanCode = is_object($item) ? ($item->ean_upc_code ?? '') : '';
                    $sellPrice = is_object($item) ? (float)($item->sell_price ?? 0) : 0;
                    $mrp = is_object($item) ? (float)($item->mrp ?? 0) : 0;
                    $gstPercent = is_object($item) ? (float)($item->gstTax?->percentage ?? 0) : 0;
                    $batchExpiry = is_object($item) ? ($item->batch_expiry_details ?? 'Not Required') : 'Not Required';
                @endphp
                <option value="{{ $itemId }}"
                        data-code="{{ $itemCode }}"
                        data-ean="{{ $eanCode }}"
                        data-sell="{{ $sellPrice }}"
                        data-mrp="{{ $mrp }}"
                        data-gst="{{ $gstPercent }}"
                        data-batch-expiry="{{ $batchExpiry }}"
                        @selected(($line->item_id ?? null) == $itemId)>
                    {{ $itemName }}{{ $itemCode ? ' ['.$itemCode.']' : '' }}
                </option>
            @endforeach
        </select>
    </td>
    <td style="width: 85px;">
        <input type="text" readonly class="form-control form-control-sm sb-item-stock bg-light text-center font-weight-bold" value="" placeholder="0.00" title="Current available stock in selected branch">
    </td>
    <td style="width: 135px;">
        <div class="input-group input-group-sm">
            <input type="date"
                   name="items[{{ $index }}][exp_date]"
                   value="{{ optional($line->exp_date ?? null)->format('Y-m-d') }}"
                   class="form-control form-control-sm sb-exp-date"
                   autocomplete="off"
                   title="Expiry date">
            <div class="input-group-append sb-batch-btn-wrap d-none">
                <button type="button" class="btn btn-warning btn-xs sb-btn-choose-batch" title="Multiple batches available! Click to choose batch">
                    <i class="fas fa-layer-group"></i>
                </button>
            </div>
        </div>
    </td>
    <td style="width: 85px;">
        <input type="number" step="0.001" name="items[{{ $index }}][qty]" value="{{ $qtyVal }}" class="form-control form-control-sm sb-qty font-weight-bold text-right" required autocomplete="off" placeholder="Qty">
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $sellPriceVal }}" class="form-control form-control-sm sb-sell-price text-right" required autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $mrpPriceVal }}" class="form-control form-control-sm sb-mrp text-right" autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 80px;">
        <input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $discPercentVal }}" class="form-control form-control-sm sb-disc-percent text-right" autocomplete="off" placeholder="0%">
    </td>
    <td style="width: 95px;">
        <input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $discAmountVal }}" class="form-control form-control-sm sb-disc-amount text-right" autocomplete="off" placeholder="0.00">
    </td>
    <td style="width: 75px;">
        <input type="number" step="0.01" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" class="form-control form-control-sm sb-gst-percent text-right" autocomplete="off" placeholder="0%">
    </td>
    <td style="width: 105px;" class="text-right align-middle font-weight-bold text-success sb-row-net">{{ $netAmtVal }}</td>
    <td style="width: 35px;" class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger sb-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
