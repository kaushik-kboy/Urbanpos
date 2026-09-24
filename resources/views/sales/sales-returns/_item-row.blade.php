@php
    $rowId = $index ?? 0;
    $itemId = data_get($line, 'item_id');
    $expDate = data_get($line, 'exp_date');
    if ($expDate instanceof \DateTimeInterface) {
        $expDate = $expDate->format('Y-m-d');
    }
    $qty = data_get($line, 'qty', '');
    $sellPrice = data_get($line, 'sell_price', '');
    $mrp = data_get($line, 'mrp', '');
    $discPercent = data_get($line, 'disc_percent', 0);
    $discAmount = data_get($line, 'disc_amount', 0);
    $gstPercent = data_get($line, 'gst_percent', 0);
    $netAmount = data_get($line, 'net_amount', 0);

    // Resolve item for display
    $resolvedItem = $itemId ? \App\Models\Item::find($itemId) : null;
    $itemCode = $itemId ?: ($resolvedItem ? $resolvedItem->id : '');
    $itemDesc = $resolvedItem ? ($resolvedItem->name . ($resolvedItem->item_code ? ' [' . $resolvedItem->item_code . ']' : '')) : '';
@endphp
<tr class="sr-item-row" data-row-index="{{ $rowId }}">
    {{-- Code / Barcode --}}
    <td style="min-width: 115px;" data-col-key="code">
        <input type="text"
               class="form-control form-control-sm sr-item-code font-weight-bold"
               value="{{ $rowId === '__INDEX__' ? '' : $itemCode }}"
               autocomplete="off"
               placeholder="Code / Barcode"
               title="Enter item code or barcode">
    </td>
    {{-- Description (readonly, auto-filled) --}}
    <td style="min-width: 220px;" data-col-key="item">
        <input type="text"
               class="form-control form-control-sm sr-item-desc bg-light text-truncate"
               readonly
               tabindex="-1"
               value="{{ $rowId === '__INDEX__' ? '' : $itemDesc }}"
               placeholder="Product Description">
        <input type="hidden"
               name="items[{{ $rowId }}][item_id]"
               class="sr-item-select"
               value="{{ $rowId === '__INDEX__' ? '' : $itemId }}"
               required>
    </td>
    {{-- Exp Date --}}
    <td style="width: 135px;" data-col-key="expiry">
        <input type="date" name="items[{{ $rowId }}][exp_date]"
               value="{{ $rowId === '__INDEX__' ? '' : $expDate }}"
               class="form-control form-control-sm sr-exp-date bg-light"
               readonly tabindex="-1">
    </td>
    {{-- Qty --}}
    <td style="width: 100px;" data-col-key="qty">
        <input type="number" step="0.001" min="0.001"
               name="items[{{ $rowId }}][qty]"
               value="{{ $rowId === '__INDEX__' ? '' : $qty }}"
               data-original-qty="{{ data_get($line, 'original_qty', '') }}"
               class="form-control form-control-sm text-right sr-qty font-weight-bold"
               placeholder="Qty" required autocomplete="off">
        <small class="text-muted d-block text-right sr-max-qty-label" style="font-size: 10px;"></small>
    </td>
    {{-- Sell Price --}}
    <td style="width: 110px;" data-col-key="sell_price">
        <input type="number" step="0.01" min="0"
               name="items[{{ $rowId }}][sell_price]"
               value="{{ $rowId === '__INDEX__' ? '' : $sellPrice }}"
               class="form-control form-control-sm text-right sr-price bg-light"
               placeholder="0.00" required autocomplete="off"
               readonly tabindex="-1">
    </td>
    {{-- MRP --}}
    <td style="width: 100px;" data-col-key="mrp">
        <input type="number" step="0.01" min="0"
               name="items[{{ $rowId }}][mrp]"
               value="{{ $rowId === '__INDEX__' ? '' : $mrp }}"
               class="form-control form-control-sm text-right sr-mrp bg-light"
               placeholder="0.00" autocomplete="off"
               readonly tabindex="-1">
    </td>
    {{-- Disc % --}}
    <td style="width: 85px;" data-col-key="disc_percent">
        <input type="number" step="0.01" min="0" max="100"
               name="items[{{ $rowId }}][disc_percent]"
               value="{{ $rowId === '__INDEX__' ? '' : $discPercent }}"
               class="form-control form-control-sm text-right sr-disc-percent"
               placeholder="0" autocomplete="off">
    </td>
    {{-- Disc Amt --}}
    <td style="width: 100px;" data-col-key="disc_amt">
        <input type="number" step="0.01" min="0"
               name="items[{{ $rowId }}][disc_amount]"
               value="{{ $rowId === '__INDEX__' ? '' : $discAmount }}"
               class="form-control form-control-sm text-right sr-disc-amount"
               placeholder="0.00" autocomplete="off">
    </td>
    {{-- GST % --}}
    <td style="width: 75px;" data-col-key="gst_percent">
        <input type="number" step="0.01" min="0"
               name="items[{{ $rowId }}][gst_percent]"
               value="{{ $rowId === '__INDEX__' ? '' : $gstPercent }}"
               class="form-control form-control-sm text-right sr-gst-percent bg-light"
               placeholder="0" autocomplete="off"
               readonly tabindex="-1">
    </td>
    {{-- Net Amount --}}
    <td class="text-right align-middle font-weight-bold text-dark" style="width: 115px;" data-col-key="net_amt">
        ₹<span class="sr-net-amount">{{ $rowId === '__INDEX__' ? '0.00' : number_format((float) $netAmount, 2) }}</span>
    </td>
    {{-- Remove --}}
    <td class="text-center align-middle" style="width: 40px;" data-col-key="actions">
        <button type="button" class="btn btn-xs btn-outline-danger sr-row-remove" title="Remove row">
            <i class="fas fa-times"></i>
        </button>
    </td>
</tr>
