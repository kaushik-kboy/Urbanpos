<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\PurchaseInvoice;
use Illuminate\Http\Request;

class BarcodePrintingController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->input('branch_id', Branch::first()->id ?? 1);
        $invoiceId = $request->input('purchase_invoice_id');

        $branches = Branch::where('status', true)->orderBy('name')->pluck('name', 'id');
        $invoices = PurchaseInvoice::where('branch_id', $branchId)
            ->latest('invoice_date')
            ->limit(20)
            ->get();

        $selectedItems = collect();

        if ($invoiceId) {
            $invoice = PurchaseInvoice::with('items.item')->find($invoiceId);
            if ($invoice) {
                foreach ($invoice->items as $invItem) {
                    if ($invItem->item) {
                        $selectedItems->push([
                            'id' => $invItem->item->id,
                            'name' => $invItem->item->name,
                            'barcode' => $invItem->item->ean_upc_code ?: str_pad((string)$invItem->item->id, 8, '0', STR_PAD_LEFT),
                            'mrp' => $invItem->mrp ?? $invItem->item->mrp,
                            'sell_price' => $invItem->sell_price ?? $invItem->item->sell_price,
                            'qty' => (int) max(1, round($invItem->qty)),
                        ]);
                    }
                }
            }
        }

        return view('inventory.barcode-printing.index', compact(
            'branches',
            'branchId',
            'invoices',
            'invoiceId',
            'selectedItems'
        ));
    }

    public function searchItems(Request $request)
    {
        $q = trim($request->input('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        $items = Item::where('name', 'like', "%{$q}%")
            ->orWhere('ean_upc_code', 'like', "%{$q}%")
            ->orWhere('alias', 'like', "%{$q}%")
            ->limit(20)
            ->get(['id', 'name', 'ean_upc_code', 'sell_price', 'mrp']);

        return response()->json($items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'barcode' => $item->ean_upc_code ?: str_pad((string)$item->id, 8, '0', STR_PAD_LEFT),
            'mrp' => (float) $item->mrp,
            'sell_price' => (float) $item->sell_price,
        ]));
    }

    public function print(Request $request)
    {
        return app(\App\Http\Controllers\Master\BarcodePrintController::class)->printLabels($request);
    }
}
