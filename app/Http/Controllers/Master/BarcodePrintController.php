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
    /**
     * Render the barcode labels print view.
     */
    public function printLabels(Request $request): View
    {
        [$labels, $storeName, $format, $options] = $this->resolveLabelsAndStore($request);

        return view('master.barcodes.print-labels', [
            'labels'    => $labels,
            'format'    => $format,
            'storeName' => $storeName,
            'options'   => $options,
        ]);
    }

    /**
     * Export raw TSPL (TSC Printer Language) for TSC TE244 / TSPL direct printing.
     */
    public function downloadTspl(Request $request)
    {
        [$labels, $storeName, $format] = $this->resolveLabelsAndStore($request);
        $output = $this->generateTspl($labels, $storeName, $format);

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="labels_tsc_te244.prn"',
        ]);
    }

    /**
     * Send TSPL directly to local TSC TE244 printer (1-click printing).
     */
    public function directPrint(Request $request)
    {
        [$labels, $storeName, $format] = $this->resolveLabelsAndStore($request);

        if (empty($labels)) {
            return response()->json(['success' => false, 'message' => 'No items selected for printing.'], 422);
        }

        $tspl = $this->generateTspl($labels, $storeName, $format);

        $tempFile = tempnam(sys_get_temp_dir(), 'tsc_');
        file_put_contents($tempFile, $tspl);

        if (!function_exists('shell_exec') || stripos((string) ini_get('disable_functions'), 'shell_exec') !== false) {
            @unlink($tempFile);
            return response()->json([
                'success' => false,
                'message' => 'Direct USB printing is only available when running UrbanPOS locally on the POS machine. On cloud hosting, please click "Print via Chrome (Ctrl + P)".',
            ], 400);
        }

        $scriptPath = base_path('scripts/print_tspl_direct.py');
        $cmd = "python " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempFile);
        $output = '';
        try {
            $output = (string) \shell_exec($cmd . " 2>&1");
        } catch (\Throwable $e) {
            $output = $e->getMessage();
        }
        @unlink($tempFile);

        if ($output && str_contains($output, 'SUCCESS')) {
            return response()->json([
                'success' => true,
                'message' => 'Printed ' . count($labels) . ' stickers directly to TSC TE244!',
                'count'   => count($labels),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Printer error: ' . trim($output ?? 'Unable to connect to printer'),
        ], 500);
    }

    /**
     * Generate raw TSPL commands calibrated for TSC TE244.
     */
    public function generateTspl(array $labels, string $storeName, string $format): string
    {
        $is2Up = str_ends_with($format, '_2up') || $format === '38x25';
        $rowHeightMm = match ($format) {
            '50x38_2up' => 38,
            '50x50_2up' => 50,
            '102x64'    => 63.5,
            default     => 25,
        };

        $output = "SIZE 102 mm, {$rowHeightMm} mm\r\n";
        $output .= "GAP 2 mm, 0 mm\r\n";
        $output .= "DIRECTION 1\r\n";
        $output .= "REFERENCE 0,0\r\n";
        $output .= "OFFSET 0 mm\r\n";
        $output .= "SET PEEL OFF\r\n";
        $output .= "SET CUTTER OFF\r\n";
        $output .= "SET TEAR ON\r\n";

        if ($is2Up) {
            $buildLabel = function (array $lbl, int $x) use ($storeName): string {
                $cleanStore = substr(preg_replace('/[^A-Za-z0-9 \-]/', '', $storeName), 0, 18);
                $cleanName  = trim(preg_replace('/[^A-Za-z0-9 \-\.\,\&\/]/', '', $lbl['name']));
                $code       = preg_replace('/[^A-Za-z0-9]/', '', $lbl['barcode']);

                $mrpDecimals = ($lbl['mrp'] == (int)$lbl['mrp']) ? 0 : 2;
                $sellDecimals = ($lbl['sell_price'] == (int)$lbl['sell_price']) ? 0 : 2;
                $mrpStr = 'Rs. ' . number_format($lbl['mrp'], $mrpDecimals);
                $sellStr = 'Rs. ' . number_format($lbl['sell_price'], $sellDecimals);

                $t = "TEXT {$x},15,\"3\",0,1,1,\"{$cleanStore}\"\r\n";

                // Smart Long Product Name Handling:
                // <= 16 chars: Large Font "3" (1 line)
                // > 16 chars: Medium Font "2" (2 lines auto-wrapped)
                if (strlen($cleanName) > 16) {
                    $wrapped = explode("\n", wordwrap($cleanName, 20, "\n", true));
                    $line1 = substr(trim($wrapped[0]), 0, 22);
                    $line2 = substr(trim($wrapped[1] ?? ''), 0, 22);
                    if (count($wrapped) > 2 && strlen($line2) > 18) {
                        $line2 = substr($line2, 0, 18) . '...';
                    }
                    $t .= "TEXT {$x},38,\"2\",0,1,1,\"{$line1}\"\r\n";
                    if ($line2 !== '') {
                        $t .= "TEXT {$x},60,\"2\",0,1,1,\"{$line2}\"\r\n";
                    }
                } else {
                    $t .= "TEXT {$x},48,\"3\",0,1,1,\"{$cleanName}\"\r\n";
                }

                $t .= "BARCODE {$x},88,\"128\",55,1,0,2,2,\"{$code}\"\r\n";

                if ($lbl['mrp'] > $lbl['sell_price']) {
                    $t .= "TEXT {$x},190,\"3\",0,1,1,\"MRP: {$mrpStr}\"\r\n";
                    $t .= "TEXT {$x},225,\"3\",0,1,1,\"PRICE: {$sellStr}\"\r\n";
                } else {
                    $t .= "TEXT {$x},190,\"3\",0,1,1,\"MRP: {$mrpStr}\"\r\n";
                    $t .= "TEXT {$x},225,\"2\",0,1,1,\"(Incl. TAX)\"\r\n";
                }

                return $t;
            };

            $chunks = array_chunk($labels, 2);
            foreach ($chunks as $pair) {
                $output .= "CLS\r\n";

                // Left Sticker (X = 115)
                $output .= $buildLabel($pair[0], 115);

                // Right Sticker (X = 430, shifted inward for safe margin to prevent edge cut)
                $lbl2 = $pair[1] ?? $pair[0];
                $output .= $buildLabel($lbl2, 430);

                $output .= "PRINT 1,1\r\n";
            }
        } else {
            foreach ($labels as $lbl) {
                $cleanStore = substr(preg_replace('/[^A-Za-z0-9 \-]/', '', $storeName), 0, 36);
                $cleanName  = substr(preg_replace('/[^A-Za-z0-9 \-]/', '', $lbl['name']), 0, 38);
                $cleanCode  = preg_replace('/[^A-Za-z0-9]/', '', $lbl['barcode']);
                $mrpText    = 'MRP: Rs. ' . number_format($lbl['mrp'], 2);
                $priceText  = 'PRICE: Rs. ' . number_format($lbl['sell_price'], 2);

                $output .= "CLS\r\n";
                $output .= "TEXT 408,24,\"3\",0,1,1,2,\"{$cleanStore}\"\r\n";
                $output .= "TEXT 50,70,\"3\",0,1,1,\"{$cleanName}\"\r\n";
                $output .= "BARCODE 80,125,\"128\",90,1,0,2,4,\"{$cleanCode}\"\r\n";
                if ($lbl['mrp'] > $lbl['sell_price']) {
                    $output .= "TEXT 50,250,\"3\",0,1,1,\"{$mrpText}\"\r\n";
                }
                $output .= "TEXT 450,245,\"4\",0,1,1,\"{$priceText}\"\r\n";
                if (!empty($lbl['exp_date'])) {
                    $output .= "TEXT 50,300,\"2\",0,1,1,\"EXP: {$lbl['exp_date']}\"\r\n";
                }
                $output .= "PRINT 1\r\n";
            }
        }

        return $output;
    }

    /**
     * Resolve labels, store branding, and format options from request.
     */
    private function resolveLabelsAndStore(Request $request): array
    {
        $format = $request->query('format', $request->input('format', 'a4_24'));
        $storeName = config('app.name', 'UrbanPOS');
        $labels = [];
        $branchId = $request->input('branch_id');

        $isPost = $request->isMethod('post');
        $options = [
            'show_store' => $isPost ? $request->boolean('show_store') : filter_var($request->input('show_store', true), FILTER_VALIDATE_BOOLEAN),
            'show_mrp'   => $isPost ? $request->boolean('show_mrp') : filter_var($request->input('show_mrp', true), FILTER_VALIDATE_BOOLEAN),
            'show_sell'  => $isPost ? $request->boolean('show_sell') : filter_var($request->input('show_sell', true), FILTER_VALIDATE_BOOLEAN),
            'show_exp'   => $isPost ? $request->boolean('show_exp') : filter_var($request->input('show_exp', true), FILTER_VALIDATE_BOOLEAN),
            'show_code'  => $isPost ? $request->boolean('show_code') : filter_var($request->input('show_code', true), FILTER_VALIDATE_BOOLEAN),
        ];

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
                        'batch_no'   => $piItem->batch_no ?? null,
                    ];
                }
            }
        }
        // Scenario 2: Print from Print Queue Array (POST or GET)
        elseif ($request->has('items') && is_array($request->input('items'))) {
            $itemsList = $request->input('items');
            // If items is a map with { id: { ... } } or list of items
            foreach ($itemsList as $raw) {
                if (empty($raw) || !is_array($raw)) continue;
                $qty = max(1, (int) ($raw['qty'] ?? 1));
                $barcode = !empty($raw['barcode']) ? $raw['barcode'] : ($raw['code'] ?? '00000000');
                
                for ($i = 0; $i < $qty; $i++) {
                    $labels[] = [
                        'item_id'    => $raw['id'] ?? null,
                        'name'       => $raw['name'] ?? ($raw['item_name'] ?? 'Item'),
                        'code'       => $raw['code'] ?? ($raw['item_code'] ?? ''),
                        'barcode'    => $barcode,
                        'mrp'        => (float) ($raw['mrp'] ?? 0),
                        'sell_price' => (float) ($raw['sell_price'] ?? 0),
                        'exp_date'   => !empty($raw['exp_date']) ? substr($raw['exp_date'], 0, 10) : null,
                        'batch_no'   => $raw['batch_no'] ?? null,
                    ];
                }
            }
        }
        // Scenario 3: Print single Item with quantity
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
                    'batch_no'   => null,
                ];
            }
        }

        return [$labels, $storeName, $format, $options];
    }
}
