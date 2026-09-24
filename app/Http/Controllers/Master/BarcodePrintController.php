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
        [$labels, $storeName, $format] = $this->resolveLabelsAndStore($request);

        return view('master.barcodes.print-labels', [
            'labels'    => $labels,
            'format'    => $format,
            'storeName' => $storeName,
        ]);
    }

    /**
     * Export raw TSPL (TSC Printer Language) for TSC TE244 / TSPL direct printing.
     */
    public function downloadTspl(Request $request)
    {
        [$labels, $storeName] = $this->resolveLabelsAndStore($request);

        // 102mm x 63.5mm (4" x 2.5") with 2mm gap at 203 DPI (8 dots/mm)
        // 102mm = 816 dots, 63.5mm = 508 dots
        $output = "SIZE 102 mm, 63.5 mm\r\n";
        $output .= "GAP 2 mm, 0 mm\r\n";
        $output .= "DIRECTION 1\r\n";
        $output .= "REFERENCE 0,0\r\n";
        $output .= "OFFSET 0 mm\r\n";
        $output .= "SET PEEL OFF\r\n";
        $output .= "SET CUTTER OFF\r\n";
        $output .= "SET TEAR ON\r\n";

        foreach ($labels as $lbl) {
            $cleanStore = substr(preg_replace('/[^A-Za-z0-9 \-]/', '', $storeName), 0, 36);
            $cleanName  = substr(preg_replace('/[^A-Za-z0-9 \-]/', '', $lbl['name']), 0, 38);
            $cleanCode  = preg_replace('/[^A-Za-z0-9]/', '', $lbl['barcode']);
            $mrpText    = 'MRP: Rs. ' . number_format($lbl['mrp'], 2);
            $priceText  = 'PRICE: Rs. ' . number_format($lbl['sell_price'], 2);

            $output .= "CLS\r\n";
            // Header Store Name centered around dot 408
            $output .= "TEXT 408,24,\"3\",0,1,1,2,\"{$cleanStore}\"\r\n";
            // Product Name
            $output .= "TEXT 50,70,\"3\",0,1,1,\"{$cleanName}\"\r\n";
            // Code128 Barcode: X=80, Y=125, height=90 dots, human-readable=1, narrow=2, wide=4
            $output .= "BARCODE 80,125,\"128\",90,1,0,2,4,\"{$cleanCode}\"\r\n";
            // Price info
            if ($lbl['mrp'] > $lbl['sell_price']) {
                $output .= "TEXT 50,250,\"3\",0,1,1,\"{$mrpText}\"\r\n";
            }
            $output .= "TEXT 450,245,\"4\",0,1,1,\"{$priceText}\"\r\n";
            if (!empty($lbl['exp_date'])) {
                $output .= "TEXT 50,300,\"2\",0,1,1,\"EXP: {$lbl['exp_date']}\"\r\n";
            }
            $output .= "PRINT 1\r\n";
        }

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="labels_tsc_te244.prn"',
        ]);
    }

    /**
     * Resolve labels and store branding from request.
     */
    private function resolveLabelsAndStore(Request $request): array
    {
        $format = $request->query('format', $request->input('format', '102x64'));
        $storeName = config('app.name', 'UrbanPOS');
        $labels = [];
        $branchId = null;

        // Scenario 1: Print from Purchase Invoice
        if ($request->filled('purchase_invoice_id')) {
            $pi = PurchaseInvoice::with(['items.item', 'branch'])->findOrFail($request->input('purchase_invoice_id'));
            $branchId = $pi->branch_id ?? null;

            if ($branchId) {
                $setting = \App\Models\ReceiptSetting::forDocument('purchase_invoice', $branchId);
                $storeName = $setting->store_name ?: ($pi->branch?->name ?: $storeName);
            }

            foreach ($pi->items as $piItem) {
                $item = $piItem->item;
                if (! $item) continue;

                $barcode = $item->ean_upc_code ?: $item->item_code ?: (string) $item->id;
                $qty = max(1, (int) round($piItem->qty ?? $piItem->quantity ?? 1));

                for ($i = 0; $i < $qty; $i++) {
                    $labels[] = [
                        'item_id'    => $item->id,
                        'name'       => $item->name,
                        'code'       => $item->item_code ?: ('#' . $item->id),
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
                    'code'       => $item->item_code ?: ('#' . $item->id),
                    'barcode'    => $barcode,
                    'mrp'        => (float) $item->mrp,
                    'sell_price' => (float) $item->sell_price,
                    'exp_date'   => null,
                ];
            }
        }

        return [$labels, $storeName, $format];
    }
}
