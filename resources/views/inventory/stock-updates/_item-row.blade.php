@php
    $idx = $index ?? 0;
    $itemId = data_get($line, 'item_id');
    $itemObj = null;
    if ($line instanceof \App\Models\StockUpdateItem) {
        $itemObj = $line->item;
    } elseif ($itemId) {
        $itemObj = \App\Models\Item::find($itemId);
    }
    $itemCode = $itemObj ? ($itemObj->item_code ?: $itemObj->ean_upc_code) : data_get($line, 'item_code', '');
    $itemName = $itemObj ? $itemObj->name : data_get($line, 'item_name', '');

    $expDate = data_get($line, 'exp_date');
    if ($expDate instanceof \DateTimeInterface) {
        $expDate = $expDate->format('Y-m-d');
    }

    $physicalQty = data_get($line, 'physical_qty', '');
    $systemQty = data_get($line, 'system_qty_at_entry');
    $sellPrice = data_get($line, 'sell_price', $itemObj?->sell_price ?? '');
    $mrp = data_get($line, 'mrp', $itemObj?->mrp ?? '');
@endphp
<tr class="su-item-row" data-row-index="{{ $idx }}">
    <td style="min-width: 140px;">
        <input type="hidden" name="items[{{ $idx }}][item_id]" class="su-item-id" value="{{ $itemId }}" required>
        <input type="text" class="form-control form-control-sm su-item-code font-weight-bold text-uppercase" placeholder="Code / Barcode" value="{{ $itemCode }}" autocomplete="off" title="Enter code or click/tab to search">
    </td>
    <td style="min-width: 220px;">
        <input type="text" class="form-control form-control-sm su-item-desc bg-light font-weight-bold text-truncate" value="{{ $itemName }}" placeholder="Product Description (auto-filled)" readonly tabindex="-1">
    </td>
    <td style="width: 130px;">
        <input type="date" name="items[{{ $idx }}][exp_date]" value="{{ $expDate }}" class="form-control form-control-sm su-exp-date">
    </td>
    <td style="width: 110px;">
        <input type="number" step="0.001" name="items[{{ $idx }}][physical_qty]" value="{{ $physicalQty }}" class="form-control form-control-sm text-right su-physical-qty font-weight-bold" placeholder="0.000" required>
    </td>
    <td class="align-middle text-muted small text-right su-current-stock" style="width: 100px;">
        {{ is_numeric($systemQty) ? number_format((float)$systemQty, 3) : 'saved on submit' }}
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $idx }}][sell_price]" value="{{ $sellPrice }}" class="form-control form-control-sm text-right su-sell-price" placeholder="0.00">
    </td>
    <td style="width: 100px;">
        <input type="number" step="0.01" name="items[{{ $idx }}][mrp]" value="{{ $mrp }}" class="form-control form-control-sm text-right su-mrp" placeholder="0.00">
    </td>
    <td class="text-center align-middle" style="width: 40px;">
        <button type="button" class="btn btn-xs btn-outline-danger su-row-remove" title="Remove row"><i class="fas fa-times"></i></button>
    </td>
</tr>
