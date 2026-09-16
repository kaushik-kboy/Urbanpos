@php
    $rowId = $index;
    $itemId = data_get($line, 'item_id');
    $qty = data_get($line, 'qty', '');
    $freeQty = data_get($line, 'free_qty', 0);
    $costPrice = data_get($line, 'cost_price', '');
    $sellPrice = data_get($line, 'sell_price', '');
    $mrp = data_get($line, 'mrp', '');
    $discPercent = data_get($line, 'disc_percent', 0);
    $discAmount = data_get($line, 'disc_amount', 0);
    $gstPercent = data_get($line, 'gst_percent', 0);
@endphp
<tr>
    <td>
        <select name="items[{{ $rowId }}][item_id]" class="form-control form-control-sm" required>
            <option value="">Select item</option>
            @foreach ($items as $id => $name)
                <option value="{{ $id }}" @selected($itemId == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="number" step="0.001" min="0.001" name="items[{{ $rowId }}][qty]" value="{{ $qty }}" class="form-control form-control-sm text-right" required></td>
    <td><input type="number" step="0.001" min="0" name="items[{{ $rowId }}][free_qty]" value="{{ $freeQty }}" class="form-control form-control-sm text-right"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][cost_price]" value="{{ $costPrice }}" class="form-control form-control-sm text-right" required></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][sell_price]" value="{{ $sellPrice }}" class="form-control form-control-sm text-right"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][mrp]" value="{{ $mrp }}" class="form-control form-control-sm text-right"></td>
    <td><input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][disc_percent]" value="{{ $discPercent }}" class="form-control form-control-sm text-right"></td>
    <td><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][disc_amount]" value="{{ $discAmount }}" class="form-control form-control-sm text-right"></td>
    <td><input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][gst_percent]" value="{{ $gstPercent }}" class="form-control form-control-sm text-right"></td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger po-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
