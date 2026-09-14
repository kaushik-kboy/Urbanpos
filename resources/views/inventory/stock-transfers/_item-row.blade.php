<tr class="item-row" data-row="{{ $index }}">
    <td class="text-center align-middle bg-light">
        <span class="row-sno font-weight-bold">{{ is_numeric($index) ? $index + 1 : '__SNO__' }}</span>
    </td>
    <td style="min-width: 145px;">
        <div class="input-group input-group-sm">
            <input type="text"
                   class="form-control form-control-sm item-code-input"
                   placeholder="Scan/Code"
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
        </select>
    </td>
    <td style="min-width: 130px;">
        <input type="date"
               name="items[{{ $index }}][exp_date]"
               class="form-control form-control-sm">
    </td>
    <td style="min-width: 100px;">
        <input type="text" class="form-control form-control-sm item-available text-right bg-light" value="0.000" readonly tabindex="-1">
    </td>
    <td style="min-width: 90px;">
        <input type="number"
               step="0.001"
               min="0.001"
               name="items[{{ $index }}][qty]"
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
