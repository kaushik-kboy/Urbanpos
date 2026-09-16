@php
    $rowId = $index ?? 0;
    $itemId = data_get($line, 'item_id');
    $expDate = data_get($line, 'exp_date');
    if ($expDate instanceof \DateTimeInterface) {
        $expDate = $expDate->format('Y-m-d');
    }
    $qty = data_get($line, 'qty', '');
    $costPrice = data_get($line, 'cost_price', '');
    $discPercent = data_get($line, 'disc_percent', 0);
    $discAmount = data_get($line, 'disc_amount', 0);
    $gstPercent = data_get($line, 'gst_percent', 0);
    $netAmount = data_get($line, 'net_amount', 0);
@endphp
<tr class="pr-item-row" data-row-index="{{ $rowId }}">
    <td>
        <select name="items[{{ $rowId }}][item_id]" class="form-control form-control-sm pr-item-select select2" required>
            <option value="">-- Select Item --</option>
            @foreach ($items as $id => $name)
                <option value="{{ $id }}" @selected($itemId == $id)>{{ $name }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <input type="date" name="items[{{ $rowId }}][exp_date]" value="{{ $expDate }}" class="form-control form-control-sm">
    </td>
    <td>
        <input type="number" step="0.001" min="0.001" name="items[{{ $rowId }}][qty]" value="{{ $qty }}" class="form-control form-control-sm text-right pr-qty" placeholder="0" required>
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="items[{{ $rowId }}][cost_price]" value="{{ $costPrice }}" class="form-control form-control-sm text-right pr-cost" placeholder="0.00" required>
    </td>
    <td>
        <input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][disc_percent]" value="{{ $discPercent }}" class="form-control form-control-sm text-right pr-disc-percent" placeholder="0">
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="items[{{ $rowId }}][disc_amount]" value="{{ $discAmount }}" class="form-control form-control-sm text-right pr-disc-amount" placeholder="0.00">
    </td>
    <td>
        <input type="number" step="0.01" min="0" name="items[{{ $rowId }}][gst_percent]" value="{{ $gstPercent }}" class="form-control form-control-sm text-right pr-gst-percent" placeholder="0">
    </td>
    <td class="text-right align-middle font-weight-bold text-dark">
        ₹<span class="pr-net-amount">{{ number_format((float) $netAmount, 2) }}</span>
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-xs btn-outline-danger pr-row-remove" title="Remove row">
            <i class="fas fa-times"></i>
        </button>
    </td>
</tr>
