<tr>
    <td>
        <select name="items[{{ $index }}][item_id]" class="form-control form-control-sm">
            <option value="">Select item</option>
            @foreach ($items as $id => $name)
                <option value="{{ $id }}" @selected(($line->item_id ?? null) == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </td>
    <td><input type="date" name="items[{{ $index }}][exp_date]" value="{{ optional($line->exp_date ?? null)->format('Y-m-d') }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.001" name="items[{{ $index }}][qty]" value="{{ $line->qty ?? '' }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.001" name="items[{{ $index }}][free_qty]" value="{{ $line->free_qty ?? 0 }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][cost_price]" value="{{ $line->cost_price ?? '' }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][sell_price]" value="{{ $line->sell_price ?? '' }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][mrp]" value="{{ $line->mrp ?? '' }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][disc_percent]" value="{{ $line->disc_percent ?? 0 }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][disc_amount]" value="{{ $line->disc_amount ?? 0 }}" class="form-control form-control-sm"></td>
    <td><input type="number" step="0.01" name="items[{{ $index }}][gst_percent]" value="{{ $line->gst_percent ?? 0 }}" class="form-control form-control-sm"></td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger pinv-remove-row"><i class="fas fa-times"></i></button>
    </td>
</tr>
