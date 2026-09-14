<tr class="item-row" data-row="{{ $index }}">
    <td class="text-center align-middle bg-light">
        <span class="row-sno font-weight-bold">{{ is_numeric($index) ? $index + 1 : '__SNO__' }}</span>
    </td>
    <td style="min-width: 145px;">
        <div class="input-group input-group-sm">
            <input type="text" 
                   class="form-control form-control-sm item-code-input" 
                   value="{{ $line?->item?->ean_upc_code ?: ($line?->item?->item_code ?? '') }}" 
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
            @if ($line?->item)
                <option value="{{ $line->item_id }}" selected>
                    {{ $line->item->name }} {{ $line->item->ean_upc_code ? '[Code: ' . $line->item->ean_upc_code . ']' : '' }}
                </option>
            @else
                <option value="">Search item name / code...</option>
            @endif
        </select>
    </td>
    <td style="min-width: 130px;">
        <input type="date" 
               name="items[{{ $index }}][exp_date]" 
               value="{{ optional($line?->exp_date)->format('Y-m-d') }}" 
               class="form-control form-control-sm">
    </td>
    <td style="min-width: 85px;">
        <input type="number" 
               step="0.001" 
               min="0.001" 
               name="items[{{ $index }}][qty]" 
               value="{{ $line?->qty ?? '' }}" 
               class="form-control form-control-sm item-qty text-right font-weight-bold" 
               placeholder="0" 
               required>
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $index }}][cost_price]" 
               value="{{ $line?->cost_price ?? '' }}" 
               class="form-control form-control-sm item-cost text-right" 
               placeholder="0.00" 
               required>
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $index }}][sell_price]" 
               value="{{ $line?->sell_price ?? '' }}" 
               class="form-control form-control-sm item-sell text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $index }}][mrp]" 
               value="{{ $line?->mrp ?? '' }}" 
               class="form-control form-control-sm item-mrp text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 75px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               max="100" 
               name="items[{{ $index }}][disc_percent]" 
               value="{{ $line?->disc_percent ?? 0 }}" 
               class="form-control form-control-sm item-disc-percent text-right" 
               placeholder="0">
    </td>
    <td style="min-width: 90px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $index }}][disc_amount]" 
               value="{{ $line?->disc_amount ?? 0 }}" 
               class="form-control form-control-sm item-disc-amount text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 75px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               max="100" 
               name="items[{{ $index }}][gst_percent]" 
               value="{{ $line?->gst_percent ?? 0 }}" 
               class="form-control form-control-sm item-gst-percent text-right" 
               placeholder="0">
    </td>
    <td style="min-width: 95px;">
        <input type="number" 
               step="0.01" 
               name="items[{{ $index }}][gst_tax_amount]" 
               value="{{ $line?->gst_tax_amount ?? 0 }}" 
               class="form-control form-control-sm item-gst-amount text-right bg-light" 
               placeholder="0.00" 
               readonly>
    </td>
    <td style="min-width: 160px;">
        <select name="items[{{ $index }}][supplier_id]" class="form-control form-control-sm item-supplier">
            <option value="">-- Default --</option>
            @foreach ($suppliers as $sId => $sName)
                <option value="{{ $sId }}" @selected(($line?->supplier_id ?? null) == $sId)>{{ $sName }}</option>
            @endforeach
        </select>
    </td>
    <td style="min-width: 80px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               max="100" 
               name="items[{{ $index }}][scheme_disc_percent]" 
               value="{{ $line?->scheme_disc_percent ?? 0 }}" 
               class="form-control form-control-sm item-scheme-percent text-right" 
               placeholder="0">
    </td>
    <td style="min-width: 90px;">
        <input type="number" 
               step="0.01" 
               min="0" 
               name="items[{{ $index }}][scheme_amount]" 
               value="{{ $line?->scheme_amount ?? 0 }}" 
               class="form-control form-control-sm item-scheme-amount text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 90px;">
        <input type="number" 
               step="0.01" 
               name="items[{{ $index }}][scheme_others]" 
               value="{{ $line?->scheme_others ?? 0 }}" 
               class="form-control form-control-sm item-scheme-others text-right" 
               placeholder="0.00">
    </td>
    <td style="min-width: 110px;">
        <input type="number" 
               step="0.01" 
               name="items[{{ $index }}][net_amount]" 
               value="{{ $line?->net_amount ?? 0 }}" 
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
