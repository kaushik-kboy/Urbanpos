@php
    $line = isset($line) ? (is_array($line) ? (object) $line : $line) : null;
    $selectedItemId = $line->item_id ?? null;
    $selectedItem = null;
    if ($selectedItemId) {
        $selectedItem = \App\Models\Item::find($selectedItemId);
    }
    $itemCodeVal = $selectedItem ? ($selectedItem->item_code ?: ($selectedItem->ean_upc_code ?: '')) : (data_get($line, 'code') ?: '');
    $selectedItemName = $selectedItem ? ($selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '')) : '';
    $expDateVal = '';
    if (!empty($line->exp_date)) {
        try {
            $expDateVal = \Carbon\Carbon::parse($line->exp_date)->format('d-m-Y');
        } catch (\Throwable) {
            $expDateVal = (string) $line->exp_date;
        }
    }
    $qtyVal = isset($line->qty) && $line->qty != 0 ? $line->qty : '';
    $availableVal = isset($line->available_qty) ? number_format((float)$line->available_qty, 3, '.', '') : '0.000';
    $batchNoVal = data_get($line, 'batch_no', '');
    $unitCostVal = isset($line->unit_cost) && (float)$line->unit_cost > 0 
        ? number_format((float)$line->unit_cost, 2, '.', '') 
        : ($selectedItem ? number_format((float)($selectedItem->cost_price ?: ($selectedItem->landing_cost ?: ($selectedItem->sell_price ?: 0))), 2, '.', '') : '0.00');
    $amountVal = (is_numeric($qtyVal) && is_numeric($unitCostVal)) ? number_format((float)$qtyVal * (float)$unitCostVal, 2, '.', '') : '0.00';
@endphp
<tr class="item-row" data-row="{{ $index }}">
    <td class="text-center align-middle bg-light" data-col-key="seq">
        <span class="row-sno font-weight-bold">{{ is_numeric($index) ? $index + 1 : '__SNO__' }}</span>
    </td>
    <td style="min-width: 140px;" data-col-key="code">
        <input type="text"
               class="form-control form-control-sm item-code-input font-weight-bold"
               placeholder="Code / Barcode"
               value="{{ $itemCodeVal }}"
               autocomplete="off"
               title="Enter item code, scan barcode, or press Tab/Enter/F2 to search">
    </td>
    <td style="min-width: 240px;" data-col-key="item">
        <input type="text"
               class="form-control form-control-sm item-desc-input bg-light font-weight-bold text-truncate"
               readonly
               tabindex="-1"
               value="{{ $selectedItemName }}"
               placeholder="Product Description (auto-filled)"
               title="Product description (Click or F2 to search item)">
        <input type="hidden"
               name="items[{{ $index }}][item_id]"
               class="item-id-input item-select"
               value="{{ $selectedItemId }}">
        <input type="hidden"
               name="items[{{ $index }}][batch_no]"
               class="item-batch-no"
               value="{{ $batchNoVal }}">
    </td>
    <td style="width: 150px;" data-col-key="expiry" title="Expiry date (Read-only)">
        <div class="input-group input-group-sm">
            <input type="text"
                   name="items[{{ $index }}][exp_date]"
                   value="{{ $expDateVal }}"
                   data-original-exp="{{ $expDateVal }}"
                   readonly
                   tabindex="-1"
                   style="pointer-events: none;"
                   class="form-control form-control-sm item-exp-date text-center font-weight-bold bg-light"
                   placeholder="DD/MM/YYYY"
                   autocomplete="off"
                   title="Expiry Date (Read-only)">
            <div class="input-group-append st-batch-btn-wrap {{ empty($batchNoVal) ? 'd-none' : '' }}" style="pointer-events: auto;">
                <button type="button" tabindex="-1" class="btn btn-warning btn-xs st-btn-choose-batch px-2 font-weight-bold" title="{{ $batchNoVal ? 'Batch: '.$batchNoVal.' (Click to choose/change batch)' : 'Multiple batches available! Click to choose batch' }}">
                    <i class="fas fa-layer-group mr-1"></i><span class="st-batch-badge-text item-batch-text">{{ $batchNoVal ?: 'Batch' }}</span>
                </button>
            </div>
        </div>
    </td>
    <td style="min-width: 95px;" data-col-key="available">
        <input type="text" class="form-control form-control-sm item-available text-right bg-light" value="{{ $availableVal }}" readonly tabindex="-1">
    </td>
    <td style="min-width: 95px;" data-col-key="qty">
        <input type="number"
               step="0.001"
               min="0"
               name="items[{{ $index }}][qty]"
               value="{{ $qtyVal }}"
               class="form-control form-control-sm item-qty text-right font-weight-bold"
               placeholder="0">
    </td>
    <td style="min-width: 105px;" data-col-key="unit_cost">
        <input type="number"
               step="0.01"
               min="0"
               name="items[{{ $index }}][unit_cost]"
               value="{{ $unitCostVal }}"
               class="form-control form-control-sm item-cost text-right bg-light"
               placeholder="0.00"
               readonly
               tabindex="-1"
               title="Unit Cost (₹)">
    </td>
    <td style="min-width: 115px;" data-col-key="amount">
        <input type="text"
               class="form-control form-control-sm item-amount text-right bg-light font-weight-bold text-success"
               value="{{ $amountVal }}"
               placeholder="0.00"
               readonly
               tabindex="-1"
               title="Amount (₹)">
    </td>
    <td class="text-center align-middle" style="width: 45px;" data-col-key="actions">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove" title="Delete row">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
