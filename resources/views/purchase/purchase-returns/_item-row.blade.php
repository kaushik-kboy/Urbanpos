@php
    $rowId = $index ?? 0;
    $itemId = data_get($line, 'item_id');
    $itemObj = null;
    if ($line instanceof \App\Models\PurchaseReturnItem) {
        $itemObj = $line->item;
    } elseif ($itemId) {
        $itemObj = \App\Models\Item::find($itemId);
    }
    $itemCode = $itemObj ? ($itemObj->item_code ?: $itemObj->ean_upc_code) : data_get($line, 'item_code', '');
    $itemName = $itemObj ? $itemObj->name : data_get($line, 'item_name', '');

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
    <td style="min-width: 140px;" data-col-key="code">
        <input type="hidden" name="items[{{ $rowId }}][item_id]" class="pr-item-id" value="{{ $itemId }}">
        <div class="input-group input-group-sm">
            <input type="text" class="form-control form-control-sm pr-item-code font-weight-bold text-uppercase" placeholder="Code / Barcode" value="{{ $itemCode }}" autocomplete="off" title="Enter code or click/tab to search">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-secondary pr-search-btn" title="Search Item (Popup)" tabindex="-1">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </td>
    <td style="min-width: 220px;" data-col-key="item">
        <input type="text" class="form-control form-control-sm pr-item-desc bg-light font-weight-bold text-truncate" value="{{ $itemName }}" placeholder="Product Description (auto-filled)" readonly tabindex="-1">
    </td>
    <td style="width: 130px;" data-col-key="expiry">
        <input type="date" name="items[{{ $rowId }}][exp_date]" value="{{ $expDate }}" class="form-control form-control-sm pr-exp-date">
    </td>
    <td style="width: 95px;" data-col-key="qty">
        <input type="number" step="0.001" min="0" name="items[{{ $rowId }}][qty]" value="{{ $qty }}" class="form-control form-control-sm text-right pr-qty font-weight-bold" placeholder="0.000">
    </td>
    <td style="width: 110px;" data-col-key="cost_price">
        <input type="number" step="0.01" min="0" name="items[{{ $rowId }}][cost_price]" value="{{ $costPrice }}" class="form-control form-control-sm text-right pr-cost font-weight-bold" placeholder="0.00">
    </td>
    <td style="width: 85px;" data-col-key="disc_percent">
        <input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][disc_percent]" value="{{ $discPercent }}" class="form-control form-control-sm text-right pr-disc-percent" placeholder="0">
    </td>
    <td style="width: 95px;" data-col-key="disc_amt">
        <input type="number" step="0.01" min="0" name="items[{{ $rowId }}][disc_amount]" value="{{ $discAmount }}" class="form-control form-control-sm text-right pr-disc-amount" placeholder="0.00">
    </td>
    <td style="width: 85px;" data-col-key="gst_percent">
        <input type="number" step="0.01" min="0" name="items[{{ $rowId }}][gst_percent]" value="{{ $gstPercent }}" readonly tabindex="-1" class="form-control form-control-sm text-right pr-gst-percent bg-light" placeholder="0" title="GST % (Read-only)">
    </td>
    <td class="text-right align-middle font-weight-bold text-dark" style="width: 110px;" data-col-key="net_amt">
        ₹<span class="pr-net-amount">{{ number_format((float) $netAmount, 2) }}</span>
    </td>
    <td class="text-center align-middle" style="width: 40px;" data-col-key="actions">
        <button type="button" class="btn btn-xs btn-outline-danger pr-row-remove" title="Remove row">
            <i class="fas fa-times"></i>
        </button>
    </td>
</tr>
