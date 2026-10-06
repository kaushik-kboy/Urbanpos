<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Item;
use App\Models\ItemCategoryValue;
use App\Models\ItemStock;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    /**
     * Barcode printing index — search items and build a print queue.
     */
    public function index(Request $request)
    {
        $search          = $request->input('search');
        $branchId        = $request->input('branch_id');
        $brandId         = $request->input('brand_id');
        $categoryValueId = $request->input('category_value_id');

        $items = collect();

        if ($search || $brandId || $categoryValueId) {
            $query = Item::with(['brand', 'categoryValue'])
                ->where('status', true)
                ->when($search, fn ($q) => $q->where(function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('item_code', 'like', "%{$search}%")
                       ->orWhere('ean_upc_code', 'like', "%{$search}%")
                       ->orWhere('alias', 'like', "%{$search}%");
                }))
                ->when($brandId, fn ($q) => $q->where('brand_id', $brandId))
                ->when($categoryValueId, fn ($q) => $q->where('category_value_id', $categoryValueId))
                ->orderBy('name')
                ->limit(100);

            $items = $query->get()->map(function ($item) use ($branchId) {
                $stock = $branchId
                    ? $item->stocks()->where('branch_id', $branchId)->first()
                    : $item->stocks()->first();

                return (object) [
                    'id'           => $item->id,
                    'item_code'    => $item->item_code,
                    'ean_upc_code' => $item->ean_upc_code,
                    'name'         => $item->name,
                    'brand_name'   => $item->brand?->name,
                    'sell_price'   => $item->sell_price,
                    'mrp'          => $item->mrp,
                    'stock_qty'    => $stock?->quantity ?? 0,
                    'barcode'      => $item->ean_upc_code ?: $item->item_code,
                ];
            });
        }

        $branches   = Branch::orderBy('name')->pluck('name', 'id');
        $brands     = Brand::orderBy('name')->pluck('name', 'id');
        $categories = ItemCategoryValue::whereHas('category', fn ($q) => $q->where('name', 'CATEGORY'))
                        ->orderBy('name')->pluck('name', 'id');

        $activeBranchId = $branchId ?: session('active_branch_id');
        $recentInvoices = \App\Models\PurchaseInvoice::with('supplier')
            ->when($activeBranchId && $activeBranchId !== 'all', fn ($q) => $q->where('branch_id', $activeBranchId))
            ->latest('invoice_date')
            ->limit(35)
            ->get();

        $selectedInvoiceId = $request->input('purchase_invoice_id');
        $initialInvoiceItems = [];
        if ($selectedInvoiceId) {
            $selInv = \App\Models\PurchaseInvoice::with('items.item')->find($selectedInvoiceId);
            if ($selInv) {
                $initialInvoiceItems = $selInv->items->map(function ($piItem) {
                    $item = $piItem->item;
                    return [
                        'id'         => $item?->id,
                        'item_code'  => $item?->item_code ?? '',
                        'name'       => $item?->name ?? 'Unknown',
                        'barcode'    => $item?->ean_upc_code ?: $item?->item_code ?: (string) $item?->id,
                        'sell_price' => (float) ($piItem->sell_price > 0 ? $piItem->sell_price : ($item?->sell_price ?? 0)),
                        'mrp'        => (float) ($piItem->mrp > 0 ? $piItem->mrp : ($item?->mrp ?? 0)),
                        'qty'        => max(1, (int) round($piItem->qty ?? $piItem->quantity ?? 1)),
                        'exp_date'   => $piItem->exp_date ? substr($piItem->exp_date, 0, 10) : '',
                        'batch_no'   => $piItem->batch_no ?? '',
                    ];
                })->values()->toArray();
            }
        }

        return view('inventory.barcode.index', compact(
            'items', 'search', 'branchId', 'brandId', 'categoryValueId',
            'branches', 'brands', 'categories', 'recentInvoices', 'selectedInvoiceId', 'initialInvoiceItems'
        ));
    }

    /**
     * AJAX fetch items from a Purchase Invoice.
     */
    public function invoiceItems(\App\Models\PurchaseInvoice $purchaseInvoice)
    {
        $items = $purchaseInvoice->items()->with('item')->get()->map(function ($piItem) {
            $item = $piItem->item;
            return [
                'id'         => $item?->id,
                'item_code'  => $item?->item_code ?? '',
                'name'       => $item?->name ?? 'Unknown',
                'barcode'    => $item?->ean_upc_code ?: $item?->item_code ?: (string) $item?->id,
                'sell_price' => (float) ($piItem->sell_price > 0 ? $piItem->sell_price : ($item?->sell_price ?? 0)),
                'mrp'        => (float) ($piItem->mrp > 0 ? $piItem->mrp : ($item?->mrp ?? 0)),
                'qty'        => max(1, (int) round($piItem->qty ?? $piItem->quantity ?? 1)),
                'exp_date'   => $piItem->exp_date ? substr($piItem->exp_date, 0, 10) : '',
                'batch_no'   => $piItem->batch_no ?? '',
            ];
        });

        return response()->json([
            'invoice_number' => $purchaseInvoice->invoice_number,
            'invoice_date'   => optional($purchaseInvoice->invoice_date)->format('d-m-Y'),
            'items'          => $items,
        ]);
    }

    /**
     * AJAX search — returns JSON array for live search & barcode scanner.
     */
    public function search(Request $request)
    {
        $q               = $request->input('q', '');
        $brandId         = $request->input('brand_id');
        $categoryValueId = $request->input('category_value_id');

        if (!$q && !$brandId && !$categoryValueId) {
            return response()->json([]);
        }

        $items = Item::with(['brand'])
            ->where('status', true)
            ->when($q, fn ($query) => $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('item_code', 'like', "%{$q}%")
                   ->orWhere('ean_upc_code', 'like', "%{$q}%")
                   ->orWhere('alias', 'like', "%{$q}%");
            }))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($categoryValueId, fn ($query) => $query->where('category_value_id', $categoryValueId))
            ->orderBy('name')
            ->limit(80)
            ->get()
            ->map(fn ($item) => [
                'id'        => $item->id,
                'item_code' => $item->item_code,
                'barcode'   => $item->ean_upc_code ?: $item->item_code,
                'name'      => $item->name,
                'brand'     => $item->brand?->name,
                'sell_price'=> (float) $item->sell_price,
                'mrp'       => (float) $item->mrp,
            ]);

        return response()->json($items);
    }

    /**
     * Render print-ready barcode label sheet via unified BarcodePrintController engine.
     */
    public function print(Request $request)
    {
        return app(\App\Http\Controllers\Master\BarcodePrintController::class)->printLabels($request);
    }
}
