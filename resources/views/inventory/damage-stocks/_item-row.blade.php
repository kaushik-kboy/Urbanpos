@php
    $idx = $index ?? 0;
    $item = $line?->item ?? null;
    $itemId = $line?->item_id ?? null;
    $displayCode = $item?->ean_upc_code ?: ($item?->item_code ?: '');
    $displayText = $item ? "{$item->name}" . ($displayCode ? " [{$displayCode}]" : "") : '';
    $qty = $line?->qty ?? '';
    $costPrice = $line?->cost_price ?? ($item?->cost_price ?? '');
    $sellPrice = $line?->sell_price ?? ($item?->sell_price ?? '');
    $mrp = $line?->mrp ?? ($item?->mrp ?? '');
    $gstPercent = $line?->gst_percent ?? ($item?->gstTax?->percentage ?? 0);
    $gstTaxAmount = $line?->gst_tax_amount ?? 0;
    $netAmount = $line?->net_amount ?? 0;
@endphp

<tr class="item-row">
    {{-- S.No --}}
    <td class="text-center align-middle row-sno font-weight-bold text-muted" style="width: 45px;">
        {{ is_numeric($idx) ? $idx + 1 : 1 }}
    </td>

    {{-- Item Code (Barcode scanner or manual code input) --}}
    <td style="width: 155px;">
        <input type="text" class="form-control form-control-sm item-code-input text-monospace font-weight-bold" 
               placeholder="Scan / Code" 
               value="{{ $displayCode }}" 
               autocomplete="off">
    </td>

    {{-- Item Description (Select2 AJAX) --}}
    <td style="min-width: 280px;">
        <input type="hidden" name="items[{{ $idx }}][item_id]" class="item-id-hidden" value="{{ $itemId }}">
        <select class="form-control form-control-sm item-select select2" style="width: 100%;">
            @if ($itemId && $displayText)
                <option value="{{ $itemId }}" selected>{{ $displayText }}</option>
            @else
                <option value="">-- Search Item / Description --</option>
            @endif
        </select>
    </td>

    {{-- Exp Date --}}
    <td style="width: 130px;">
        <input type="date" name="items[{{ $idx }}][exp_date]" 
               value="{{ optional($line->exp_date ?? null)->format('Y-m-d') }}" 
               class="form-control form-control-sm item-exp-date">
    </td>

    {{-- Qty --}}
    <td style="width: 95px;">
        <input type="number" step="0.001" min="0.001" name="items[{{ $idx }}][qty]" 
               value="{{ $qty }}" 
               placeholder="0.000" 
               class="form-control form-control-sm item-qty text-right font-weight-bold" required>
    </td>

    {{-- Cost Price --}}
    <td style="width: 110px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][cost_price]" 
               value="{{ is_numeric($costPrice) ? number_format((float)$costPrice, 2, '.', '') : '' }}" 
               placeholder="0.00" 
               class="form-control form-control-sm item-cost text-right" required>
    </td>

    {{-- Sell Price --}}
    <td style="width: 110px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][sell_price]" 
               value="{{ is_numeric($sellPrice) ? number_format((float)$sellPrice, 2, '.', '') : '' }}" 
               placeholder="0.00" 
               class="form-control form-control-sm item-sell text-right text-muted">
    </td>

    {{-- MRP --}}
    <td style="width: 110px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][mrp]" 
               value="{{ is_numeric($mrp) ? number_format((float)$mrp, 2, '.', '') : '' }}" 
               placeholder="0.00" 
               class="form-control form-control-sm item-mrp text-right">
    </td>

    {{-- GST % --}}
    <td style="width: 80px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][gst_percent]" 
               value="{{ is_numeric($gstPercent) ? number_format((float)$gstPercent, 2, '.', '') : '0.00' }}" 
               class="form-control form-control-sm item-gst-percent text-right">
    </td>

    {{-- GST Tax Amt --}}
    <td style="width: 110px;">
        <input type="text" readonly 
               value="{{ is_numeric($gstTaxAmount) ? number_format((float)$gstTaxAmount, 2, '.', '') : '0.00' }}" 
               class="form-control form-control-sm item-gst-amount text-right bg-light text-muted">
    </td>

    {{-- Net Amt --}}
    <td style="width: 125px;">
        <input type="text" readonly 
               value="{{ is_numeric($netAmount) ? number_format((float)$netAmount, 2, '.', '') : '0.00' }}" 
               class="form-control form-control-sm item-net-amount text-right bg-light font-weight-bold text-danger">
    </td>

    {{-- Action --}}
    <td class="text-center align-middle" style="width: 45px;">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove" title="Remove Row">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
