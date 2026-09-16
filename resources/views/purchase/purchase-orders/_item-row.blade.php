@php
    $rowId = $index;
    if (is_array($line)) {
        $line = (object) $line;
    }
    $itemId = data_get($line, 'item_id');
    $selectedItem = null;
    if ($itemId && isset($items)) {
        $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
            ? collect($items)->firstWhere('id', $itemId)
            : null;
    }
    if ($itemId && ! $selectedItem) {
        $selectedItem = \App\Models\Item::with('gstTax:id,percentage')->find($itemId);
    }
    $itemCodeVal = data_get($line, 'code') ?? ($selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? ''));
    $selectedItemName = $selectedItem ? ($selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '')) : '';

    $qty = data_get($line, 'qty', '');
    $freeQty = data_get($line, 'free_qty', 0);
    $costPrice = data_get($line, 'cost_price', ($selectedItem?->cost_price > 0 ? $selectedItem->cost_price : ''));
    $sellPrice = data_get($line, 'sell_price', ($selectedItem?->sell_price > 0 ? $selectedItem->sell_price : ''));
    $mrp = data_get($line, 'mrp', ($selectedItem?->mrp > 0 ? $selectedItem->mrp : ''));
    $discPercent = data_get($line, 'disc_percent', 0);
    $discAmount = data_get($line, 'disc_amount', 0);
    $gstPercent = data_get($line, 'gst_percent', ($selectedItem?->gstTax?->percentage > 0 ? $selectedItem->gstTax->percentage : 0));
@endphp
<tr>
    <td class="text-center align-middle font-weight-bold po-sr-no">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="min-width: 110px;">
        <input type="text" class="form-control form-control-sm po-item-code font-weight-bold" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code / Barcode" title="Enter item code or barcode, or click to search">
    </td>
    <td style="min-width: 220px;">
        <input type="text"
               class="form-control form-control-sm po-item-desc bg-light font-weight-bold text-truncate"
               readonly
               tabindex="-1"
               value="{{ $selectedItemName }}"
               placeholder="Product Description (auto-filled)"
               title="Product description">
        <input type="hidden"
               name="items[{{ $rowId }}][item_id]"
               class="po-item-select"
               value="{{ $itemId }}"
               required>
    </td>
    <td style="width: 85px;">
        @php
            $itemStockVal = 0;
            if ($itemId) {
                $bId = $branchId ?? (isset($po) ? $po->branch_id : session('active_branch_id', auth()->user()?->branch_id ?? 3));
                $itemStockVal = (float) (\App\Models\ItemStock::where('item_id', $itemId)->where('branch_id', $bId)->value('quantity') ?? 0);
            }
        @endphp
        <input type="text"
               class="form-control form-control-sm po-item-stock text-right bg-light font-weight-bold text-info"
               readonly
               tabindex="-1"
               value="{{ number_format($itemStockVal, 0) }}"
               placeholder="0"
               title="Current stock in this branch">
    </td>
    <td><input type="number" step="0.001" min="0.001" name="items[{{ $rowId }}][qty]" value="{{ $qty }}" class="form-control form-control-sm po-qty text-right font-weight-bold" placeholder="Qty" required autocomplete="off"></td>
    <td><input type="number" step="0.001" min="0" name="items[{{ $rowId }}][free_qty]" value="{{ $freeQty }}" class="form-control form-control-sm po-free-qty text-right" placeholder="0" autocomplete="off"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][cost_price]" value="{{ $costPrice }}" class="form-control form-control-sm po-cost text-right font-weight-bold" placeholder="0.00" required autocomplete="off"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][sell_price]" value="{{ $sellPrice }}" class="form-control form-control-sm po-sell text-right" placeholder="0.00" autocomplete="off"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][mrp]" value="{{ $mrp }}" class="form-control form-control-sm po-mrp text-right" placeholder="0.00" autocomplete="off"></td>
    <td><input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][disc_percent]" value="{{ $discPercent }}" class="form-control form-control-sm po-disc-percent text-right" placeholder="0" autocomplete="off"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][disc_amount]" value="{{ $discAmount }}" class="form-control form-control-sm po-disc-amount text-right" placeholder="0.00" autocomplete="off"></td>
    <td><input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][gst_percent]" value="{{ $gstPercent }}" class="form-control form-control-sm po-gst text-right" placeholder="0" autocomplete="off"></td>
    <td class="text-right align-middle font-weight-bold text-success" style="width:115px;">
        ₹<span class="po-row-net">0.00</span>
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger po-remove-row" title="Remove row"><i class="fas fa-times"></i></button>
    </td>
</tr>
