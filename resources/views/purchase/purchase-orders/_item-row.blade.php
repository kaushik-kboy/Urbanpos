@php
    $rowId = $index;
    if (is_array($line)) {
        $line = (object) $line;
    }
    $itemId = data_get($line, 'item_id');
    $selectedItem = null;
    // 1) Prefer the pre-loaded Eloquent relation (avoids any DB query during edit)
    if ($itemId && is_object($line) && isset($line->item) && $line->item instanceof \App\Models\Item) {
        $selectedItem = $line->item;
    }
    // 2) Fall back to scanning the $items collection passed from the create form
    if (!$selectedItem && $itemId && isset($items)) {
        $selectedItem = is_array($items) || $items instanceof \Illuminate\Support\Collection
            ? collect($items)->firstWhere('id', $itemId)
            : null;
    }
    // 3) Last resort: single query (only fires when neither relation nor collection has the item)
    if ($itemId && ! $selectedItem) {
        $selectedItem = \App\Models\Item::with('gstTax:id,percentage')->find($itemId);
    }
    $itemCodeVal = data_get($line, 'item_id') ?: ($selectedItem ? $selectedItem->id : (data_get($line, 'code') ?: ($selectedItem->item_code ?? ($selectedItem->ean_upc_code ?? ''))));
    $selectedItemName = $selectedItem ? ($selectedItem->name . ($selectedItem->item_code ? ' ['.$selectedItem->item_code.']' : '')) : '';

    $qty = data_get($line, 'qty', '');
    $freeQty = data_get($line, 'free_qty', 0);
    $costPrice = data_get($line, 'cost_price', ($selectedItem?->cost_price > 0 ? $selectedItem->cost_price : ''));
    $sellPrice = data_get($line, 'sell_price', ($selectedItem?->sell_price > 0 ? $selectedItem->sell_price : ''));
    $mrp = data_get($line, 'mrp', ($selectedItem?->mrp > 0 ? $selectedItem->mrp : ''));
    $discPercent = data_get($line, 'disc_percent', 0);
    $discAmount = data_get($line, 'disc_amount', 0);
    $gstPercent = data_get($line, 'gst_percent', ($selectedItem?->gstTax?->percentage > 0 ? $selectedItem->gstTax->percentage : 0));

    $numQty = (float) $qty;
    $numFree = (float) $freeQty;
    $numDiscAmt = (float) $discAmount;
    $numCost = (float) $costPrice;
    $numSell = (float) $sellPrice;
    $numMrp = (float) $mrp;
    $numGst = (float) $gstPercent;
    $totalUnits = $numQty + $numFree;

    if (isset($line->effective_cost) && (float)$line->effective_cost > 0) {
        $landingCostVal = round((float)$line->effective_cost, 2);
    } elseif ($totalUnits > 0 && $numCost > 0) {
        $baseAfterDisc = max(0, ($numQty * $numCost) - $numDiscAmt);
        $landingCostVal = round($baseAfterDisc / $totalUnits, 2);
    } else {
        $landingCostVal = $numCost > 0 ? $numCost : ($selectedItem?->landing_cost > 0 ? (float)$selectedItem->landing_cost : null);
    }

    $effectiveCostForProfit = ($landingCostVal !== null && $landingCostVal > 0) ? $landingCostVal : $numCost;
    $baseSellVal = $numSell > 0 ? $numSell : $numMrp;
    $sellExclGstVal = ($baseSellVal > 0) ? ($baseSellVal / (1 + ($numGst / 100))) : 0;
    $marginVal = ($sellExclGstVal > 0 && $effectiveCostForProfit > 0) ? round((($sellExclGstVal - $effectiveCostForProfit) / $sellExclGstVal) * 100, 2) : null;
    $profitVal = ($effectiveCostForProfit > 0 && $sellExclGstVal > 0) ? round((($sellExclGstVal - $effectiveCostForProfit) / $effectiveCostForProfit) * 100, 2) : null;
@endphp
<tr>
    <td class="text-center align-middle font-weight-bold po-sr-no" data-col-key="sr">{{ is_numeric($index) ? $index + 1 : 1 }}</td>
    <td style="min-width: 110px;" data-col-key="code">
        <input type="text" class="form-control form-control-sm po-item-code font-weight-bold" value="{{ $itemCodeVal }}" autocomplete="off" placeholder="Code / Barcode" title="Enter item code or barcode">
    </td>
    <td style="min-width: 220px;" data-col-key="desc">
        <input type="text"
               class="form-control form-control-sm po-item-desc bg-light font-weight-bold text-truncate"
               readonly
               tabindex="-1"
               value="{{ $selectedItemName }}"
               placeholder="Product Description (auto-filled)"
               title="Product description">
        <input type="hidden"
               name="items[{{ $rowId }}][item_id]"
               class="po-item-select"
               value="{{ $itemId }}">
    </td>
    <td style="width: 85px;" data-col-key="stock">
        @php
            $itemStockVal = 0;
            if ($itemId) {
                $bId = $branchId ?? (isset($po) ? $po->branch_id : (session('active_branch_id') ?: auth()->user()?->branch_id));
                if ($bId) {
                    $itemStockVal = (float) (\App\Models\ItemStock::where('item_id', $itemId)->where('branch_id', $bId)->value('quantity') ?? 0);
                }
                if ($itemStockVal <= 0) {
                    $itemStockVal = (float) (\App\Models\ItemStock::where('item_id', $itemId)->sum('quantity') ?? 0);
                }
                if ($itemStockVal <= 0) {
                    $itemStockVal = (float) (\Illuminate\Support\Facades\DB::table('closing_stocks')->where('item_id', $itemId)->sum('closing_stock') ?? 0);
                }
            }
        @endphp
        <input type="text"
               class="form-control form-control-sm po-item-stock text-right bg-light font-weight-bold text-info"
               readonly
               tabindex="-1"
               value="{{ number_format($itemStockVal, 0) }}"
               placeholder="0"
               title="Current stock in this branch">
    </td>
    <td data-col-key="qty"><input type="number" step="0.001" min="0" name="items[{{ $rowId }}][qty]" value="{{ $qty }}" class="form-control form-control-sm po-qty text-right font-weight-bold" placeholder="Qty" autocomplete="off"></td>
    <td data-col-key="free"><input type="number" step="0.001" min="0" name="items[{{ $rowId }}][free_qty]" value="{{ $freeQty }}" class="form-control form-control-sm po-free-qty text-right" placeholder="0" autocomplete="off" title="Free Quantity"></td>
    <td data-col-key="cost"><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][cost_price]" value="{{ $costPrice }}" class="form-control form-control-sm po-cost text-right font-weight-bold" placeholder="0.00" autocomplete="off"></td>
    <td data-col-key="landing_cost"><input type="text" readonly tabindex="-1" class="form-control form-control-sm po-landing-cost bg-light text-right font-weight-bold text-info" value="{{ $landingCostVal !== null && $landingCostVal > 0 ? number_format($landingCostVal, 2) : '' }}" placeholder="0.00" autocomplete="off" title="Landing Cost Price (Effective unit cost after free qty & discount)"></td>
    <td data-col-key="sell"><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][sell_price]" value="{{ $sellPrice }}" class="form-control form-control-sm po-sell text-right" placeholder="0.00" autocomplete="off"></td>
    <td data-col-key="mrp"><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][mrp]" value="{{ $mrp }}" class="form-control form-control-sm po-mrp text-right" placeholder="0.00" autocomplete="off"></td>
    <td data-col-key="margin"><input type="text" readonly tabindex="-1" class="form-control form-control-sm po-margin bg-light text-right font-weight-bold" value="{{ $marginVal !== null && $marginVal != 0 ? number_format($marginVal, 1).'%' : '' }}" placeholder="0.0%" autocomplete="off" title="Margin %"></td>
    <td data-col-key="profit"><input type="text" readonly tabindex="-1" class="form-control form-control-sm po-profit bg-light text-right font-weight-bold" value="{{ $profitVal !== null && $profitVal != 0 ? number_format($profitVal, 1).'%' : '' }}" placeholder="0.0%" autocomplete="off" title="Profit %"></td>
    <td data-col-key="disc_pct"><input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][disc_percent]" value="{{ $discPercent }}" class="form-control form-control-sm po-disc-percent text-right" placeholder="0" autocomplete="off"></td>
    <td data-col-key="disc_amt"><input type="number" step="0.01" min="0" name="items[{{ $rowId }}][disc_amount]" value="{{ $discAmount }}" class="form-control form-control-sm po-disc-amount text-right" placeholder="0.00" autocomplete="off"></td>
    <td data-col-key="gst"><input type="number" step="0.01" min="0" max="100" name="items[{{ $rowId }}][gst_percent]" value="{{ $gstPercent }}" readonly tabindex="-1" class="form-control form-control-sm po-gst text-right bg-light" placeholder="0" autocomplete="off" title="GST % (Read-only)"></td>
    <td class="text-right align-middle font-weight-bold text-success" style="width:115px;" data-col-key="net">
        ₹<span class="po-row-net">0.00</span>
    </td>
    <td class="text-center align-middle" data-col-key="action">
        <button type="button" class="btn btn-xs btn-outline-danger po-remove-row" title="Remove row"><i class="fas fa-times"></i></button>
    </td>
</tr>
