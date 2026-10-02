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
            $expDateVal = \Carbon\Carbon::parse($line->exp_date)->format('d/m/Y');
        } catch (\Throwable) {
            $expDateVal = (string) $line->exp_date;
        }
    }
    $qtyVal = isset($line->qty) && $line->qty != 0 ? $line->qty : '';
    $availableVal = isset($line->available_qty) ? number_format((float)$line->available_qty, 3, '.', '') : '0.000';
    $batchNoVal = data_get($line, 'batch_no', '');
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
    <td style="min-width: 250px;" data-col-key="item">
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
    </td>
    <td style="min-width: 120px;" data-col-key="batch">
        <div class="d-flex align-items-center">
            <input type="hidden" name="items[{{ $index }}][batch_no]" class="item-batch-no" value="{{ $batchNoVal }}">
            <span class="badge badge-info item-batch-badge px-2 py-1 text-truncate font-weight-bold" style="max-width: 85px;" title="{{ $batchNoVal ?: 'No Batch' }}">
                <i class="fas fa-layer-group mr-1"></i><span class="item-batch-text">{{ $batchNoVal ?: '—' }}</span>
            </span>
            <button type="button" class="btn btn-xs btn-outline-primary st-btn-choose-batch ml-1" title="Select / Change Batch">
                <i class="fas fa-edit"></i>
            </button>
        </div>
    </td>
    <td style="min-width: 130px;" data-col-key="expiry">
        <input type="text"
               name="items[{{ $index }}][exp_date]"
               value="{{ $expDateVal }}"
               class="form-control form-control-sm item-exp-date text-center font-weight-bold bg-light"
               placeholder="DD/MM/YYYY"
               autocomplete="off"
               title="Expiry Date (DD/MM/YYYY)">
    </td>
    <td style="min-width: 100px;" data-col-key="available">
        <input type="text" class="form-control form-control-sm item-available text-right bg-light" value="{{ $availableVal }}" readonly tabindex="-1">
    </td>
    <td style="min-width: 90px;" data-col-key="qty">
        <input type="number"
               step="0.001"
               min="0"
               name="items[{{ $index }}][qty]"
               value="{{ $qtyVal }}"
               class="form-control form-control-sm item-qty text-right font-weight-bold"
               placeholder="0">
    </td>
    <td class="text-center align-middle" style="width: 45px;" data-col-key="actions">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove" title="Delete row">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
