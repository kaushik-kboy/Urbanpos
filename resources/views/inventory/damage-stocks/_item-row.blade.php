@php
    $idx = $index ?? 0;
    $itemId = data_get($line, 'item_id');
    $item = is_object($line) && isset($line->item) ? $line->item : ($itemId ? \App\Models\Item::find($itemId) : null);
    $displayCode = $item?->item_code ?: ($item?->ean_upc_code ?: '');
    $displayText = $item ? "{$item->name}" . ($displayCode ? " [{$displayCode}]" : "") : '';
    $expDate = data_get($line, 'exp_date');
    if ($expDate instanceof \DateTimeInterface) {
        $expDate = $expDate->format('Y-m-d');
    }
    $qty = data_get($line, 'qty', '');
    $costPrice = data_get($line, 'cost_price', $item?->cost_price ?? '');
    $sellPrice = data_get($line, 'sell_price', $item?->sell_price ?? '');
    $mrp = data_get($line, 'mrp', $item?->mrp ?? '');
    $gstPercent = data_get($line, 'gst_percent', $item?->gstTax?->percentage ?? 0);
    $gstTaxAmount = data_get($line, 'gst_tax_amount', 0);
    $netAmount = data_get($line, 'net_amount', 0);
@endphp

<tr class="item-row">
    {{-- S.No --}}
    <td class="text-center align-middle row-sno font-weight-bold text-muted" style="width: 45px;">
        {{ is_numeric($idx) ? $idx + 1 : 1 }}
    </td>

    {{-- Item Code (Barcode scanner or manual code input) --}}
    <td style="width: 155px;">
        <input type="text" class="form-control form-control-sm item-code-input text-monospace font-weight-bold" 
               placeholder="Code / Barcode" 
               value="{{ $displayCode }}" 
               autocomplete="off"
               title="Enter code or click/tab to search">
    </td>

    {{-- Item Description (Standard auto-filled read-only input) --}}
    <td style="min-width: 260px;">
        <input type="text" 
               class="form-control form-control-sm item-desc bg-light font-weight-bold text-truncate" 
               readonly 
               tabindex="-1"
               value="{{ $displayText }}" 
               placeholder="Product Description (auto-filled)"
               title="Product description (auto-filled on code entry)">
        <input type="hidden" name="items[{{ $idx }}][item_id]" class="item-id-hidden" value="{{ $itemId }}">
        <input type="hidden" name="items[{{ $idx }}][batch_no]" class="item-batch-no" value="{{ data_get($line, 'batch_no', '') }}">
        <div class="item-batch-display mt-1 {{ empty(data_get($line, 'batch_no')) ? 'd-none' : '' }}">
            <span class="badge badge-info px-2 py-1"><i class="fas fa-layer-group mr-1"></i>Batch: <span class="item-batch-text">{{ data_get($line, 'batch_no', '') }}</span></span>
        </div>
    </td>

    {{-- Exp Date with Batch Selector --}}
    @php
        $expDateVal = '';
        if (!empty($expDate)) {
            try {
                $expDateVal = \Carbon\Carbon::parse($expDate)->format('d-m-Y');
            } catch (\Throwable) {
                $expDateVal = (string) $expDate;
            }
        }
        $batchNoVal = data_get($line, 'batch_no', '');
    @endphp
    <td style="width: 215px; min-width: 205px;" title="Expiry date (Auto-filled from Batch)">
        <div class="input-group input-group-sm">
            <input type="text" 
                   name="items[{{ $idx }}][exp_date]" 
                   value="{{ $expDateVal }}" 
                   data-original-exp="{{ $expDateVal }}"
                   readonly
                   tabindex="-1"
                   style="pointer-events: none;"
                   class="form-control form-control-sm item-exp-date text-center font-weight-bold bg-light"
                   placeholder="DD/MM/YYYY"
                   autocomplete="off"
                   title="Expiry date (Auto-filled from Batch)">
            <div class="input-group-append ds-batch-btn-wrap {{ empty($batchNoVal) ? 'd-none' : '' }}" style="pointer-events: auto;">
                <button type="button" tabindex="-1" class="btn btn-warning btn-xs ds-btn-choose-batch px-2 font-weight-bold" title="{{ $batchNoVal ? 'Batch: '.$batchNoVal.' (Click to choose/change batch)' : 'Click to choose batch' }}">
                    <i class="fas fa-layer-group mr-1"></i><span class="ds-batch-badge-text item-batch-text">{{ $batchNoVal ?: 'Batch' }}</span>
                </button>
            </div>
        </div>
    </td>

    {{-- Qty --}}
    <td style="width: 75px;">
        <input type="number" step="0.001" min="0" name="items[{{ $idx }}][qty]" 
               value="{{ $qty }}" 
               placeholder="0.000" 
               class="form-control form-control-sm item-qty text-right font-weight-bold px-1">
    </td>

    {{-- Cost Price --}}
    <td style="width: 85px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][cost_price]" 
               value="{{ is_numeric($costPrice) ? number_format((float)$costPrice, 2, '.', '') : '' }}" 
               placeholder="0.00" 
               class="form-control form-control-sm item-cost text-right px-1">
    </td>

    {{-- Sell Price --}}
    <td style="width: 85px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][sell_price]" 
               value="{{ is_numeric($sellPrice) ? number_format((float)$sellPrice, 2, '.', '') : '' }}" 
               placeholder="0.00" 
               class="form-control form-control-sm item-sell text-right text-muted px-1">
    </td>

    {{-- MRP --}}
    <td style="width: 85px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][mrp]" 
               value="{{ is_numeric($mrp) ? number_format((float)$mrp, 2, '.', '') : '' }}" 
               placeholder="0.00" 
               class="form-control form-control-sm item-mrp text-right px-1">
    </td>

    {{-- GST % --}}
    <td style="width: 65px;">
        <input type="number" step="0.01" min="0" name="items[{{ $idx }}][gst_percent]" 
               value="{{ is_numeric($gstPercent) ? number_format((float)$gstPercent, 2, '.', '') : '0.00' }}" 
               class="form-control form-control-sm item-gst-percent text-right px-1">
    </td>

    {{-- GST Tax Amt --}}
    <td style="width: 85px;">
        <input type="text" readonly 
               value="{{ is_numeric($gstTaxAmount) ? number_format((float)$gstTaxAmount, 2, '.', '') : '0.00' }}" 
               class="form-control form-control-sm item-gst-amount text-right bg-light text-muted px-1">
    </td>

    {{-- Net Amt --}}
    <td style="width: 95px;">
        <input type="text" readonly 
               value="{{ is_numeric($netAmount) ? number_format((float)$netAmount, 2, '.', '') : '0.00' }}" 
               class="form-control form-control-sm item-net-amount text-right bg-light font-weight-bold text-danger px-1">
    </td>

    {{-- Action --}}
    <td class="text-center align-middle" style="width: 45px;">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove" title="Remove Row">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
