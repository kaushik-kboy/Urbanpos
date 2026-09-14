<tr>
    <td>
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm select2 pinv-item-select" required data-placeholder="Select item">
            <option value="">Select item</option>
            @foreach ($items as $item)
                @php
                    $itemId = is_object($item) ? $item->id : $item;
                    $itemName = is_object($item) ? $item->name : $item;
                    $itemCode = is_object($item) ? ($item->item_code ?? '') : '';
                    $costPrice = is_object($item) ? (float)($item->cost_price ?? 0) : 0;
                    $sellPrice = is_object($item) ? (float)($item->sell_price ?? 0) : 0;
                    $mrp = is_object($item) ? (float)($item->mrp ?? 0) : 0;
                    $gstPercent = is_object($item) ? (float)($item->gstTax?->percentage ?? 0) : 0;
                    $batchExpiry = is_object($item) ? ($item->batch_expiry_details ?? 'Not Required') : 'Not Required';
                    $shelfLife = is_object($item) ? ($item->shelf_life_days ?? '') : '';
                @endphp
                <option value="{{ $itemId }}"
                        data-cost="{{ $costPrice }}"
                        data-sell="{{ $sellPrice }}"
                        data-mrp="{{ $mrp }}"
                        data-gst="{{ $gstPercent }}"
                        data-batch-expiry="{{ $batchExpiry }}"
                        data-shelf-life="{{ $shelfLife }}"
                        @selected(($line->item_id ?? null) == $itemId)>
                    {{ $itemName }}{{ $itemCode ? ' ['.$itemCode.']' : '' }}
                </option>
            @endforeach
        </select>
    </td>
    @php
        $selectedItemId = $line->item_id ?? null;
        $selectedItem = null;
        if ($selectedItemId && isset($items)) {
            $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
                ? collect($items)->firstWhere('id', $selectedItemId)
                : null;
        }
        $isExpRequired = $selectedItem && in_array($selectedItem->batch_expiry_details ?? '', ['Mandatory', 'Days', 'Month']);
    @endphp
    <td>
        <input type="date"
               name="items[{{ $index }}][exp_date]"
               value="{{ optional($line->exp_date ?? null)->format('Y-m-d') }}"
               class="form-control form-control-sm pinv-exp-date {{ $isExpRequired ? 'border-danger' : '' }}"
               @if($isExpRequired) required @endif
               title="{{ $isExpRequired ? 'Expiry date is mandatory for this item' : 'Expiry date (optional)' }}">
        <small class="pinv-exp-badge text-danger font-weight-bold {{ $isExpRequired ? '' : 'd-none' }}"><i class="fas fa-exclamation-circle"></i> Required</small>
    </td>
    <td><input type="number" step="0.001" name="items[{{ $index }}][qty]" value="{{ $line->qty ?? '' }}" class="form-control form-control-sm pinv-qty" required placeholder="0"></td>
    <td><input type="number" step="0.001" name="items[{{ $index }}][free_qty]" value="{{ $line->free_qty ?? 0 }}" class="form-control form-control-sm pinv-free-qty" placeholder="0"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][cost_price]" value="{{ $line->cost_price ?? '' }}" class="form-control form-control-sm pinv-cost" required placeholder="0.00"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $line->sell_price ?? '' }}" class="form-control form-control-sm pinv-sell" placeholder="0.00"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $line->mrp ?? '' }}" class="form-control form-control-sm pinv-mrp" placeholder="0.00"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $line->disc_percent ?? 0 }}" class="form-control form-control-sm pinv-disc-percent" placeholder="0"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $line->disc_amount ?? 0 }}" class="form-control form-control-sm pinv-disc-amount" placeholder="0.00"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][gst_percent]" value="{{ $line->gst_percent ?? 0 }}" class="form-control form-control-sm pinv-gst" placeholder="0"></td>
    <td class="text-right align-middle font-weight-bold pinv-row-net">{{ number_format($line->net_amount ?? 0, 2) }}</td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger pinv-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
