<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\PurchaseInvoiceItem;
use App\Services\Inventory\BatchStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseLookupController extends Controller
{
    public function __construct(
        private readonly BatchStockService $batchStockService
    ) {}

    public function lookup(Request $request, string $code): JsonResponse
    {
        $code = trim($code);

        $item = Item::query()
            ->where('ean_upc_code', $code)
            ->orWhere('item_code', $code)
            ->orWhere('id', $code)
            ->with(['supplier', 'brand', 'categoryValue'])
            ->first();

        if (! $item) {
            return response()->json([
                'message' => "No product found matching code: {$code}",
            ], 404);
        }

        $user = $request->user();
        $branchId = (int) ($request->query('branch_id') ?? $user->branch_id ?? 1);

        $costPrice = (float) ($item->cost_price ?? $item->landing_cost ?? 0);
        $landingCost = (float) ($item->landing_cost ?? $item->cost_price ?? 0);
        $mrp = (float) ($item->mrp ?? 0);
        $sellPrice = (float) ($item->sell_price ?? $mrp);
        $marginPercent = $mrp > 0 ? round((($mrp - $costPrice) / $mrp) * 100, 1) : 0;

        // Current branch stock from batches
        $batches = $this->batchStockService->getItemBatches($item->id, $branchId);
        $currentStock = (float) $batches->sum(fn ($b) => $b['remaining_qty'] ?? 0);

        // Recent Purchase Inward lines for this item
        $recentInwards = PurchaseInvoiceItem::query()
            ->where('item_id', $item->id)
            ->with(['purchaseInvoice.supplier', 'purchaseInvoice.branch'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $inv = $row->purchaseInvoice;
                return [
                    'id' => $row->id,
                    'invoice_number' => $inv?->invoice_number ?? $inv?->supplier_inv_no ?? '-',
                    'invoice_date' => $inv?->invoice_date?->format('Y-m-d') ?? $inv?->created_at?->format('Y-m-d'),
                    'supplier_name' => $inv?->supplier?->name ?? '-',
                    'branch_name' => $inv?->branch?->name ?? '-',
                    'cost_price' => (float) $row->cost_price,
                    'qty' => (float) $row->qty,
                    'free_qty' => (float) ($row->free_qty ?? 0),
                    'net_amount' => (float) $row->net_amount,
                    'batch_no' => $row->batch_no,
                    'exp_date' => $row->exp_date?->format('Y-m-d'),
                ];
            });

        return response()->json([
            'data' => [
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'item_code' => $item->item_code,
                    'ean_upc_code' => $item->ean_upc_code,
                    'brand_name' => $item->brand?->name,
                    'category_name' => $item->categoryValue?->name,
                ],
                'pricing' => [
                    'cost_price' => $costPrice,
                    'landing_cost' => $landingCost,
                    'mrp' => $mrp,
                    'sell_price' => $sellPrice,
                    'margin_percent' => $marginPercent,
                ],
                'supplier' => $item->supplier ? [
                    'id' => $item->supplier->id,
                    'name' => $item->supplier->name,
                    'mobile' => $item->supplier->mobile ?? $item->supplier->phone ?? null,
                    'gstin' => $item->supplier->gstin ?? null,
                    'email' => $item->supplier->email ?? null,
                ] : null,
                'inventory' => [
                    'branch_id' => $branchId,
                    'current_stock' => $currentStock,
                    'batch_count' => $batches->count(),
                ],
                'recent_inwards' => $recentInwards,
            ],
        ]);
    }
}
