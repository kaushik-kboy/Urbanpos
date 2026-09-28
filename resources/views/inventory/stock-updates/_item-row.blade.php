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

    $batchNo = data_get($line, 'batch_no', '');

    $expDate = data_get($line, 'exp_date');
    if ($expDate instanceof \DateTimeInterface) {
        $expDate = $expDate->format('Y-m-d');
    }

    $costPrice = data_get($line, 'cost_price', $itemObj?->cost_price ?? '');
    $physicalQty = data_get($line, 'physical_qty', '');
    $systemQty = data_get($line, 'system_qty_at_entry');
    $sellPrice = data_get($line, 'sell_price', $itemObj?->sell_price ?? '');
    $mrp = data_get($line, 'mrp', $itemObj?->mrp ?? '');
@endphp
<tr class="su-item-row" data-row-index="{{ $idx }}">
    <td style="min-width: 130px;" data-col-key="code">
        <input type="hidden" name="items[{{ $idx }}][item_id]" class="su-item-id" value="{{ $itemId }}">
        <input type="text" class="form-control form-control-sm su-item-code font-weight-bold text-uppercase" placeholder="Code / Barcode" value="{{ $itemCode }}" autocomplete="off" title="Enter or F2 to search">
    </td>
    <td style="min-width: 180px;" data-col-key="item">
        <input type="text" class="form-control form-control-sm su-item-desc bg-light font-weight-bold text-truncate" value="{{ $itemName }}" placeholder="Product Description (auto-filled)" readonly tabindex="-1">
    </td>
    <td style="width: 110px;" data-col-key="batch_no">
        <input type="text" name="items[{{ $idx }}][batch_no]" value="{{ $batchNo }}" class="form-control form-control-sm su-batch-no font-weight-bold bg-light" placeholder="Batch" readonly tabindex="-1">
    </td>
    <td style="width: 120px;" data-col-key="expiry">
        <input type="date" name="items[{{ $idx }}][exp_date]" value="{{ $expDate }}" class="form-control form-control-sm su-exp-date bg-light" readonly tabindex="-1">
    </td>
    <td style="width: 95px;" data-col-key="cost_price">
        <input type="number" step="0.01" name="items[{{ $idx }}][cost_price]" value="{{ $costPrice }}" class="form-control form-control-sm text-right su-cost-price bg-light" placeholder="0.00" readonly tabindex="-1">
    </td>
    <td style="width: 105px;" data-col-key="qty">
        <input type="number" step="0.001" min="0" name="items[{{ $idx }}][physical_qty]" value="{{ $physicalQty }}" class="form-control form-control-sm text-right su-physical-qty font-weight-bold" placeholder="0.000">
    </td>
    <td class="align-middle text-muted small text-right su-current-stock" style="width: 95px;" data-col-key="current_stock">
        {{ is_numeric($systemQty) ? number_format((float)$systemQty, 3) : 'saved on submit' }}
    </td>
    <td style="width: 95px;" data-col-key="sell_price">
        <input type="number" step="0.01" name="items[{{ $idx }}][sell_price]" value="{{ $sellPrice }}" class="form-control form-control-sm text-right su-sell-price bg-light" placeholder="0.00" readonly tabindex="-1">
    </td>
    <td style="width: 95px;" data-col-key="mrp">
        <input type="number" step="0.01" name="items[{{ $idx }}][mrp]" value="{{ $mrp }}" class="form-control form-control-sm text-right su-mrp bg-light" placeholder="0.00" readonly tabindex="-1">
    </td>
    <td class="text-center align-middle" style="width: 40px;" data-col-key="actions">
        <button type="button" class="btn btn-xs btn-outline-danger su-row-remove" title="Remove row"><i class="fas fa-times"></i></button>
    </td>
</tr>
