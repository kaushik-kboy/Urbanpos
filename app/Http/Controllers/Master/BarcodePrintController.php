<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarcodePrintController extends Controller
{
    /**
     * Render the barcode labels print view.
     */
    public function printLabels(Request $request): View
    {
        $format = $request->query('format', $request->input('format', '50x25'));
        $storeName = config('app.name', 'UrbanPOS');
        $labels = [];

        // Scenario 1: Print from Purchase Invoice
        if ($request->filled('purchase_invoice_id')) {
            $pi = PurchaseInvoice::with(['items.item'])->findOrFail($request->input('purchase_invoice_id'));
            foreach ($pi->items as $piItem) {
                $item = $piItem->item;
                if (! $item) continue;

                $barcode = $item->ean_upc_code ?: $item->item_code ?: (string) $item->id;
                $qty = max(1, (int) round($piItem->qty ?? $piItem->quantity ?? 1));

                for ($i = 0; $i < $qty; $i++) {
                    $labels[] = [
                        'item_id'    => $item->id,
                        'name'       => $item->name,
                        'code'       => $item->item_code,
                        'barcode'    => $barcode,
                        'mrp'        => (float) ($piItem->mrp > 0 ? $piItem->mrp : $item->mrp),
                        'sell_price' => (float) ($piItem->sell_price > 0 ? $piItem->sell_price : $item->sell_price),
                        'exp_date'   => $piItem->exp_date ? substr($piItem->exp_date, 0, 10) : null,
                    ];
                }
            }
        }
        // Scenario 2: Print single Item with quantity
        elseif ($request->filled('item_id')) {
            $item = Item::findOrFail($request->input('item_id'));
            $barcode = $item->ean_upc_code ?: $item->item_code ?: (string) $item->id;
            $qty = max(1, (int) $request->input('qty', 1));

            for ($i = 0; $i < $qty; $i++) {
                $labels[] = [
                    'item_id'    => $item->id,
                    'name'       => $item->name,
                    'code'       => $item->item_code,
                    'barcode'    => $barcode,
                    'mrp'        => (float) $item->mrp,
                    'sell_price' => (float) $item->sell_price,
                    'exp_date'   => null,
                ];
            }
        }

        return view('master.barcodes.print-labels', [
            'labels'    => $labels,
            'format'    => $format,
            'storeName' => $storeName,
        ]);
    }
}
