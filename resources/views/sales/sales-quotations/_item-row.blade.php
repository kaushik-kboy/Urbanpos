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
    <td style="min-width: 250px;">
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm select2 sq-item-select" required data-placeholder="Select item">
            <option value="">Select item</option>
            @foreach ($items as $item)
                @php
                    $itemId = is_object($item) ? $item->id : $item;
                    $itemName = is_object($item) ? $item->name : $item;
                    $itemCode = is_object($item) ? ($item->item_code ?? '') : '';
                    $sellPrice = is_object($item) ? (float)($item->sell_price ?? 0) : 0;
                    $mrp = is_object($item) ? (float)($item->mrp ?? 0) : 0;
                    $gstPercent = is_object($item) ? (float)($item->gstTax?->percentage ?? 0) : 0;
                @endphp
                <option value="{{ $itemId }}"
                        data-code="{{ $itemCode }}"
                        data-sell="{{ $sellPrice }}"
                        data-mrp="{{ $mrp }}"
                        data-gst="{{ $gstPercent }}"
                        @selected(($line->item_id ?? null) == $itemId)>
                    {{ $itemName }}{{ $itemCode ? ' ['.$itemCode.']' : '' }}
                </option>
            @endforeach
        </select>
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
        <input type="number" step="0.01" name="items[{{ $index }}][gst_percent]" value="{{ $gstPercentVal }}" class="form-control form-control-sm sq-gst-percent text-right" autocomplete="off" placeholder="0%">
    </td>
    <td style="width: 120px;" class="text-right align-middle font-weight-bold text-success sq-row-net">{{ $netAmtVal }}</td>
    <td style="width: 35px;" class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger sq-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
