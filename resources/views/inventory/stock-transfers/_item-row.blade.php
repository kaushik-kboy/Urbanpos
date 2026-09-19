@php
    $line = isset($line) ? (is_array($line) ? (object) $line : $line) : null;
    $selectedItemId = $line->item_id ?? null;
    $selectedItem = null;
    if ($selectedItemId) {
        $selectedItem = \App\Models\Item::find($selectedItemId);
    }
    $itemCodeVal = $line->code ?? ($selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? ''));
    $expDateVal = '';
    if (!empty($line->exp_date)) {
        $expDateVal = is_string($line->exp_date) ? $line->exp_date : optional($line->exp_date)->format('Y-m-d');
    }
    $qtyVal = isset($line->qty) && $line->qty != 0 ? $line->qty : '';
    $availableVal = isset($line->available_qty) ? number_format((float)$line->available_qty, 3, '.', '') : '0.000';
@endphp
<tr class="item-row" data-row="{{ $index }}">
    <td class="text-center align-middle bg-light">
        <span class="row-sno font-weight-bold">{{ is_numeric($index) ? $index + 1 : '__SNO__' }}</span>
    </td>
    <td style="min-width: 145px;">
        <div class="input-group input-group-sm">
            <input type="text"
                   class="form-control form-control-sm item-code-input"
                   placeholder="Scan/Code"
                   value="{{ $itemCodeVal }}"
                   autocomplete="off">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary btn-sm open-item-modal" title="Search Items Popup (F2)" tabindex="-1">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </td>
    <td style="min-width: 260px;">
        <select name="items[{{ $index }}][item_id]"
                class="form-control form-control-sm item-select"
                style="width: 100%;"
                required>
            <option value="">Search item name / code...</option>
            @if($selectedItem)
                @php
                    $displayCode = $selectedItem->ean_upc_code ?: ($selectedItem->item_code ? "Item: {$selectedItem->item_code}" : "");
                    $codeStr = $displayCode ? " [{$displayCode}]" : "";
                @endphp
                <option value="{{ $selectedItem->id }}" selected>{{ $selectedItem->name }}{{ $codeStr }}</option>
            @endif
        </select>
    </td>
    <td style="min-width: 130px;">
        <input type="text"
               name="items[{{ $index }}][exp_date]"
               value="{{ $expDateVal }}"
               class="form-control form-control-sm datepicker item-exp-date"
               placeholder="YYYY-MM-DD"
               autocomplete="off">
    </td>
    <td style="min-width: 100px;">
        <input type="text" class="form-control form-control-sm item-available text-right bg-light" value="{{ $availableVal }}" readonly tabindex="-1">
    </td>
    <td style="min-width: 90px;">
        <input type="number"
               step="0.001"
               min="0.001"
               name="items[{{ $index }}][qty]"
               value="{{ $qtyVal }}"
               class="form-control form-control-sm item-qty text-right font-weight-bold"
               placeholder="0"
               required>
    </td>
    <td class="text-center align-middle" style="width: 45px;">
        <button type="button" class="btn btn-xs btn-outline-danger row-remove" title="Delete row">
            <i class="fas fa-trash-alt"></i>
        </button>
    </td>
</tr>
