@php
    $rowId = $index;
    $itemId = data_get($line, 'item_id');
    $item = is_object($line) && isset($line->item) ? $line->item : ($itemId ? \App\Models\Item::find($itemId) : null);
    $displayCode = $item?->ean_upc_code ?: ($item?->item_code ?? '');
    $expDate = data_get($line, 'exp_date');
    if ($expDate instanceof \DateTimeInterface) {
        $expDate = $expDate->format('Y-m-d');
    }
    $qty = data_get($line, 'qty', '');
    $costPrice = data_get($line, 'cost_price', '');
    $sellPrice = data_get($line, 'sell_price', '');
    $mrp = data_get($line, 'mrp', '');
    $discPercent = data_get($line, 'disc_percent', 0);
    $discAmount = data_get($line, 'disc_amount', 0);
    $gstPercent = data_get($line, 'gst_percent', 0);
    $gstTaxAmount = data_get($line, 'gst_tax_amount', 0);
    $supplierId = data_get($line, 'supplier_id');
    $schemeDiscPercent = data_get($line, 'scheme_disc_percent', 0);
    $schemeAmount = data_get($line, 'scheme_amount', 0);
    $schemeOthers = data_get($line, 'scheme_others', 0);
    $netAmount = data_get($line, 'net_amount', 0);
@endphp

<tr class="item-row" data-row="{{ $rowId }}">
    <td class="text-center align-middle bg-light">
        <span class="row-sno font-weight-bold">{{ is_numeric($rowId) ? $rowId + 1 : '__SNO__' }}</span>
    </td>
    <td style="min-width: 145px;">
        <input type="text" 
               class="form-control form-control-sm item-code-input" 
               value="{{ $displayCode }}" 
               placeholder="Code / Barcode" 
               autocomplete="off"
               title="Enter code or click/tab to search">
    </td>
    <td style="min-width: 260px;">
        <input type="text" 
               class="form-control form-control-sm item-desc bg-light font-weight-bold text-truncate" 
               readonly 
               tabindex="-1"
               value="{{ $item ? ($item->name . ($item->ean_upc_code ? ' [Code: ' . $item->ean_upc_code . ']' : '')) : '' }}" 
               placeholder="Product Description (auto-filled)"
               title="Product description (auto-filled on code entry)">
        <input type="hidden" name="items[{{ $rowId }}][item_id]" class="item-select item-id-hidden" value="{{ $itemId }}" required>
    </td>
    <td style="min-width: 130px;">
        <input type="date" 
               name="items[{{ $rowId }}][exp_date]" 
               value="{{ $expDate }}" 
               class="form-control form-control-sm">
    </td>
    <td style="min-width: 85px;">
        <input type="number" 
               step="0.001" 
               min="0.001" 
               name="items[{{ $rowId }}][qty]" 
               value="{{ $qty }}" 
               class="form-control form-control-sm item-qty text-right font-weight-bold" 
               placeholder="0" 
               required>
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $rowId }}][cost_price]" 
               value="{{ $costPrice }}" 
               class="form-control form-control-sm item-cost text-right" 
               placeholder="0.00" 
               required>
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $rowId }}][sell_price]" 
               value="{{ $sellPrice }}" 
               class="form-control form-control-sm item-sell text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $rowId }}][mrp]" 
               value="{{ $mrp }}" 
               class="form-control form-control-sm item-mrp text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 75px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               max="100" 
               name="items[{{ $rowId }}][disc_percent]" 
               value="{{ $discPercent }}" 
               class="form-control form-control-sm item-disc-percent text-right" 
               placeholder="0">
    </td>
    <td style="min-width: 90px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $rowId }}][disc_amount]" 
               value="{{ $discAmount }}" 
               class="form-control form-control-sm item-disc-amount text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 75px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               max="100" 
               name="items[{{ $rowId }}][gst_percent]" 
               value="{{ $gstPercent }}" 
               class="form-control form-control-sm item-gst-percent text-right" 
               placeholder="0">
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               name="items[{{ $rowId }}][gst_tax_amount]" 
               value="{{ $gstTaxAmount }}" 
               class="form-control form-control-sm item-gst-amount text-right bg-light" 
               placeholder="0.00" 
               readonly>
    </td>
    <td style="min-width: 160px;">
        <select name="items[{{ $rowId }}][supplier_id]" class="form-control form-control-sm item-supplier">
            <option value="">-- Default --</option>
            @foreach ($suppliers as $sId => $sName)
                <option value="{{ $sId }}" @selected($supplierId == $sId)>{{ $sName }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width: 80px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               max="100" 
               name="items[{{ $rowId }}][scheme_disc_percent]" 
               value="{{ $schemeDiscPercent }}" 
               class="form-control form-control-sm item-scheme-percent text-right" 
               placeholder="0">
    </td>
    <td style="min-width: 90px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $rowId }}][scheme_amount]" 
               value="{{ $schemeAmount }}" 
               class="form-control form-control-sm item-scheme-amount text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 90px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $rowId }}][scheme_others]" 
               value="{{ $schemeOthers }}" 
               class="form-control form-control-sm item-scheme-others text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 110px;">
        <input type="number" 
               step="0.01" 
               name="items[{{ $rowId }}][net_amount]" 
               value="{{ $netAmount }}" 
               class="form-control form-control-sm item-net font-weight-bold text-right text-success bg-light" 
               placeholder="0.00" 
               readonly>
    </td>
    <td class="text-center align-middle" style="width: 45px;">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove" title="Delete row">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
